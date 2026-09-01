<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Sale;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {}

    /**
     * The landing screen for every role, which is why what it publishes has
     * to be decided here rather than left to the page.
     *
     * This route carries no role middleware — a cashier belongs on it — so it
     * is the one manager-only surface in the app whose guard is a condition
     * instead of a route. Everything cost-derived is simply not queried for a
     * cashier, so there is nothing on the wire for a page bug to leak later.
     */
    public function __invoke(Request $request): Response
    {
        $isManager = $request->user()->isManager();

        $recentSales = Sale::with('user')
            ->completed()
            ->today()
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => $this->reportService->dashboard($isManager),
            // How the queue has run today. The live counts arrive separately
            // on the shared `queue` prop, which polls; this is the settled
            // history behind them and does not need to.
            'orderFlow' => $this->reportService->orderFlow(),
            'recentSales' => $recentSales,
            'lowStockProducts' => $isManager
                ? ProductResource::collection(
                    Product::with('category')
                        ->lowStock()
                        ->orderBy('stock_quantity')
                        ->take(8)
                        ->get()
                )
                : [],
        ]);
    }
}
