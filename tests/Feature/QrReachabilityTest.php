<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\URL;

/**
 * A QR code built while the app is being viewed at localhost renders, prints
 * and scans perfectly — it just resolves to nothing on a customer's phone.
 * There is no symptom until someone is standing at a counter, so the screens
 * that offer to print one say so.
 *
 * Note what this checks. route() builds from the HOST OF THE CURRENT REQUEST,
 * not from APP_URL — APP_URL only governs URLs made outside a request, in a
 * console command or a queued job. So the danger is not "APP_URL was never
 * changed" but the narrower and likelier "whoever printed this was looking at
 * the app on the machine it runs on". Inspecting the URL actually encoded
 * catches both, and keeps working if the app is reached over a tunnel or an
 * IP that a phone on the same network genuinely can resolve.
 */
it('flags an address that only resolves on the machine running the app', function (string $root) {
    URL::forceRootUrl($root);

    expect(app(QrCodeService::class)->isUnreachable(currentStore()))->toBeTrue();
})->with([
    'http://localhost',
    'http://localhost:8000',
    'http://127.0.0.1',
    'http://127.0.0.1:8000',
    'http://0.0.0.0',
    'http://tindapos.test',
    'http://shop.localhost',
]);

it('accepts an address a phone can actually reach', function (string $root) {
    URL::forceRootUrl($root);

    expect(app(QrCodeService::class)->isUnreachable(currentStore()))->toBeFalse();
})->with([
    'https://tindapos.ph',
    'https://order.kapelokal.com.ph',
    // A LAN address is fine: a phone on the same wifi resolves it, which is
    // exactly how a cafe would test the code before printing it.
    'http://192.168.1.20:8000',
    'http://203.0.113.10',
]);

it('warns on the settings screen while the address is local', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get('http://localhost/store/settings')
        ->assertInertia(fn ($page) => $page
            ->where('qrUnreachable', true)
            ->where('orderUrl', fn (string $url) => str_starts_with($url, 'http://localhost/s/'))
        );
});

it('warns on the printable card too, which is the one that reaches paper', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get('http://localhost/store/qr')
        ->assertInertia(fn ($page) => $page->where('qrUnreachable', true));
});

it('stops warning when the shop is viewed at a real address', function () {
    // The same store, same code — only the host the app was reached on
    // differs, which is the whole point.
    $this->actingAs(User::factory()->owner()->create())
        ->get('https://tindapos.ph/store/qr')
        ->assertInertia(fn ($page) => $page
            ->where('qrUnreachable', false)
            ->where('orderUrl', fn (string $url) => str_starts_with($url, 'https://tindapos.ph/s/'))
        );
});

it('still encodes the scannable URL into the SVG', function () {
    $svg = app(QrCodeService::class)->svgFor(currentStore());

    expect($svg)->toContain('<svg')
        ->and($svg)->toContain('viewBox');
});
