<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The plain unique index on `project_request_id` treats NULL as a value on SQL Server,
     * so only ONE project without a linked request could ever exist — every subsequent
     * "direct" project (not created from a Project Request) failed to insert. A filtered
     * index (unique only where the column is not null) is the standard SQL Server fix.
     * MySQL is unaffected: it already treats each NULL as distinct in a unique index, so
     * no schema change is needed there.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::statement('DROP INDEX projects_project_request_id_unique ON projects');
        DB::statement('CREATE UNIQUE INDEX projects_project_request_id_unique ON projects (project_request_id) WHERE project_request_id IS NOT NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::statement('DROP INDEX projects_project_request_id_unique ON projects');
        DB::statement('CREATE UNIQUE INDEX projects_project_request_id_unique ON projects (project_request_id)');
    }
};
