<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `recommendation` was created as VARCHAR(20), too narrow for values like
     * "Withhold (Pending Clarification)" — truncated inserts errored on SQL Server.
     * Raw SQL is used since doctrine/dbal (required by Schema::table()->change()) isn't installed.
     * The ALTER COLUMN syntax differs per driver, so both sqlsrv and mysql are handled.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE project_billings ALTER COLUMN recommendation NVARCHAR(255) NULL');
        } else {
            DB::statement('ALTER TABLE project_billings MODIFY COLUMN recommendation VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE project_billings ALTER COLUMN recommendation NVARCHAR(20) NULL');
        } else {
            DB::statement('ALTER TABLE project_billings MODIFY COLUMN recommendation VARCHAR(20) NULL');
        }
    }
};
