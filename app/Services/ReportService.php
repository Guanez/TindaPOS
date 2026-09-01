<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ReportService
{
    /**
     * Dashboard summary — today's stats at a glance.
     *
     * `$withMargins` decides whether the cost-derived figures are computed at
     * all, rather than computing them and trusting the page not to render
     * them. Profit is (selling_price - cost_price) summed over the day, so
     * publishing it to a cashier hands them the buy price of everything they
     * sold — which is exactly what ProductResource goes to some trouble to
     * withhold from the same person on the next screen.
     *
     * Low stock rides along with it: the only thing to do about a low count
     * is restock, and that whole surface is behind `role:owner,admin`.
     *
     * @return array<string, mixed>
     */
    public function dashboard(bool $withMargins = true): array
    {
        $todaySales = Sale::completed()->today();

        $stats = [
            'revenue' => (float) $todaySales->sum('total'),
            'transactions' => $todaySales->count(),
            'discounts' => (float) $todaySales->sum('discount'),
            'total_products' => Product::active()->count(),
        ];

        if (! $withMargins) {
            return $stats;
        }

        // Profit: sum of (selling_price - cost_price) * quantity for today's sale items
        $profit = (float) SaleItem::whereHas('sale', fn ($q) => $q->completed()->today())
            ->selectRaw('SUM((selling_price - cost_price) * quantity) as profit')
            ->value('profit') ?? 0;

        return [
            ...$stats,
            'profit' => $profit,
            'low_stock_count' => Product::lowStock()->count(),
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
            // A number on its own is not a report. ₱48,000 is a good week or
            // a bad one depending entirely on the week before it, and that is
            // the comparison an owner makes in their head anyway.
            'comparison' => $this->comparison($startDate, $endDate, [
                'revenue' => $revenue,
                'transactions' => (float) $transactions,
                'profit' => $profit,
                'discounts' => $discounts,
            ]),
        ];
    }

    /**
     * The same span of days immediately before this one, and the change.
     *
     * Equal length rather than "last month" so the two are actually
     * comparable: a four-day range compared against a thirty-day one would
     * read as a collapse every time.
     *
     * @param  array<string, float>  $current
     * @return array<string, mixed>
     */
    private function comparison(string $startDate, string $endDate, array $current): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        // Carbon returns a float here, and a period is a whole number of days.
        $days = (int) $start->diffInDays($end) + 1;

        $previousEnd = $start->subDay();
        $previousStart = $previousEnd->subDays($days - 1);

        $sales = Sale::completed()->dateRange(
            $previousStart->toDateString(),
            $previousEnd->toDateString(),
        );

        $previous = [
            'revenue' => (float) $sales->sum('total'),
            'transactions' => (float) $sales->count(),
            'discounts' => (float) $sales->sum('discount'),
            'profit' => (float) (SaleItem::whereHas(
                'sale',
                fn ($q) => $q->completed()->dateRange(
                    $previousStart->toDateString(),
                    $previousEnd->toDateString(),
                ),
            )->selectRaw('SUM((selling_price - cost_price) * quantity) as profit')
                ->value('profit') ?? 0),
        ];

        return [
            'start_date' => $previousStart->toDateString(),
            'end_date' => $previousEnd->toDateString(),
            'days' => $days,
            'previous' => $previous,
            'change' => [
                'revenue' => $this->percentChange($previous['revenue'], $current['revenue']),
                'transactions' => $this->percentChange($previous['transactions'], $current['transactions']),
                'profit' => $this->percentChange($previous['profit'], $current['profit']),
                'discounts' => $this->percentChange($previous['discounts'], $current['discounts']),
            ],
        ];
    }

    /**
     * Percentage change, or null when there is nothing to compare against.
     *
     * Null rather than 100% for a previous period of zero: going from no
     * sales to some sales is not a percentage, and printing "+100%" for the
     * first week a shop was open is a made-up number.
     */
    private function percentChange(float $before, float $after): ?float
    {
        if ($before <= 0.0) {
            return null;
        }

        return round((($after - $before) / $before) * 100, 1);
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

    /**
     * How today's online orders have flowed, for the dashboard.
     *
     * Separate from `dashboard()` because it counts a different thing. That
     * method reports money that changed hands; this one reports work — and a
     * cafe owner opening the app in the morning wants to know how long people
     * are waiting well before they want to know the margin on a latte.
     *
     * Deliberately available to every role. A cashier being told the shop is
     * running eight minutes behind is a cashier who can do something about
     * it, and none of it is derived from cost.
     *
     * @return array<string, mixed>
     */
    public function orderFlow(): array
    {
        $today = Order::query()->today();

        // Only orders that actually went through preparation can say how long
        // preparation takes. An order collected straight off the counter has
        // a ready_at, but one rejected at the till never had the work done.
        $prepared = (clone $today)
            ->whereNotNull('paid_at')
            ->whereNotNull('ready_at');

        $averageSeconds = $this->averagePrepSeconds($prepared);

        return [
            'collected' => (clone $today)->where('status', OrderStatus::Collected->value)->count(),
            // Rejections and expiries together: both mean a customer ordered
            // and did not get it, which is the number worth watching whatever
            // the reason behind it.
            'unfulfilled' => (clone $today)
                ->whereIn('status', [OrderStatus::Rejected->value, OrderStatus::Expired->value])
                ->count(),
            // Null rather than zero when nothing has been made yet — "no data"
            // and "instant" are different answers and the tile says so.
            'avg_prep_minutes' => $averageSeconds === null
                ? null
                : (int) round($averageSeconds / 60),
        ];
    }

    /**
     * Mean seconds between payment and ready, across a prepared-orders query.
     *
     * The arithmetic is done in PHP rather than SQL because the three
     * supported databases spell datetime subtraction three different ways —
     * TIMESTAMPDIFF, EXTRACT(EPOCH FROM …) and strftime('%s', …) — and a
     * cafe's daily order count is far too small for the round trip to matter.
     *
     * @param  Builder<Order>  $prepared
     */
    private function averagePrepSeconds(Builder $prepared): ?float
    {
        $rows = $prepared->get(['paid_at', 'ready_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        return $rows->avg(
            fn (Order $order) => $order->ready_at->getTimestamp() - $order->paid_at->getTimestamp()
        );
    }
}
