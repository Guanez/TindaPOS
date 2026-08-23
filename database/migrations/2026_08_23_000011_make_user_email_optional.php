<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-in is by username, so an email is genuinely optional here — a cafe
 * hiring a part-time barista should not have to invent one.
 *
 * The User model has always documented this column as `string|null`; only the
 * schema disagreed, which surfaced the moment there was a form to create staff
 * from. The unique index stays: NULLs do not collide in either MySQL or
 * SQLite, so any number of accounts may go without an address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
