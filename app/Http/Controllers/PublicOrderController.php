<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
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

        // Everything on the menu, not everything orderable — a sold-out item
        // is shown and marked, because a gap where a customer's usual should
        // be reads as "they stopped making it".
        $products = Product::with(['variants', 'modifierGroups.modifiers'])
            ->onMenu()
            ->orderBy('name')
            ->get();

        return Inertia::render('Public/Menu', [
            'store' => $this->storePayload($store),
            'categories' => Category::active()
                ->whereIn('id', $products->pluck('category_id')->unique())
                ->orderBy('name')
                ->get(['id', 'name']),
            'products' => PublicMenuResource::collection($products),
        ])->withViewData(['og' => $this->openGraph($store)]);
    }

    /**
     * The link preview for this shop's menu.
     *
     * Passed to the Blade layout rather than set from Vue: the crawlers that
     * build preview cards do not run JavaScript, and pasting the menu link
     * into a Facebook post is the most likely way this ever gets shared.
     *
     * @return array<string, string|null>
     */
    private function openGraph(Store $store): array
    {
        $description = $store->address !== null
            ? "Order ahead from {$store->name} on {$store->address}. Pay at the counter and we'll tell you when it's ready."
            : "Order ahead from {$store->name}. Pay at the counter and we'll tell you when it's ready.";

        return [
            'title' => "Order from {$store->name}",
            'description' => $description,
            'url' => route('public.menu', $store->slug),
            // The shop's own logo, so the card carries the cafe rather than
            // this application. Absent is handled by the layout.
            'image' => StoreLogoService::url($store->logo_path),
        ];
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

    /**
     * The customer changing their mind, which is only theirs to do until they
     * pay. OrderService holds that rule; a cancel that arrives after the
     * cashier has settled raises InvalidOrderTransitionException, which the
     * global handler turns into an error on the page they are already looking
     * at — so they find out the order is being made rather than watching a
     * paid order disappear.
     */
    public function cancel(string $token): RedirectResponse
    {
        $order = $this->findByToken($token);

        $this->orderService->cancel($order);

        return redirect()->route('public.status', $token);
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
            // Shown on the menu as "usually ready in about N minutes", which
            // is the question a customer has before they order rather than
            // after. Null for a shop that has not said, and then nothing is
            // claimed on its behalf.
            'prep_minutes' => $store->prep_minutes,
            // The whole palette, derived from the one colour the shop chose
            // and emitted as token overrides. Only ever on these pages: a
            // cashier working two shops should not have the till change
            // colour between shifts.
            'brand_css' => AccentPalette::css($store->accent),
        ];
    }

    /**
     * How much longer, roughly — or null when there is nothing honest to say.
     *
     * Only while it is being made: before payment nothing has started, and
     * once it is ready the number is no longer a question. Floors at zero
     * rather than going negative, because "ready in -3 minutes" is worse than
     * saying nothing, and the page reads 0 as "any moment now".
     */
    private function readyInMinutes(Order $order): ?int
    {
        $prep = $order->store?->prep_minutes;

        if ($order->status !== OrderStatus::Paid || $prep === null || $order->paid_at === null) {
            return null;
        }

        // Rounded UP from seconds. Working in whole minutes would report an
        // eight-minute wait as seven the instant it was paid for, because a
        // fraction of a minute has already elapsed by then.
        $remaining = ($prep * 60) - $order->paid_at->diffInSeconds(now());

        return (int) max(0, ceil($remaining / 60));
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
            // Minutes left, computed here rather than sent as a timestamp for
            // the phone to subtract from. A customer's clock can be minutes
            // out and they would never know it; the page already re-asks
            // every five seconds, so the server's answer is always fresh.
            'ready_in_minutes' => $this->readyInMinutes($order),
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
