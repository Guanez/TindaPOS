<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ProductImageService;
use App\Support\StoreContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Product photographs
|--------------------------------------------------------------------------
| The application's first upload path, which makes it the first place a
| request can put a file on the server. Two things are worth pinning hardest:
| that what lands is bounded in size and shape rather than whatever came off
| a phone camera, and that it lands inside the shop that uploaded it.
*/

beforeEach(function () {
    Storage::fake('public');
});

function photo(int $width = 900, int $height = 900): UploadedFile
{
    return UploadedFile::fake()->image('drink.jpg', $width, $height);
}

it('writes every derivative and stores only the base path', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('inventory.store'), [
        'category_id' => Category::factory()->create()->id,
        'name' => 'Spanish Latte',
        'sku' => 'SL-001',
        'cost_price' => 60,
        'selling_price' => 145,
        'stock_quantity' => 0,
        'image' => photo(),
    ])->assertRedirect();

    $product = Product::query()->where('sku', 'SL-001')->firstOrFail();

    // A path, not a URL and not a filename with an extension.
    expect($product->image_path)->not->toBeNull()
        ->and($product->image_path)->not->toContain('.webp')
        ->and($product->image_path)->not->toContain('http');

    foreach (array_keys(ProductImageService::SIZES) as $size) {
        Storage::disk('public')->assertExists("{$product->image_path}-{$size}.webp");
    }
});

it('shrinks what it stores rather than keeping the camera original', function () {
    $service = app(ProductImageService::class);

    $base = $service->store(photo(2400, 1600));

    foreach (ProductImageService::SIZES as $size => $pixels) {
        $raw = Storage::disk('public')->get("{$base}-{$size}.webp");
        [$width, $height] = getimagesizefromstring($raw);

        expect($width)->toBe($pixels)
            ->and($height)->toBe($pixels);
    }
});

it('files an upload under the shop that made it', function () {
    $store = Store::factory()->create();
    app(StoreContext::class)->set($store->id);

    $base = app(ProductImageService::class)->store(photo());

    expect($base)->toStartWith("products/{$store->id}/");
});

it('refuses a file that is not an image', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('inventory.store'), [
        'category_id' => Category::factory()->create()->id,
        'name' => 'Payload',
        'sku' => 'PL-001',
        'cost_price' => 1,
        'selling_price' => 2,
        'stock_quantity' => 0,
        'image' => UploadedFile::fake()->create('shell.php', 40, 'application/x-php'),
    ])->assertSessionHasErrors('image');

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('takes the old files with it when a photo is replaced', function () {
    $service = app(ProductImageService::class);

    $product = Product::factory()->create(['image_path' => $service->store(photo())]);
    $old = $product->image_path;

    $new = $service->replace($product, photo());

    expect($new)->not->toBe($old);
    Storage::disk('public')->assertMissing("{$old}-card.webp");
    Storage::disk('public')->assertExists("{$new}-card.webp");
});

it('keeps the photo when a product is deactivated', function () {
    // Deactivation is a soft delete that exists to preserve sales history,
    // and a voided sale's receipt should still be able to show the item.
    $admin = User::factory()->admin()->create();
    $service = app(ProductImageService::class);

    $product = Product::factory()->create(['image_path' => $service->store(photo())]);

    $this->actingAs($admin)
        ->delete(route('inventory.destroy', $product))
        ->assertRedirect();

    Storage::disk('public')->assertExists("{$product->image_path}-card.webp");
});

it('offers the customer a url and never the path', function () {
    $store = Store::factory()->withOnlineOrdering()->create();
    app(StoreContext::class)->set($store->id);

    $product = Product::factory()->create([
        'is_active' => true,
        'is_available' => true,
        'image_path' => app(ProductImageService::class)->store(photo()),
    ]);

    $this->get(route('public.menu', $store->slug))
        ->assertInertia(fn ($page) => $page
            ->where('products.data.0.image_url', fn ($url) => str_contains((string) $url, '.webp'))
            ->missing('products.data.0.image_path')
        );

    expect($product->fresh()->image_path)->not->toBeNull();
});
