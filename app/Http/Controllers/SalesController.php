<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\VoidSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Sale>
     */
    private function filteredSales(Request $request)
    {
        $query = Sale::with('user');

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

        return $query->latest()->paginate(15)->withQueryString();
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
