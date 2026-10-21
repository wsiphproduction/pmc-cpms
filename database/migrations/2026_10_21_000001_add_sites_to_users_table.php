<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sites a "Division Manager User" signs NTPs for. A division can have more
 * than one such manager, each over different sites; empty means every site in
 * their division, which is how every existing account keeps behaving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('sites')->nullable()->after('division');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sites');
        });
    }
};
