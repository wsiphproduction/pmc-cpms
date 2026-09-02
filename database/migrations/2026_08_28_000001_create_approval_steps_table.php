<?php

use App\Models\ProjectNtp;
use App\Models\ProjectRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per step of an approval chain, for any approvable record.
        // Project requests and NTPs both run a fixed, sequential chain, so the
        // steps are written out in full when the chain starts and are ticked off
        // in order — which makes "what is waiting on my role?" a single query.
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            // The role expected to act on this step (a Spatie role name).
            $table->string('role', 60);
            $table->unsignedTinyInteger('sequence');
            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            // Plain nullable column (no FK) — SQL Server rejects extra cascade
            // paths here, matching how project_ntps.reviewed_by is stored.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id'], 'approval_steps_approvable_index');
            $table->index(['role', 'status'], 'approval_steps_role_status_index');
        });

        // Backfill the records that are still open, so nothing in flight is
        // stranded without a chain. Settled records (approved/rejected/issued)
        // predate the chain and are left alone — the UI reads them as legacy.
        $now = now();

        $openRequests = DB::table('project_requests')
            ->whereIn('status', ['pending', 'hold'])
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($openRequests as $id) {
            $this->seedChain(ProjectRequest::class, $id, ProjectRequest::APPROVAL_CHAIN, $now);
        }

        $openNtps = DB::table('project_ntps')
            ->where('status', 'pending_review')
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($openNtps as $id) {
            $this->seedChain(ProjectNtp::class, $id, ProjectNtp::APPROVAL_CHAIN, $now);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }

    private function seedChain(string $type, int $id, array $roles, $now): void
    {
        $rows = [];

        foreach (array_values($roles) as $index => $role) {
            $rows[] = [
                'approvable_type' => $type,
                'approvable_id'   => $id,
                'role'            => $role,
                'sequence'        => $index + 1,
                'status'          => 'pending',
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        DB::table('approval_steps')->insert($rows);
    }
};
