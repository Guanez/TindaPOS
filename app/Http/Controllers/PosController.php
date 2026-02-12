<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductInactiveException;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService
    ) {}

    /**
     * Show the POS terminal page.
     */
    public function index(): Response
    {
        $products = Product::with('category')
            ->active()
            ->orderBy('name')
            ->get();

        $categories = Category::active()
            ->orderBy('name')
            ->get();

        return Inertia::render('POS/Index', [
            'products' => ProductResource::collection($products),
            'categories' => $categories,
        ]);
    }

    /**
     * Process checkout — the most critical endpoint.
     */
    public function checkout(CheckoutRequest $request): RedirectResponse
    {
        try {
            $sale = $this->saleService->checkout(
                $request->validated(),
                $request->user()
            );

            return redirect()->back()->with([
                'success' => 'Sale completed!',
                'sale' => $sale->load('items')->toArray(),
            ]);
        } catch (InsufficientStockException|ProductInactiveException $e) {
            return redirect()->back()->withErrors(['checkout' => $e->getMessage()]);
        }
    }
}
