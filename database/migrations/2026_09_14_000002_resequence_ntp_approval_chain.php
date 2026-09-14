<?php

use App\Models\ProjectNtp;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The NTP sign-off order changed: PMD now signs first (Assistant Manager,
 * Department Manager, Division Manager), then the department user, and a new
 * final step — the Division Manager User over that department's division —
 * issues it.
 *
 * Chains that nobody has signed yet are rewritten in the new order. A chain
 * already partly signed keeps its order, so nobody's signature is discarded,
 * but gains the new final step so it still ends where every NTP now does.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $open = DB::table('project_ntps')
            ->where('status', 'pending_review')
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($open as $ntpId) {
            $steps = DB::table('approval_steps')
                ->where('approvable_type', ProjectNtp::class)
                ->where('approvable_id', $ntpId)
                ->orderBy('sequence')
                ->get();

            if ($steps->isEmpty() || $steps->contains('status', 'rejected')) {
                continue;
            }

            if ($steps->every(fn ($step) => $step->status === 'pending')) {
                DB::table('approval_steps')->whereIn('id', $steps->pluck('id'))->delete();

                DB::table('approval_steps')->insert(
                    collect(ProjectNtp::APPROVAL_CHAIN)->values()->map(fn ($role, $index) => [
                        'approvable_type' => ProjectNtp::class,
                        'approvable_id'   => $ntpId,
                        'role'            => $role,
                        'sequence'        => $index + 1,
                        'status'          => 'pending',
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ])->all()
                );

                continue;
            }

            if (! $steps->contains('role', User::ROLE_DIVISION_MANAGER_USER)) {
                DB::table('approval_steps')->insert([
                    'approvable_type' => ProjectNtp::class,
                    'approvable_id'   => $ntpId,
                    'role'            => User::ROLE_DIVISION_MANAGER_USER,
                    'sequence'        => $steps->max('sequence') + 1,
                    'status'          => 'pending',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // The signatures already given are kept; only the new, still-pending
        // final step is taken back out.
        DB::table('approval_steps')
            ->where('approvable_type', ProjectNtp::class)
            ->where('role', User::ROLE_DIVISION_MANAGER_USER)
            ->where('status', 'pending')
            ->delete();
    }
};
