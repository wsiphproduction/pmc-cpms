<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a project names to sign each step of its NTPs, where it departs from
 * the default. A step with no row here falls to whoever holds it by default.
 *
 * No foreign key to projects: the live database refuses one (errno 150), so
 * the ids are plain columns, as on approval_steps and roster_breaks. That
 * first attempt also left the bare table behind with this migration
 * unrecorded, so an existing table is completed rather than created.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_ntp_signatories')) {
            Schema::create('project_ntp_signatories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->string('role', 64);                // the approval-chain step
                $table->unsignedBigInteger('user_id');     // who signs it on this project
                $table->timestamps();
            });
        }

        Schema::table('project_ntp_signatories', function (Blueprint $table) {
            if (! Schema::hasIndex('project_ntp_signatories', ['project_id', 'role'], 'unique')) {
                $table->unique(['project_id', 'role']);
            }

            if (! Schema::hasIndex('project_ntp_signatories', ['user_id'])) {
                $table->index('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_ntp_signatories');
    }
};
