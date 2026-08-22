<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sale items already snapshot the product name and prices so history survives
 * later edits; the chosen size and add-ons are part of what was sold and are
 * snapshotted the same way.
 *
 * selling_price keeps its meaning: the unit price actually charged. For a
 * plain product that is still the product's price, so existing rows and the
 * existing checkout path are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->nullOnDelete();
            $table->string('variant_name', 60)->nullable()->after('product_name');
            $table->json('modifiers')->nullable()->after('variant_name');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['product_variant_id', 'variant_name', 'modifiers']);
        });
    }
};
