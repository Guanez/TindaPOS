<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\RejectOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    /**
     * The queue screen. Polled every few seconds, so the payload stays small
     * and the orders prop is reloaded on its own.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Orders/Index', [
            'orders' => fn () => Order::with(['items.modifiers', 'cashier'])
                ->open()
                ->today()
                ->orderBy('queue_number')
                ->get(),
            'recentlyFinished' => fn () => Order::query()
                ->today()
                ->whereIn('status', [
                    OrderStatus::Collected->value,
                    OrderStatus::Rejected->value,
                    OrderStatus::Expired->value,
                ])
                ->latest('updated_at')
                ->take(8)
                ->get(),
        ]);
    }

    /**
     * Take payment. Domain exceptions are rendered by the handlers in
     * bootstrap/app.php.
     */
    public function settle(Request $request, Order $order): RedirectResponse
    {
        $payment = $request->validate([
            'payment_method' => ['required', 'in:cash,gcash,maya,card,other'],
            'cash_received' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $this->orderService->settle($order, $request->user(), $payment);

        return redirect()->back()->with('success', "Order #{$order->queue_number} paid.");
    }

    public function reject(RejectOrderRequest $request, Order $order): RedirectResponse
    {
        $this->orderService->reject($order, $request->user(), $request->validated('reason'));

        return redirect()->back()->with('success', "Order #{$order->queue_number} rejected.");
    }

    public function ready(Order $order): RedirectResponse
    {
        $this->orderService->markReady($order);

        return redirect()->back()->with('success', "Order #{$order->queue_number} is ready.");
    }

    public function collect(Order $order): RedirectResponse
    {
        $this->orderService->markCollected($order);

        return redirect()->back()->with('success', "Order #{$order->queue_number} collected.");
    }
}
