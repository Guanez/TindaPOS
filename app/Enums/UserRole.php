<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    /**
     * Runs the platform, not a shop. Sits outside tenancy entirely — see
     * User::isSuperAdmin() for why the role alone is not enough.
     */
    case SuperAdmin = 'super_admin';

    case Owner = 'owner';
    case Admin = 'admin';
    case Cashier = 'cashier';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Platform Admin',
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
        return in_array($this, [self::SuperAdmin, self::Owner, self::Admin]);
    }

    /**
     * The roles a store may hand out to its own staff.
     *
     * SuperAdmin is deliberately absent: a shop cannot mint a landlord. Both
     * staff form requests validate against this list.
     *
     * @return array<int, string>
     */
    public static function assignableByStore(): array
    {
        return [
            self::Owner->value,
            self::Admin->value,
            self::Cashier->value,
        ];
    }
}
