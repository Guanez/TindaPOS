<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These columns were unique across the whole table, which was correct when one
 * shop owned the database. With several stores sharing it, that would let only
 * one store in the entire system own the category name "Beverages" or the SKU
 * "COFFEE-01". Uniqueness belongs per store instead.
 *
 * users.username stays globally unique on purpose: sign-in asks for a username
 * and password with no store selector, so two stores both having a "cashier"
 * would make login ambiguous.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_name_unique');
            $table->unique(['store_id', 'name'], 'categories_store_name_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_sku_unique');
            $table->dropUnique('products_barcode_unique');
            $table->unique(['store_id', 'sku'], 'products_store_sku_unique');
            $table->unique(['store_id', 'barcode'], 'products_store_barcode_unique');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_store_name_unique');
            $table->unique('name', 'categories_name_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_store_sku_unique');
            $table->dropUnique('products_store_barcode_unique');
            $table->unique('sku', 'products_sku_unique');
            $table->unique('barcode', 'products_barcode_unique');
        });
    }
};
