<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One person may hold two roles that cannot share an account — a department
 * supervisor who is also the MEPEO Representative, say. They get one account
 * per role under the same email and switch between them, so the email is no
 * longer unique; UserController keeps one account per email and role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->unique('email');
        });
    }
};
