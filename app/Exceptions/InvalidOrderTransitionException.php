<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OrderStatus;
use RuntimeException;

/**
 * An order was asked to do something its current state does not allow —
 * settling one that was already paid, readying one nobody has paid for.
 *
 * Usually a double-tap on the queue screen rather than anything sinister,
 * which is exactly why it must not be allowed to happen twice.
 */
class InvalidOrderTransitionException extends RuntimeException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {
        parent::__construct(
            "This order is already {$from->label()}, so it cannot be marked {$to->label()}."
        );
    }
}
