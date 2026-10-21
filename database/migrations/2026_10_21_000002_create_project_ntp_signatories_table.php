<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a project names to sign each step of its NTPs, where it departs from
 * the default. A step with no row here falls to whoever holds it by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_ntp_signatories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('role', 64);                // the approval-chain step
            $table->unsignedBigInteger('user_id');     // who signs it on this project
            $table->timestamps();

            $table->unique(['project_id', 'role']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_ntp_signatories');
    }
};
