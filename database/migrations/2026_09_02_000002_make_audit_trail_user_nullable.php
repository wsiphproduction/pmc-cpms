<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Not every audited action has a signed-in user behind it any more: a supplier
 * filling in a quotation through the portal holds only an RFQ token, and their
 * submissions belong in the trail just as much as staff actions do.
 *
 * The trail already renders a missing user as "System", and supplier entries
 * name the company in the action text, so nothing is lost by allowing null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->integer('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->integer('user_id')->nullable(false)->change();
        });
    }
};
