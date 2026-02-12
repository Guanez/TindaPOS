<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InvalidStockException extends RuntimeException
{
    public function __construct(string $message = 'Invalid stock operation.')
    {
        parent::__construct($message);
    }
}
