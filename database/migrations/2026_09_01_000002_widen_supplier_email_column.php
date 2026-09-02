<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppliers often quote through several mailboxes (sales, admin, the owner), so
 * the field holds a comma-separated list rather than one address. 191 chars ran
 * out after two or three of them; a text column takes however many a supplier
 * actually has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->longText('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('email', 191)->nullable()->change();
        });
    }
};
