<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a shop is open, so the menu can say so.
 *
 * Until now a customer could scan a card at two in the morning and place an
 * order that nobody would see, which then sat until `orders:expire` killed it
 * twenty minutes later. They were told nothing at any point in that.
 *
 * One JSON column rather than a table of seven rows: hours are always read as
 * a whole week, never queried across stores, and never joined. A shape that
 * is only ever loaded with its parent does not need a table of its own.
 *
 * Nullable, and null means "no hours set — always open", which is exactly how
 * every existing shop behaves today. Adding this changes nothing until a shop
 * fills it in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->json('hours')->nullable()->after('online_ordering_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('hours');
        });
    }
};
