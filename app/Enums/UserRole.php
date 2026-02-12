<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Cashier = 'cashier';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Cashier => 'Cashier',
        };
    }

    /**
     * Check if role has management privileges
     */
    public function isManager(): bool
    {
        return in_array($this, [self::Owner, self::Admin]);
    }
}
