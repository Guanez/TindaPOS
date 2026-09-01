<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Support\StoreContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Product photographs: what gets written, where, and in what sizes.
 *
 * Resizing happens here rather than in the browser. The browser can be told
 * to shrink an image and simply not do it — the upload is the client's to
 * shape — so a phone camera's 12MB original would still arrive, and the menu
 * it feeds is opened over mobile data seconds after a QR scan.
 *
 * Every write is scoped to a store directory. A flat folder would work today
 * and make "delete everything belonging to this shop" impossible tomorrow,
 * which is not a thing a multi-tenant product can be missing.
 *
 * The stored value is a base path with no extension and no size. Both are
 * appended when a URL is built, so adding a third derivative later is a
 * change to this class and to nothing in the database.
 */
class ProductImageService
{
    /**
     * Square, because both surfaces that show these are square: the POS grid
     * tile and the customer menu row. One crop policy, no per-surface
     * art direction to get wrong.
     *
     * @var array<string, int>
     */
    public const SIZES = [
        'card' => 480,
        'thumb' => 160,
    ];

    public function __construct(
        private readonly StoreContext $context
    ) {}

    /**
     * Write every derivative and return the base path to store on the model.
     *
     * The original is deliberately not kept. It is up to 8MB of a customer's
     * data allowance that nothing ever serves, and holding personal-camera
     * originals for no reason is a liability rather than an asset.
     */
    public function store(UploadedFile $file): string
    {
        $base = sprintf('products/%d/%s', $this->storeId(), (string) Str::ulid());

        $manager = new ImageManager(new Driver);

        foreach (self::SIZES as $name => $pixels) {
            $image = $manager->read($file->getRealPath());

            // cover() crops to fill rather than letterboxing, so a portrait
            // photo of a cup does not arrive with bars down both sides.
            $image->cover($pixels, $pixels);

            $this->disk()->put("{$base}-{$name}.webp", (string) $image->toWebp(quality: 82));
        }

        return $base;
    }

    /**
     * Swap the photo on a product, and take the old files with it.
     *
     * Replacement is a delete plus a store rather than an overwrite, because
     * the path carries a ULID: a cached or shared URL keeps pointing at the
     * picture it was taken of, and never silently becomes a different drink.
     */
    public function replace(Product $product, UploadedFile $file): string
    {
        $this->delete($product);

        return $this->store($file);
    }

    /**
     * Remove every derivative for a product. Safe to call when it has none.
     */
    public function delete(Product $product): void
    {
        if ($product->image_path === null) {
            return;
        }

        foreach (array_keys(self::SIZES) as $name) {
            $this->disk()->delete("{$product->image_path}-{$name}.webp");
        }
    }

    /**
     * The public URL for one size, or null when the product has no photo.
     */
    public static function url(?string $basePath, string $size = 'card'): ?string
    {
        if ($basePath === null || ! isset(self::SIZES[$size])) {
            return null;
        }

        return Storage::disk('public')->url("{$basePath}-{$size}.webp");
    }

    private function disk(): Filesystem
    {
        return Storage::disk('public');
    }

    /**
     * Uploads belong to the shop that is in context, never to whoever happens
     * to be signed in — a platform admin standing inside a client shop is
     * uploading that shop's photograph, not their own.
     */
    private function storeId(): int
    {
        $id = $this->context->id();

        if ($id === null) {
            throw new \RuntimeException('Cannot store a product image with no store in context.');
        }

        return $id;
    }
}
