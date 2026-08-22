<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 3 of 4. Assigns every pre-tenancy row to the original shop, then refuses
 * to continue if anything was missed — the next migration adds a NOT NULL
 * constraint, and it must not be the thing that discovers a gap.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['users', 'categories', 'products', 'sales', 'stock_logs'];

    public function up(): void
    {
        $storeId = DB::table('stores')->orderBy('id')->value('id');

        if ($storeId === null) {
            throw new RuntimeException('No store exists to backfill into.');
        }

        foreach ($this->tables as $name) {
            DB::table($name)->whereNull('store_id')->update(['store_id' => $storeId]);
        }

        foreach ($this->tables as $name) {
            $remaining = DB::table($name)->whereNull('store_id')->count();

            if ($remaining > 0) {
                throw new RuntimeException("Backfill left {$remaining} row(s) in {$name} without a store_id.");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            DB::table($name)->update(['store_id' => null]);
        }
    }
};
