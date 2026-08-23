<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TindaPOS is run for other people's shops, so somebody has to sit above all
 * of them: create a client's store, suspend it when they stop paying, and
 * step into it to help when they call.
 *
 * That person is a `super_admin` with a null store_id. Both halves matter.
 * The null store is what unscopes their queries (see StoreScope), but a null
 * store on its own is not authority — an ordinary owner whose store_id got
 * nulled by a bug would otherwise silently become a landlord. The role is
 * the claim; the null store is the mechanism.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'owner', 'admin', 'cashier'])
                ->default('cashier')
                ->change();
        });
    }

    public function down(): void
    {
        // Demote any platform accounts first — the narrowed constraint would
        // reject them, and there is no sensible shop to hand them to.
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['owner', 'admin', 'cashier'])
                ->default('cashier')
                ->change();
        });
    }
};
