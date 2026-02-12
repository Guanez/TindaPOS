<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\InvalidStockException;
use App\Http\Requests\RestockRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockLog;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService
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

        $products = $query->orderBy('name')->paginate(15)->withQueryString();
        $categories = Category::active()->orderBy('name')->get();

        return Inertia::render('Inventory/Index', [
            'products' => ProductResource::collection($products),
            'categories' => $categories,
            'filters' => $request->only(['search', 'category', 'stock']),
        ]);
    }

    /**
     * Store a new product.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return redirect()->route('inventory.index')
            ->with('success', 'Product created successfully.');
    }

    /**
     * Update an existing product.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('inventory.index')
            ->with('success', 'Product updated successfully.');
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
     */
    public function restock(RestockRequest $request): RedirectResponse
    {
        try {
            $this->inventoryService->restock(
                $request->validated('product_id'),
                $request->validated('quantity'),
                $request->user(),
                $request->validated('reason') ?? 'Restock'
            );

            return redirect()->back()->with('success', 'Product restocked successfully.');
        } catch (InvalidStockException $e) {
            return redirect()->back()->withErrors(['restock' => $e->getMessage()]);
        }
    }

    /**
     * Stock logs for audit trail.
     */
    public function logs(Request $request): Response
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
