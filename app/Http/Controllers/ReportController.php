<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {}

    /**
     * Reports page — all tabs rendered from one page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Reports/Index');
    }

    /**
     * Get daily report data (Inertia visit).
     */
    public function daily(Request $request): Response
    {
        $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $data = $this->reportService->daily($request->input('date'));

        return Inertia::render('Reports/Index', [
            'dailyReport' => $data,
            'activeTab' => 'daily',
        ]);
    }

    /**
     * Get date range report.
     */
    public function range(Request $request): Response
    {
        $request->validate([
            'start_date' => ['required', 'date', 'before_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:today'],
        ]);

        $data = $this->reportService->range(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return Inertia::render('Reports/Index', [
            'rangeReport' => $data,
            'activeTab' => 'range',
        ]);
    }

    /**
     * Get top selling products.
     */
    public function topProducts(Request $request): Response
    {
        $request->validate([
            'start_date' => ['required', 'date', 'before_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:today'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $data = $this->reportService->topProducts(
            $request->input('start_date'),
            $request->input('end_date'),
            (int) $request->input('limit', 10)
        );

        return Inertia::render('Reports/Index', [
            'topProducts' => $data,
            'activeTab' => 'top-products',
        ]);
    }
}
