<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStoreAddressRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Models\RetiredStoreSlug;
use App\Models\Store;
use App\Services\AccentPalette;
use App\Services\QrCodeService;
use App\Services\StoreLogoService;
use App\Support\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A shop managing itself: its details, whether it takes online orders, and
 * the code customers scan.
 */
class StoreController extends Controller
{
    public function __construct(
        private readonly QrCodeService $qrCodes,
        private readonly StoreContext $context,
        private readonly StoreLogoService $logos,
    ) {}

    public function edit(): Response
    {
        $store = $this->currentStore();

        return Inertia::render('Store/Settings', [
            'store' => [
                ...$store->only([
                    'id', 'name', 'slug', 'type', 'address', 'phone',
                    'receipt_footer', 'currency_symbol', 'online_ordering_enabled',
                    'accent', 'hours', 'prep_minutes',
                ]),
                'logo_url' => StoreLogoService::url($store->logo_path),
                // So the form can show the shop what its own hours currently
                // mean, rather than leaving them to work it out from a grid.
                'is_open' => $store->isOpenNow(),
                'next_opening' => $store->nextOpening(),
                // So the form can say "this colour is too pale to read as
                // text, we will darken it there" before they commit to it,
                // rather than leaving them to discover it on the menu.
                'accent_readable' => AccentPalette::isReadableAsText($store->accent),
            ],
            'orderUrl' => $this->qrCodes->urlFor($store),
            // Generated from our own URL by the QR library, so it is safe to
            // render inline rather than fetched as a second request.
            'qrSvg' => $this->qrCodes->svgFor($store),
            'qrUnreachable' => $this->qrCodes->isUnreachable($store),
        ]);
    }

    public function update(UpdateStoreRequest $request): RedirectResponse
    {
        $store = $this->currentStore();
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $this->logos->delete($store);
            $data['logo_path'] = $this->logos->store($store, $request->file('logo'));
        } elseif ($request->boolean('remove_logo')) {
            $this->logos->delete($store);
            $data['logo_path'] = null;
        }

        $store->update($data);

        return redirect()->route('store.edit')->with('success', 'Store settings saved.');
    }

    /**
     * A print-ready counter card. Deliberately its own page so a cafe can hit
     * Print without the application chrome coming with it.
     */
    public function qr(): Response
    {
        $store = $this->currentStore();

        return Inertia::render('Store/QrCard', [
            'store' => $store->only(['name', 'address']),
            'orderUrl' => $this->qrCodes->urlFor($store),
            'qrSvg' => $this->qrCodes->svgFor($store, 420),
            'qrUnreachable' => $this->qrCodes->isUnreachable($store),
        ]);
    }

    /**
     * Retire the ordering address and take a new one.
     *
     * This is the recovery route for a code that has been misused: every
     * printed card stops working, and honest customers keep ordering from the
     * new one. Kept apart from the ordinary settings save so it cannot happen
     * by accident while someone is editing a phone number.
     */
    public function updateAddress(UpdateStoreAddressRequest $request): RedirectResponse
    {
        $store = $this->currentStore();
        $retired = $store->slug;

        DB::transaction(function () use ($store, $retired, $request): void {
            // Recorded before the change, and never reissued to anyone: a card
            // still on a wall must not start pointing at a different shop.
            RetiredStoreSlug::query()->create([
                'slug' => $retired,
                'store_id' => $store->id,
                'retired_at' => now(),
            ]);

            $store->update(['slug' => $request->validated('slug')]);
        });

        return redirect()->route('store.edit')->with(
            'success',
            "Ordering address changed. Print new counter cards — anything showing /s/{$retired} has stopped working."
        );
    }

    private function currentStore(): Store
    {
        return Store::query()->findOrFail($this->context->id());
    }
}
