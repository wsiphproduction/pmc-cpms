<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project cost now carries approved variation orders on top of the contracted
 * figure, so the two have to be stored apart: budget_base is what the project
 * form captures, budget_total is that plus the approved variations. Keeping
 * only the total would make the sum unrepeatable — re-saving the form or
 * re-approving a variation would fold the additions in again.
 *
 * Existing projects have never had variations counted, so their current total
 * is their base — and any variation already sitting at "approved" is then
 * added on, which is the whole point of the change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('budget_base', 15, 2)->default(0)->after('budget_total');
        });

        DB::statement('UPDATE projects SET budget_base = budget_total');

        // Apply variations that were already approved before this change.
        DB::table('projects')->whereNull('deleted_at')->orderBy('id')->pluck('id')
            ->each(function ($projectId) {
                $approved = DB::table('project_variation_orders')
                    ->where('project_id', $projectId)
                    ->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->sum('amount') ?: 0;

                if ($approved > 0) {
                    DB::table('projects')->where('id', $projectId)
                        ->update(['budget_total' => DB::raw("budget_base + {$approved}")]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('budget_base');
        });
    }
};
