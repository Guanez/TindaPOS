<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Product;
use RuntimeException;

/**
 * The product is still on the menu, but the shop has switched it off for now —
 * distinct from being deactivated outright.
 */
class ProductUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly Product $product,
    ) {
        parent::__construct("'{$product->name}' is sold out right now.");
    }
}
