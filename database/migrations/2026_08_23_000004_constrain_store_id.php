<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4 of 4. Only now, with every row carrying a store, does the column
 * become required.
 *
 * users.store_id stays nullable on purpose: a null store means a platform
 * owner — the vendor operating the app across all stores — rather than a
 * missing value.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $required = ['categories', 'products', 'sales', 'stock_logs'];

    public function up(): void
    {
        foreach ($this->required as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('store_id')->nullable(false)->change();
            });
        }

        foreach ([...$this->required, 'users'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->index('store_id', "{$name}_store_id_index");
            });
        }

        // SQLite cannot add a foreign key to an existing table; the global
        // scope and the store relationship enforce this in the application.
        if (DB::getDriverName() !== 'sqlite') {
            foreach ([...$this->required, 'users'] as $name) {
                Schema::table($name, function (Blueprint $table) {
                    $table->foreign('store_id')->references('id')->on('stores')->restrictOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            foreach ([...$this->required, 'users'] as $name) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropForeign(['store_id']);
                });
            }
        }

        foreach ([...$this->required, 'users'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropIndex("{$name}_store_id_index");
            });
        }

        foreach ($this->required as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('store_id')->nullable()->change();
            });
        }
    }
};
