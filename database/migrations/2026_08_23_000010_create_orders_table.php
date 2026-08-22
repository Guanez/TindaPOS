<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's request, which is a different thing from a sale.
 *
 * A sale is money that changed hands; an order exists before payment, can be
 * refused, and moves through a lifecycle. Keeping them apart is what lets all
 * the existing reporting, stock and void behaviour carry on untouched — the
 * order simply produces a sale when the till settles it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();

            // Queue numbers restart each day, so the date is part of the key.
            $table->date('queue_date');
            $table->unsignedInteger('queue_number');

            // What the customer's status page is addressed by: unguessable,
            // never a sequential id.
            $table->string('token', 40)->unique();

            $table->string('customer_name', 60)->nullable();
            $table->string('note', 255)->nullable();

            $table->enum('status', [
                'placed', 'paid', 'ready', 'collected', 'rejected', 'expired',
            ])->default('placed');

            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->unsignedInteger('item_count');

            // Set the moment the order is settled at the till.
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('placed_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('collected_at')->nullable();

            $table->string('reject_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['store_id', 'queue_date', 'queue_number'], 'orders_store_queue_unique');
            $table->index(['store_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            // Snapshots, for the same reason sale items carry them.
            $table->string('product_name');
            $table->string('variant_name', 60)->nullable();

            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->timestamps();

            $table->index('order_id');
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 60);
            $table->decimal('price_delta', 10, 2)->default(0);

            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
