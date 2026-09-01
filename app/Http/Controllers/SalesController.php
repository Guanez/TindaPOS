<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\VoidSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService
    ) {}

    /**
     * Sales history page with filters.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Sales/Index', $this->listProps($request));
    }

    /**
     * Sale details — rendered into the same page as the list so that the
     * detail URL works on a direct visit, not only as a modal fetch.
     */
    public function show(Request $request, Sale $sale): Response
    {
        $sale->load(['items', 'user', 'voidedByUser']);

        return Inertia::render('Sales/Index', [
            ...$this->listProps($request),
            'saleDetail' => new SaleResource($sale),
        ]);
    }

    /**
     * Props for the sales list. Closures so Inertia skips the query entirely
     * on partial reloads that only ask for `saleDetail`.
     *
     * @return array<string, \Closure>
     */
    private function listProps(Request $request): array
    {
        return [
            'sales' => fn () => $this->filteredSales($request),
            'filters' => fn () => $request->only(['date_from', 'date_to', 'status', 'payment_method']),
        ];
    }

    /**
     * The filtered sales as a CSV download — managers only.
     *
     * Streamed rather than built in memory: a shop pulling a year of sales
     * for its accountant is the case this exists for, and that is the one
     * case where holding every row at once is how it falls over.
     *
     * The same filters as the screen, so what downloads is what was on it —
     * an export that quietly ignores the date range is worse than none.
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = sprintf('sales-%s.csv', now()->format('Y-m-d'));

        $query = $this->salesQuery($request)->with('user')->latest();

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'wb');

            // Excel reads a UTF-8 file as the system codepage unless it finds
            // a BOM, which turns every ₱ into mojibake on a Philippine desk.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Receipt', 'Date', 'Cashier', 'Status',
                'Items', 'Subtotal', 'Discount', 'Total',
                'Payment method', 'Cash received', 'Change',
            ]);

            // Chunked so the query does not load the whole history either.
            $query->chunk(500, function ($sales) use ($out) {
                foreach ($sales as $sale) {
                    fputcsv($out, [
                        $sale->receipt_number,
                        $sale->created_at?->toDateTimeString(),
                        $sale->user?->name,
                        $sale->status,
                        $sale->item_count,
                        $sale->subtotal,
                        $sale->discount,
                        $sale->total,
                        $sale->payment_method,
                        $sale->cash_received,
                        $sale->change_amount,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Sale>
     */
    private function filteredSales(Request $request)
    {
        return $this->salesQuery($request)
            ->with('user')
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * The filters behind both the screen and the export, in one place so the
     * two cannot drift apart.
     *
     * @return Builder<Sale>
     */
    private function salesQuery(Request $request): Builder
    {
        $query = Sale::query();

        // Date range filter
        if ($from = $request->date('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->date('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Payment method filter
        if ($payment = $request->input('payment_method')) {
            $query->where('payment_method', $payment);
        }

        return $query;
    }

    /**
     * Void a sale — managers only.
     *
     * Re-voiding an already voided sale is rendered by the domain exception
     * handlers registered in bootstrap/app.php.
     */
    public function voidSale(VoidSaleRequest $request, Sale $sale): RedirectResponse
    {
        $this->saleService->voidSale(
            $sale,
            $request->user(),
            $request->validated('reason')
        );

        return redirect()->back()->with('success', 'Sale voided successfully. Stock has been restored.');
    }
}
