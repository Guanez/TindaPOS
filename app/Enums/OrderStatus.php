<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The life of a customer order.
 *
 *   placed ──> paid ──> ready ──> collected
 *     │          └──────────────> collected   (nothing to prepare)
 *     ├──> rejected    (staff refused it, with a reason)
 *     ├──> cancelled   (the customer changed their mind)
 *     └──> expired     (nobody came to the till)
 *
 * Payment is what starts preparation, so `paid` is also the moment the order
 * becomes a sale and stock moves — and the moment cancelling stops being the
 * customer's to do, because by then someone is making it.
 */
enum OrderStatus: string
{
    case Placed = 'placed';
    case Paid = 'paid';
    case Ready = 'ready';
    case Collected = 'collected';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Placed => 'Awaiting payment',
            self::Paid => 'Preparing',
            self::Ready => 'Ready for pickup',
            self::Collected => 'Collected',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    /**
     * Still on the queue screen — someone has work to do.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Placed, self::Paid, self::Ready], true);
    }

    public function isFinished(): bool
    {
        return ! $this->isOpen();
    }

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Placed => [self::Paid, self::Rejected, self::Cancelled, self::Expired],
            self::Paid => [self::Ready, self::Collected],
            self::Ready => [self::Collected],
            self::Collected, self::Rejected, self::Cancelled, self::Expired => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->isOpen())
        );
    }
}
