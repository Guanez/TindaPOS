<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 2 of 4. The column arrives nullable and unconstrained so that adding it
 * cannot fail on existing rows; the backfill and the constraint follow.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['users', 'categories', 'products', 'sales', 'stock_logs'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('store_id')->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('store_id');
            });
        }
    }
};
