<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A manager's roster break, and who signs for them while they are away.
        // The break names the role being covered rather than assuming it from
        // the manager's account, so the OIC's cover is exact even if the
        // manager's role changes hands mid-break.
        Schema::create('roster_breaks', function (Blueprint $table) {
            $table->id();
            // Plain nullable-free columns without FKs, matching approval_steps —
            // SQL Server rejects the extra cascade paths.
            $table->unsignedBigInteger('user_id');     // the manager going on break
            $table->unsignedBigInteger('oic_user_id'); // who covers for them
            $table->string('role', 60);                // the Spatie role the OIC covers
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['oic_user_id', 'role', 'starts_on', 'ends_on'], 'roster_breaks_cover_index');
            $table->index(['user_id', 'starts_on'], 'roster_breaks_user_index');
        });

        // A step settled by an OIC remembers whom they signed for, so the chain
        // reads honestly afterwards: the signature is the OIC's, on behalf of
        // the office that was away.
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->unsignedBigInteger('on_behalf_of_user_id')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn('on_behalf_of_user_id');
        });

        Schema::dropIfExists('roster_breaks');
    }
};
