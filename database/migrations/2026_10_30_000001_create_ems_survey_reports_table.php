<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EMS Survey Reports — the Environmental Impact Assessment (PMD-PRJ-FRM-19)
 * a project engineer raises on a project and MEPEO fills in and approves —
 * and the two MEPEO roles that work them.
 *
 * No foreign key to projects: the live database refuses one (errno 150), so
 * the ids are plain indexed columns, as on project_meeting_minutes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ems_survey_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('control_no', 40)->unique();
            // draft → with_mepeo → for_approval → approved
            $table->string('status', 20)->default('draft');

            // Header, filled by the project engineer.
            $table->date('date_requested')->nullable();
            $table->string('location')->nullable();
            $table->string('contractor')->nullable();
            $table->date('target_start')->nullable();
            $table->date('target_end')->nullable();

            // The body, filled by the MEPEO representative.
            $table->json('major_activities')->nullable();
            $table->string('major_activities_other')->nullable();
            $table->text('other_scope')->nullable();
            $table->json('aspects')->nullable();
            $table->string('aspects_other')->nullable();
            $table->text('impacts')->nullable();
            $table->text('mitigation')->nullable();
            $table->string('mepeo_status', 30)->nullable();
            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('mepeo_rep_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            // Why it last came back to the representative, until they resubmit.
            $table->text('return_remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('project_id');
            $table->index('mepeo_rep_id');
        });

        // The role rows are otherwise only written by RoleSeeder, which an
        // existing install will not re-run.
        foreach ([User::ROLE_MEPEO_REP, User::ROLE_MEPEO_MANAGER] as $role) {
            if (! DB::table('roles')->where('name', $role)->exists()) {
                DB::table('roles')->insert([
                    'name'       => $role,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Spatie caches the role list; without this the users page cannot
        // assign the new roles until the cache expires on its own.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('name', [User::ROLE_MEPEO_REP, User::ROLE_MEPEO_MANAGER])->delete();
        Schema::dropIfExists('ems_survey_reports');
    }
};
