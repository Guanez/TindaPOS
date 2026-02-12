<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\SaleAlreadyVoidedException;
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

        $sales = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => $request->only(['date_from', 'date_to', 'status', 'payment_method']),
        ]);
    }

    /**
     * Get sale details (for modal).
     */
    public function show(Sale $sale): Response
    {
        $sale->load(['items', 'user', 'voidedByUser']);

        return Inertia::render('Sales/Index', [
            'saleDetail' => new SaleResource($sale),
        ]);
    }

    /**
     * Void a sale — managers only.
     */
    public function voidSale(VoidSaleRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->saleService->voidSale(
                $sale,
                $request->user(),
                $request->validated('reason')
            );

            return redirect()->back()->with('success', 'Sale voided successfully. Stock has been restored.');
        } catch (SaleAlreadyVoidedException $e) {
            return redirect()->back()->withErrors(['void' => $e->getMessage()]);
        }
    }
}
