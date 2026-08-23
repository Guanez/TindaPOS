<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A photo of the thing being sold.
 *
 * Nullable with no backfill, so every existing product keeps working and the
 * feature appears one product at a time as a shop gets round to it.
 *
 * What is stored is the base path of an upload — `products/7/01J....` — not a
 * URL and not a filename. The service derives each size from it, which is
 * what lets a new derivative be added later without touching a single row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
