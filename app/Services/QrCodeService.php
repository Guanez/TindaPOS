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
