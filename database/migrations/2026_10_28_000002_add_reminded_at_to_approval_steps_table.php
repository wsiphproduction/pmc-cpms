<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the signers of a step were last reminded that it is still waiting on
 * them (see `ntps:remind-stalled`). Held on the step, so the weekly count
 * starts over once the chain moves on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('acted_at');
        });
    }

    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
