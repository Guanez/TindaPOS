<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a shop retire its ordering address and take a new one.
 *
 * The slug was immutable because it is printed on counter cards, which made a
 * misused code impossible to kill: the only lever was switching ordering off
 * entirely, stopping honest customers along with the nuisance. Changing the
 * address is now a deliberate, warned action instead of a forbidden one.
 *
 * A retired slug is never handed out again. Without this table, a shop that
 * rotates away from "kape-lokal" leaves that name free for the next shop to
 * take — and every poster still on a wall out there would then point
 * customers at somebody else's menu. That is worse than the abuse being
 * fixed, so retired names are kept permanently and blocked from reuse.
 *
 * stores.qr_token goes at the same time. It was written by the migration, the
 * factory and a seeder and read by nothing at all; the rotation it was added
 * for is what this migration actually delivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retired_store_slugs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->timestamp('retired_at');

            $table->index('store_id');
        });

        // The unique index has to go first. SQLite refuses to drop a column
        // that is still indexed, and it fails mid-migration rather than up
        // front — so this is two statements on purpose, not tidiness.
        Schema::table('stores', function (Blueprint $table) {
            $table->dropUnique('stores_qr_token_unique');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('qr_token', 40)->nullable()->unique();
        });

        Schema::dropIfExists('retired_store_slugs');
    }
};
