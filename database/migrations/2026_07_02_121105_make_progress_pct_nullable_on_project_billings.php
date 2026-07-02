<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `progress_pct` is an optional field (validated as `nullable` and the app explicitly
     * stores NULL when not reported), but the column was created NOT NULL — causing a 500
     * on every billing submitted without a progress percentage. Raw SQL is used here since
     * doctrine/dbal (required by Schema::table()->change()) isn't installed in this project.
     * The ALTER COLUMN syntax differs per driver, so both sqlsrv and mysql are handled.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE project_billings ALTER COLUMN progress_pct TINYINT NULL');
        } else {
            DB::statement('ALTER TABLE project_billings MODIFY COLUMN progress_pct TINYINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        DB::statement('UPDATE project_billings SET progress_pct = 0 WHERE progress_pct IS NULL');

        if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE project_billings ALTER COLUMN progress_pct TINYINT NOT NULL');
        } else {
            DB::statement('ALTER TABLE project_billings MODIFY COLUMN progress_pct TINYINT UNSIGNED NOT NULL DEFAULT 0');
        }
    }
};
