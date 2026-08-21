<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A parent's budget_paid used to count only its own approved billings, while
 * its hub listed its sub-projects' billings too — so the payment figure
 * under-reported against a budget_total that already includes the NTPs those
 * sub-projects were spawned from. The roll-up now happens whenever a billing
 * changes; this backfills the parents that have not been touched since.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('projects')->whereNull('parent_id')->whereNull('deleted_at')
            ->orderBy('id')->pluck('id')
            ->each(function ($parentId) {
                $ids = DB::table('projects')
                    ->where('parent_id', $parentId)->whereNull('deleted_at')
                    ->pluck('id')->push($parentId);

                DB::table('projects')->where('id', $parentId)->update([
                    'budget_paid' => DB::table('project_billings')
                        ->whereIn('project_id', $ids)
                        ->where('status', 'approved')
                        ->whereNull('deleted_at')
                        ->sum('amount') ?: 0,
                ]);
            });
    }

    public function down(): void
    {
        // Revert to own-billings-only totals.
        DB::table('projects')->whereNull('parent_id')->whereNull('deleted_at')
            ->orderBy('id')->pluck('id')
            ->each(function ($parentId) {
                DB::table('projects')->where('id', $parentId)->update([
                    'budget_paid' => DB::table('project_billings')
                        ->where('project_id', $parentId)
                        ->where('status', 'approved')
                        ->whereNull('deleted_at')
                        ->sum('amount') ?: 0,
                ]);
            });
    }
};
