<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Store;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * The code a customer scans at the counter.
 *
 * SVG rather than PNG: it needs no image extension on the server and stays
 * sharp at whatever size a cafe prints it, from a table tent to an A4 poster.
 *
 * High error correction because this ends up on a sticker near a coffee
 * machine — it has to survive a splash and a thumbprint.
 */
class QrCodeService
{
    public function urlFor(Store $store): string
    {
        return route('public.menu', $store->slug);
    }

    /**
     * Whether the generated code points somewhere only this machine can reach.
     *
     * route() builds from APP_URL, which is http://localhost in development.
     * A code built there encodes an address no phone can resolve — and it
     * fails silently: the SVG renders, prints, and looks perfectly correct
     * right up until a customer is standing at the counter with a dead scan.
     * Cheap to detect, so the screens that show a code say so.
     */
    public function isUnreachable(Store $store): bool
    {
        $host = parse_url($this->urlFor($store), PHP_URL_HOST);

        if (! is_string($host)) {
            return true;
        }

        return in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
    }

    public function svgFor(Store $store, int $size = 320): string
    {
        return (new Builder(
            writer: new SvgWriter,
            data: $this->urlFor($store),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 8,
        ))->build()->getString();
    }
}
