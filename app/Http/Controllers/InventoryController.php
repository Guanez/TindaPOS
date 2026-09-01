<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RestockRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\StockLog;
use App\Services\InventoryService;
use App\Services\MenuService;
use App\Services\ProductImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly MenuService $menuService,
        private readonly ProductImageService $productImages,
    ) {}

    /**
     * Inventory management page with paginated products.
     */
    public function index(Request $request): Response
    {
        $query = Product::with('category');

        // Search filter
        if ($search = $request->input('search')) {
            $query->search($search);
        }

        // Category filter
        if ($categoryId = $request->input('category')) {
            $query->where('category_id', $categoryId);
        }

        // Stock status filter
        if ($stockFilter = $request->input('stock')) {
            match ($stockFilter) {
                'low' => $query->lowStock(),
                'out' => $query->where('stock_quantity', '<=', 0),
                'ok' => $query->whereColumn('stock_quantity', '>', 'low_stock_threshold'),
                default => null,
            };
        }

        $products = $query->with(['variants', 'modifierGroups'])
            ->orderBy('name')->paginate(15)->withQueryString();

        $categories = Category::active()->orderBy('name')->get();

        return Inertia::render('Inventory/Index', [
            'products' => ProductResource::collection($products),
            'categories' => $categories,
            'modifierGroups' => ModifierGroup::with('modifiers')->orderBy('name')->get(),
            'filters' => $request->only(['search', 'category', 'stock']),
        ]);
    }

    /**
     * Store a new product.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->productImages->store($request->file('image'));
        }

        $product = Product::create($data);
        $this->saveMenu($product, $data);

        return redirect()->route('inventory.index')
            ->with('success', 'Product created successfully.');
    }

    /**
     * Update an existing product.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        // Three cases, and only the first two touch storage: a new file
        // replaces whatever was there, an explicit removal clears it, and
        // sending neither leaves the existing photo alone — which is what a
        // form that only changed the price is doing.
        if ($request->hasFile('image')) {
            $data['image_path'] = $this->productImages->replace($product, $request->file('image'));
        } elseif ($request->boolean('remove_image')) {
            $this->productImages->delete($product);
            $data['image_path'] = null;
        }

        $product->update($data);
        $this->saveMenu($product, $data);

        return redirect()->route('inventory.index')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Sizes and add-ons are edited in the same modal as the product, so they
     * are saved by the same request.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveMenu(Product $product, array $data): void
    {
        if (array_key_exists('variants', $data)) {
            $this->menuService->syncVariants($product, $data['variants']);
        }

        if (array_key_exists('modifier_group_ids', $data)) {
            $this->menuService->syncModifierGroups($product, $data['modifier_group_ids']);
        }
    }

    /**
     * Delete a product (soft — deactivate).
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->update(['is_active' => false]);

        return redirect()->route('inventory.index')
            ->with('success', 'Product deactivated.');
    }

    /**
     * Restock a product.
     *
     * Invalid quantities are rendered by the domain exception handlers
     * registered in bootstrap/app.php.
     */
    public function restock(RestockRequest $request): RedirectResponse
    {
        $this->inventoryService->restock(
            $request->validated('product_id'),
            $request->validated('quantity'),
            $request->user(),
            $request->validated('reason') ?? 'Restock'
        );

        return redirect()->back()->with('success', 'Product restocked successfully.');
    }

    /**
     * Stock logs for audit trail.
     */
    public function logs(): Response
    {
        $logs = StockLog::with(['product', 'user'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inventory/Logs', [
            'logs' => $logs,
        ]);
    }
}
