<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Sale;
use App\Services\ReportService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {}

    public function __invoke(): Response
    {
        $stats = $this->reportService->dashboard();

        $recentSales = Sale::with('user')
            ->completed()
            ->today()
            ->latest()
            ->take(5)
            ->get();

        $lowStockProducts = Product::with('category')
            ->lowStock()
            ->orderBy('stock_quantity')
            ->take(8)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentSales' => $recentSales,
            'lowStockProducts' => ProductResource::collection($lowStockProducts),
        ]);
    }
}
