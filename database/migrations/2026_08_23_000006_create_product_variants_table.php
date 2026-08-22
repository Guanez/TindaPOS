<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A size or format of a product: 12oz / 16oz, regular / large.
 *
 * A product with no variants is priced by its own selling_price, which is how
 * every existing sari-sari product keeps working untouched.
 *
 * store_id is carried here rather than reached through the product because
 * customers will post variant ids from a public page: a scoped lookup makes a
 * reference to another store's variant simply not resolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('selling_price', 10, 2);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('store_id');
            $table->index(['product_id', 'sort_order']);
            $table->unique(['product_id', 'name'], 'product_variants_product_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
