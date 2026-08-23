<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\StoreContext;
use Illuminate\Database\Seeder;

/**
 * The account that runs TindaPOS itself, as opposed to any shop on it.
 *
 * Seeded with no store in context on purpose: BelongsToStore fills store_id
 * from whatever is current and cannot tell "not given" from "given as null",
 * so the only way to genuinely create a storeless user is to have no store
 * set while doing it.
 */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        app(StoreContext::class)->runForNone(function (): void {
            $admin = User::query()->firstOrCreate(
                ['username' => 'superadmin'],
                [
                    'name' => 'Platform Admin',
                    // Not admin@ — the demo sari-sari store already seeds a
                    // shop admin at that address, and emails are unique.
                    'email' => 'platform@tindapos.ph',
                    'password' => 'super123',
                    'role' => UserRole::SuperAdmin,
                ]
            );

            // firstOrCreate on an existing row leaves store_id alone, and a
            // super admin pinned to a shop is powerless by design — so it is
            // asserted rather than assumed.
            if ($admin->store_id !== null) {
                $admin->update(['store_id' => null]);
            }
        });
    }
}
