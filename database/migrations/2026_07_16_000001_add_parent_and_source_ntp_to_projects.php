<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sub-projects: a project may belong to one parent project and be spawned
     * from a single issued NTP. Both foreign keys use NO ACTION (the default)
     * rather than cascade/set-null on purpose — projects.parent_id is a
     * self-reference and projects.source_ntp_id closes a cycle back through
     * project_ntps.project_id (which cascades), and SQL Server forbids cascade
     * cycles / multiple cascade paths. Deletes are handled at the app layer
     * (projects soft-delete; issued NTPs are never deletable).
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('projects');
            $table->foreignId('source_ntp_id')->nullable()->after('parent_id')->constrained('project_ntps');
        });

        // One sub-project per NTP. A plain unique index treats NULL as a value on
        // SQL Server (blocking a second non-sub project), so filter it there.
        if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
            DB::statement('CREATE UNIQUE INDEX projects_source_ntp_id_unique ON projects (source_ntp_id) WHERE source_ntp_id IS NOT NULL');
        } else {
            Schema::table('projects', function (Blueprint $table) {
                $table->unique('source_ntp_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
            DB::statement('DROP INDEX projects_source_ntp_id_unique ON projects');
        }

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlsrv') {
                $table->dropUnique('projects_source_ntp_id_unique');
            }
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['source_ntp_id']);
            $table->dropColumn(['parent_id', 'source_ntp_id']);
        });
    }
};
