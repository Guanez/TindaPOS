<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Store;
use App\Support\StoreContext;
use Illuminate\Database\Seeder;

/**
 * Note: deliberately does NOT use WithoutModelEvents. Tenant models fill
 * store_id from a `creating` hook, and suppressing model events makes every
 * insert here fail the NOT NULL constraint.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // The platform admin belongs to no shop, so it is made before any
        // store is put into context.
        $this->call(PlatformSeeder::class);

        $store = Store::query()->firstOrCreate(
            ['slug' => 'main'],
            [
                'name' => 'TindaPOS Sari-Sari Store',
                'type' => 'sari_sari',
                'address' => 'Brgy. Sample, Quezon City, Metro Manila',
                'phone' => '0917-123-4567',
                'receipt_footer' => 'Salamat po! Please come again.',
                'currency_symbol' => 'P',
            ]
        );

        // Everything seeded below belongs to that store.
        app(StoreContext::class)->runFor($store->id, function (): void {
            $this->call([
                UserSeeder::class,
                CategorySeeder::class,
                ProductSeeder::class,
            ]);
        });

        // A second store, with its own staff, menu and add-ons.
        $this->call(CafeSeeder::class);
    }
}
