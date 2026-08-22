<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Both default to the behaviour products already have, so nothing in the
 * sari-sari catalogue changes.
 *
 * track_stock exists because a cafe does not count lattes — it counts beans
 * and milk, neither of which is the thing being sold. is_available is the
 * one-tap "we've run out" switch for a shop that is mid-service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_stock')->default(true)->after('stock_quantity');
            $table->boolean('is_available')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['track_stock', 'is_available']);
        });
    }
};
