<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * A shop's logo.
 *
 * Deliberately not ProductImageService with a different size constant. A
 * product photo is cropped square because the grid that shows it is square;
 * a logo is whatever shape the shop's designer made it, and cropping one to
 * a square is how you cut the end off somebody's wordmark.
 *
 * So this scales to fit inside a box and never crops, and it keeps
 * transparency, because a logo lands on the shop's own accent colour and a
 * white rectangle behind it would be obvious.
 */
class StoreLogoService
{
    private const MAX_WIDTH = 480;

    private const MAX_HEIGHT = 200;

    /**
     * Store the logo for a shop and return its path.
     *
     * Named by ULID like every other upload, so a replaced logo cannot be
     * served from a cache under the old URL.
     */
    public function store(Store $store, UploadedFile $file): string
    {
        $path = sprintf('stores/%d/%s.webp', $store->id, (string) Str::ulid());

        $image = (new ImageManager(new Driver))->read($file->getRealPath());

        // scaleDown, not scale: a logo that is already small stays that size
        // rather than being blown up into a blurry version of itself.
        $image->scaleDown(self::MAX_WIDTH, self::MAX_HEIGHT);

        Storage::disk('public')->put($path, (string) $image->toWebp(quality: 90));

        return $path;
    }

    public function delete(Store $store): void
    {
        if ($store->logo_path === null) {
            return;
        }

        Storage::disk('public')->delete($store->logo_path);
    }

    public static function url(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }
}
