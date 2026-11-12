<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Environment Survey Reports now go PMD first: the engineer prepares the
 * whole assessment, the PMD Assistant Manager reviews it, the PMD Manager
 * notes it, then the MEPEO representative verifies it and the MEPEO Manager
 * approves it. The representative's verification is now a step of the chain.
 *
 * `submitted_at` changes meaning from "the representative submitted" to "the
 * engineer submitted for review", and `sent_at` from "sent to the
 * representative" to "reached MEPEO". Reports already under way are moved
 * onto the new order:
 *
 *  - with the representative (not yet filled out): back to the engineer as a
 *    draft, to check the assessment and submit it again;
 *  - in sign-off: the chain is re-ordered, the representative's submission
 *    recorded as their verification, and the steps still unsigned carry on;
 *  - approved: the representative's verification is added ahead of the
 *    signatures it came before, so the record and printout stay complete.
 */
return new class extends Migration
{
    private const TYPE = 'App\\Models\\EmsSurveyReport';

    private const ORDER = ['pmd_asst_manager' => 1, 'pmd_dept_manager' => 2, 'mepeo_rep' => 3, 'mepeo_manager' => 4];

    public function up(): void
    {
        $now = now();

        DB::table('ems_survey_reports')->whereNull('deleted_at')->where('status', 'with_mepeo')->orderBy('id')
            ->each(function ($report) use ($now) {
                DB::table('approval_steps')->where('approvable_type', self::TYPE)->where('approvable_id', $report->id)->delete();
                DB::table('ems_survey_reports')->where('id', $report->id)->update([
                    'status'         => 'draft',
                    'sent_at'        => null,
                    'submitted_at'   => null,
                    'return_remarks' => 'The sign-off order has changed: PMD now reviews the report before MEPEO. Check the assessment and submit it for review again.',
                    'updated_at'     => $now,
                ]);
            });

        DB::table('ems_survey_reports')->whereIn('status', ['for_approval', 'approved'])->orderBy('id')
            ->each(function ($report) use ($now) {
                $steps = DB::table('approval_steps')->where('approvable_type', self::TYPE)->where('approvable_id', $report->id);

                if ((clone $steps)->where('role', 'mepeo_rep')->exists()) {
                    return;
                }

                // Under way: the new signing order. Approved: kept as signed,
                // with the verification placed ahead of the first signature.
                if ($report->status === 'for_approval') {
                    foreach (self::ORDER as $role => $sequence) {
                        (clone $steps)->where('role', $role)->update(['sequence' => $sequence]);
                    }
                }

                DB::table('approval_steps')->insert([
                    'approvable_type' => self::TYPE,
                    'approvable_id'   => $report->id,
                    'role'            => 'mepeo_rep',
                    'sequence'        => $report->status === 'for_approval' ? self::ORDER['mepeo_rep'] : 0,
                    'status'          => 'approved',
                    'user_id'         => $report->mepeo_rep_id,
                    'acted_at'        => $report->submitted_at,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);

                // The engineer's send was their sign-off; it now lives in submitted_at.
                DB::table('ems_survey_reports')->where('id', $report->id)->update([
                    'submitted_at' => $report->sent_at ?? $report->submitted_at,
                    'updated_at'   => $now,
                ]);
            });
    }

    public function down(): void
    {
        // The old order cannot be restored without losing signatures given
        // under the new one; this migration is one-way.
    }
};
