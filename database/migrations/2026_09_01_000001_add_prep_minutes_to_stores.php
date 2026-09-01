<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roughly how long this shop takes to make an order.
 *
 * The status page currently tells a paid customer "hang tight", which is the
 * one thing a person standing in a cafe already knows. The question they
 * actually have is whether they have time to sit down, and a shop knows the
 * answer to within a few minutes.
 *
 * Deliberately one number for the whole shop rather than per product. A
 * per-item prep time implies the kitchen works serially and that the estimate
 * is a promise; a single "usually about eight minutes" is the honest shape of
 * what a barista would say out loud, and it is a setting a shop will actually
 * fill in.
 *
 * Nullable, and null means the estimate is simply not shown — which is how
 * every existing shop behaves today. Adding this changes nothing until a shop
 * fills it in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedSmallInteger('prep_minutes')->nullable()->after('hours');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('prep_minutes');
        });
    }
};
