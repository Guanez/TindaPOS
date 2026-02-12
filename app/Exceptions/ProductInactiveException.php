<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Product;
use RuntimeException;

class ProductInactiveException extends RuntimeException
{
    public function __construct(
        public readonly Product $product,
    ) {
        parent::__construct("Product '{$product->name}' is no longer available.");
    }
}
