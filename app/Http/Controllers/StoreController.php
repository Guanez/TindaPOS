<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStoreRequest;
use App\Models\Store;
use App\Services\QrCodeService;
use App\Support\StoreContext;
use Illuminate\Http\RedirectResponse;
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
    ) {}

    public function edit(): Response
    {
        $store = $this->currentStore();

        return Inertia::render('Store/Settings', [
            'store' => $store->only([
                'id', 'name', 'slug', 'type', 'address', 'phone',
                'receipt_footer', 'currency_symbol', 'online_ordering_enabled',
            ]),
            'orderUrl' => $this->qrCodes->urlFor($store),
            // Generated from our own URL by the QR library, so it is safe to
            // render inline rather than fetched as a second request.
            'qrSvg' => $this->qrCodes->svgFor($store),
            'qrUnreachable' => $this->qrCodes->isUnreachable($store),
        ]);
    }

    public function update(UpdateStoreRequest $request): RedirectResponse
    {
        $this->currentStore()->update($request->validated());

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

    private function currentStore(): Store
    {
        return Store::query()->findOrFail($this->context->id());
    }
}
