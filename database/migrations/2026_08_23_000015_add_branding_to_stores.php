<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What makes a shop's customer menu look like theirs rather than ours.
 *
 * Both nullable, so a shop that sets neither keeps the platform's own look —
 * which is a deliberate default and not a blank page.
 *
 * Only the accent is stored, never the palette derived from it. AccentPalette
 * computes the tint, the hairline, the readable text variant and the colour
 * of labels sitting on top; caching those in columns would freeze today's
 * contrast rules into every existing row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('accent', 7)->nullable()->after('currency_symbol');
            $table->string('logo_path')->nullable()->after('accent');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['accent', 'logo_path']);
        });
    }
};
