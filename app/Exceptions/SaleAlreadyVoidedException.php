<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Sale;
use RuntimeException;

class SaleAlreadyVoidedException extends RuntimeException
{
    public function __construct(
        public readonly Sale $sale,
    ) {
        parent::__construct("Sale #{$sale->receipt_number} has already been voided.");
    }
}
