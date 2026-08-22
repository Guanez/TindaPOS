<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 60)->unique();
            $table->enum('type', ['sari_sari', 'cafe'])->default('sari_sari');
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('receipt_footer')->nullable();
            $table->string('currency_symbol', 5)->default('P');
            $table->string('qr_token', 40)->nullable()->unique();
            $table->boolean('online_ordering_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Every existing row belongs to the shop this app was set up for, so
        // that shop has to exist before the backfill migration can run.
        DB::table('stores')->insert([
            'name' => 'Main Store',
            'slug' => 'main',
            'type' => 'sari_sari',
            'currency_symbol' => 'P',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
