<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop columns we don't need
            $table->dropColumn(['email_verified_at']);

            // Add TindaPOS columns
            $table->string('username', 50)->unique()->after('name');
            $table->enum('role', ['owner', 'admin', 'cashier'])->default('cashier')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role', 'is_active']);
            $table->timestamp('email_verified_at')->nullable();
        });
    }
};
