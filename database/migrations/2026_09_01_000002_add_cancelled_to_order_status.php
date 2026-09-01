<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Room for `cancelled` in the order status column.
 *
 * The column is an enum, which on every supported driver becomes a CHECK
 * constraint listing the allowed values — so adding a case to the PHP enum is
 * only half the change. Without this the customer's cancel button raises an
 * integrity constraint violation at the moment it is pressed, which is a
 * failure with no symptom until someone presses it.
 *
 * The list is restated in full rather than appended to, because that is what
 * a constraint rebuild is: the new set replaces the old one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'placed', 'paid', 'ready', 'collected', 'rejected', 'cancelled', 'expired',
            ])->default('placed')->change();
        });
    }

    public function down(): void
    {
        // Anything already cancelled has to go somewhere the old constraint
        // accepts, or the column cannot be narrowed back. Expired is the
        // closest meaning: an order that ended without being paid for.
        DB::table('orders')->where('status', 'cancelled')->update(['status' => 'expired']);

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'placed', 'paid', 'ready', 'collected', 'rejected', 'expired',
            ])->default('placed')->change();
        });
    }
};
