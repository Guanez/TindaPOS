<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use App\Services\StoreLogoService;
use App\Support\StoreContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| A shop's brand reaches its customers and stops there
|--------------------------------------------------------------------------
| The palette is per shop, and the surface it applies to is the one thing
| worth pinning: a cashier who works two shops must not have the till change
| colour between shifts, because the colour is how they find the button.
*/

beforeEach(function () {
    Storage::fake('public');
});

it('paints the customer menu in the shop colour', function () {
    $store = Store::factory()->withOnlineOrdering()->create(['accent' => '#2F5D50']);
    app(StoreContext::class)->set($store->id);

    $response = $this->get(route('public.menu', $store->slug));

    // 47 93 80 is #2F5D50 as channels — the fill is the colour they picked.
    $response->assertSee('--accent:47 93 80', false);
    $response->assertSee('prefers-color-scheme: dark', false);
    // Doubled selector: app.css sets the same tokens, and whether it lands
    // before or after this block depends on build versus dev server.
    $response->assertSee(':root:root{', false);
});

it('leaves the till alone', function () {
    currentStore()->update(['accent' => '#2F5D50']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('pos.index'))
        ->assertDontSee('--accent:47 93 80', false);
});

it('falls back to the platform look when a shop sets no colour', function () {
    $store = Store::factory()->withOnlineOrdering()->create(['accent' => null]);
    app(StoreContext::class)->set($store->id);

    $this->get(route('public.menu', $store->slug))
        ->assertDontSee('<style>', false);
});

it('shows the shop logo to customers', function () {
    $store = Store::factory()->withOnlineOrdering()->create();
    app(StoreContext::class)->set($store->id);

    $store->update([
        'logo_path' => app(StoreLogoService::class)->store(
            $store,
            UploadedFile::fake()->image('logo.png', 600, 300)
        ),
    ]);

    $this->get(route('public.menu', $store->slug))
        ->assertInertia(fn ($page) => $page
            ->where('store.logo_url', fn ($url) => str_contains((string) $url, '.webp'))
        );
});

it('scales a logo down without cropping it', function () {
    // A wordmark cropped square is a wordmark with the end cut off, which is
    // why this does not reuse the product image service.
    $store = Store::factory()->create();

    $path = app(StoreLogoService::class)->store(
        $store,
        UploadedFile::fake()->image('logo.png', 1200, 300)
    );

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));

    // 1200x300 is 4:1, and 480x120 is the same ratio — nothing was cropped.
    expect($width)->toBe(480)
        ->and($height)->toBe(120);
});

it('refuses a colour that is not a hex', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('store.update'), [
            'name' => currentStore()->name,
            'currency_symbol' => 'P',
            'online_ordering_enabled' => false,
            'accent' => 'javascript:alert(1)',
        ])
        ->assertSessionHasErrors('accent');
});

it('keeps one shop brand out of another', function () {
    $mine = Store::factory()->withOnlineOrdering()->create(['accent' => '#2F5D50']);
    $theirs = Store::factory()->withOnlineOrdering()->create(['accent' => '#D42B1C']);

    app(StoreContext::class)->set($mine->id);

    $this->get(route('public.menu', $mine->slug))
        ->assertSee('--accent:47 93 80', false)
        ->assertDontSee('--accent:212 43 28', false);

    expect($theirs->accent)->toBe('#D42B1C');
});
