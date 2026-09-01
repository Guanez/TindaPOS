<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\PublicMenuResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Services\AccentPalette;
use App\Services\OrderService;
use App\Services\StoreLogoService;
use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's side of the counter: scan, order, watch for it to be ready.
 *
 * Nothing here trusts the client with anything that costs money. The basket
 * arrives as ids and quantities; every peso is recomputed by the pricer from
 * the store's own menu.
 */
class PublicOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly StoreContext $context,
    ) {}

    /**
     * The QR target: this store's menu.
     */
    public function menu(Request $request): Response
    {
        $store = $this->store($request);

        $products = Product::with(['variants', 'modifierGroups.modifiers'])
            ->sellable()
            ->orderBy('name')
            ->get();

        return Inertia::render('Public/Menu', [
            'store' => $this->storePayload($store),
            'categories' => Category::active()
                ->whereIn('id', $products->pluck('category_id')->unique())
                ->orderBy('name')
                ->get(['id', 'name']),
            'products' => PublicMenuResource::collection($products),
        ]);
    }

    /**
     * Place the order. It arrives unpaid — the customer pays at the counter,
     * which is what starts preparation.
     */
    public function place(PlaceOrderRequest $request): RedirectResponse
    {
        $store = $this->store($request);

        $order = $this->orderService->place($store, $request->validated());

        return redirect()->route('public.status', $order->token);
    }

    /**
     * The live status page, addressed by an unguessable token.
     *
     * The lookup is deliberately unscoped: the token is the authorisation, and
     * there is no signed-in user to scope by. The store context is then set
     * from the order so everything loaded afterwards stays inside it.
     */
    public function status(string $token): Response
    {
        $order = $this->findByToken($token);

        /** @var Store $store */
        $store = $order->store;

        return Inertia::render('Public/Status', [
            'order' => fn () => $this->orderPayload($order->fresh(['items.modifiers'])),
            'store' => $this->storePayload($store),
        ]);
    }

    private function findByToken(string $token): Order
    {
        $order = Order::withoutGlobalScopes()
            ->with(['items.modifiers', 'store'])
            ->where('token', $token)
            ->firstOrFail();

        $this->context->set($order->store_id);

        return $order;
    }

    private function store(Request $request): Store
    {
        /** @var Store $store */
        $store = $request->attributes->get('publicStore');

        return $store;
    }

    /**
     * @return array<string, mixed>
     */
    private function storePayload(Store $store): array
    {
        return [
            'name' => $store->name,
            'slug' => $store->slug,
            'address' => $store->address,
            'currency_symbol' => $store->currency_symbol,
            'receipt_footer' => $store->receipt_footer,
            'logo_url' => StoreLogoService::url($store->logo_path),
            // The menu stays readable when the shop is shut — a customer
            // deciding what to get tomorrow is a customer worth keeping — so
            // this says what to do about it rather than hiding the page.
            'is_open' => $store->isOpenNow(),
            'next_opening' => $store->nextOpening(),
            // The whole palette, derived from the one colour the shop chose
            // and emitted as token overrides. Only ever on these pages: a
            // cashier working two shops should not have the till change
            // colour between shifts.
            'brand_css' => AccentPalette::css($store->accent),
        ];
    }

    /**
     * What the customer may see about their own order. No cost prices, no
     * cashier names, no internal ids beyond the token they already hold.
     *
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order): array
    {
        /** @var Collection<int, OrderItem> $items */
        $items = $order->items;

        return [
            'token' => $order->token,
            'queue_number' => $order->queue_number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'customer_name' => $order->customer_name,
            'note' => $order->note,
            'total' => $order->total,
            'item_count' => $order->item_count,
            'placed_at' => $order->placed_at,
            'reject_reason' => $order->reject_reason,
            'items' => $items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'modifiers' => $item->modifiers->pluck('name'),
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
            ]),
        ];
    }
}
