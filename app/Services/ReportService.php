<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;

class ReportService
{
    /**
     * Dashboard summary — today's stats at a glance.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $todaySales = Sale::completed()->today();

        $revenue = (float) $todaySales->sum('total');
        $transactions = $todaySales->count();
        $discounts = (float) $todaySales->sum('discount');

        // Profit: sum of (selling_price - cost_price) * quantity for today's sale items
        $profit = (float) SaleItem::whereHas('sale', fn ($q) => $q->completed()->today())
            ->selectRaw('SUM((selling_price - cost_price) * quantity) as profit')
            ->value('profit') ?? 0;

        $lowStockCount = Product::lowStock()->count();
        $totalProducts = Product::active()->count();

        return [
            'revenue' => $revenue,
            'transactions' => $transactions,
            'profit' => $profit,
            'discounts' => $discounts,
            'low_stock_count' => $lowStockCount,
            'total_products' => $totalProducts,
        ];
    }

    /**
     * Daily report with hourly breakdown.
     *
     * @return array<string, mixed>
     */
    public function daily(string $date): array
    {
        $sales = Sale::completed()->whereDate('created_at', $date);

        $revenue = (float) $sales->sum('total');
        $transactions = $sales->count();
        $discounts = (float) $sales->sum('discount');

        $profit = (float) SaleItem::whereHas('sale', fn ($q) => $q->completed()->whereDate('created_at', $date))
            ->selectRaw('SUM((selling_price - cost_price) * quantity) as profit')
            ->value('profit') ?? 0;

        $itemsSold = (int) SaleItem::whereHas('sale', fn ($q) => $q->completed()->whereDate('created_at', $date))
            ->sum('quantity');

        $avgTransaction = $transactions > 0 ? $revenue / $transactions : 0;

        // Hourly breakdown
        $hour = $this->hourExpression();

        $hourly = Sale::completed()
            ->whereDate('created_at', $date)
            ->selectRaw("{$hour} as hour, COUNT(*) as transactions, SUM(total) as revenue")
            ->groupByRaw($hour)
            ->orderBy('hour')
            ->get()
            ->keyBy(fn (Sale $row) => (int) $row->getAttribute('hour'));

        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyData[] = [
                'hour' => $h,
                'label' => sprintf('%02d:00', $h),
                'transactions' => (int) ($hourly[$h]->transactions ?? 0),
                'revenue' => (float) ($hourly[$h]->revenue ?? 0),
            ];
        }

        return [
            'date' => $date,
            'revenue' => $revenue,
            'transactions' => $transactions,
            'profit' => $profit,
            'discounts' => $discounts,
            'items_sold' => $itemsSold,
            'avg_transaction' => round($avgTransaction, 2),
            'hourly' => $hourlyData,
        ];
    }

    /**
     * Date range report with daily and payment breakdowns.
     *
     * @return array<string, mixed>
     */
    public function range(string $startDate, string $endDate): array
    {
        $sales = Sale::completed()->dateRange($startDate, $endDate);

        $revenue = (float) $sales->sum('total');
        $transactions = $sales->count();
        $discounts = (float) $sales->sum('discount');

        $profit = (float) SaleItem::whereHas('sale', fn ($q) => $q->completed()->dateRange($startDate, $endDate))
            ->selectRaw('SUM((selling_price - cost_price) * quantity) as profit')
            ->value('profit') ?? 0;

        // Daily breakdown
        $daily = Sale::completed()
            ->dateRange($startDate, $endDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as transactions, SUM(total) as revenue, SUM(discount) as discounts')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        // Payment method breakdown
        $payments = Sale::completed()
            ->dateRange($startDate, $endDate)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total) as revenue')
            ->groupBy('payment_method')
            ->get();

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'revenue' => $revenue,
            'transactions' => $transactions,
            'profit' => $profit,
            'discounts' => $discounts,
            'daily' => $daily,
            'payment_methods' => $payments,
        ];
    }

    /**
     * SQL expression for the hour-of-day of a sale, per database driver.
     * MySQL/MariaDB have HOUR(); SQLite and Postgres do not.
     */
    private function hourExpression(): string
    {
        return match ((new Sale)->getConnection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%H', created_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(HOUR FROM created_at)',
            default => 'HOUR(created_at)',
        };
    }

    /**
     * Top selling products in a date range.
     *
     * @return array<int, mixed>
     */
    public function topProducts(string $startDate, string $endDate, int $limit = 10): array
    {
        $products = SaleItem::whereHas('sale', fn ($q) => $q->completed()->dateRange($startDate, $endDate))
            ->selectRaw('product_id, product_name, SUM(quantity) as total_quantity, SUM(line_total) as total_revenue, COUNT(DISTINCT sale_id) as order_count')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        return $products->toArray();
    }
}
