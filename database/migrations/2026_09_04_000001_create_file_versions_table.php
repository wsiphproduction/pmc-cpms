<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version history for every uploaded file in the system.
 *
 * The "current" file keeps living on its own record (attachments.filepath,
 * project_quality_docs.file_path, …) so nothing that reads a file today has to
 * change. This table is the append-only log beside it: one row per upload,
 * numbered 1, 2, 3… per (record, collection), with the superseded files left on
 * disk so an earlier version can still be opened.
 */
return new class extends Migration
{
    /**
     * Every versioned file slot, as [table, model, path column, name column].
     * Also drives the v1 backfill below.
     *
     * RFQs are deliberately absent: a quotation is superseded by raising
     * another quotation against the RFQ, so that history already exists as
     * separate records and does not need a second one per file.
     */
    private const SLOTS = [
        ['attachments',              \App\Models\Attachment::class,            'filepath',           'filename'],
        ['projects',                 \App\Models\Project::class,               'proposal_document',  null],
        ['project_permit_files',     \App\Models\ProjectPermitFile::class,     'path',               'filename'],
        ['project_variation_orders', \App\Models\ProjectVariationOrder::class, 'attachment',         null],
        ['project_quality_docs',     \App\Models\ProjectQualityDoc::class,     'file_path',          'filename'],
        ['project_mtr_docs',         \App\Models\ProjectMtrDoc::class,         'file_path',          'filename'],
        ['project_billings',         \App\Models\ProjectBilling::class,        'file_path',          'filename'],
        ['project_ioc_items',        \App\Models\ProjectIocItem::class,        'file_path',          'filename'],
        ['project_weekly_reports',   \App\Models\ProjectWeeklyReport::class,   'file_path',          'filename'],
    ];

    public function up(): void
    {
        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->string('versionable_type');
            $table->unsignedBigInteger('versionable_id');

            // Which file slot on the record — a record may hold more than one
            // (a billing has both its statement PDF and its support files).
            $table->string('collection', 60)->default('file');

            $table->unsignedInteger('version');
            $table->string('filename');
            $table->string('filepath', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['versionable_type', 'versionable_id', 'collection'], 'file_versions_owner_index');
            $table->unique(
                ['versionable_type', 'versionable_id', 'collection', 'version'],
                'file_versions_owner_version_unique'
            );
        });

        $this->backfill();
    }

    /** Every file that already exists becomes v1 of its slot. */
    private function backfill(): void
    {
        foreach (self::SLOTS as [$table, $class, $pathColumn, $nameColumn]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $pathColumn)) {
                continue;
            }

            $columns = ['id', $pathColumn, 'created_at'];
            if ($nameColumn && Schema::hasColumn($table, $nameColumn)) {
                $columns[] = $nameColumn;
            }

            DB::table($table)
                ->select($columns)
                ->whereNotNull($pathColumn)
                ->where($pathColumn, '!=', '')
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($class, $pathColumn, $nameColumn) {
                    $now = now();

                    DB::table('file_versions')->insert($rows->map(fn ($row) => [
                        'versionable_type' => $class,
                        'versionable_id'   => $row->id,
                        'collection'       => 'file',
                        'version'          => 1,
                        'filename'         => ($nameColumn && isset($row->{$nameColumn}) && $row->{$nameColumn} !== null)
                            ? $row->{$nameColumn}
                            : basename($row->{$pathColumn}),
                        'filepath'         => $row->{$pathColumn},
                        'created_at'       => $row->created_at ?? $now,
                        'updated_at'       => $row->created_at ?? $now,
                    ])->all());
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('file_versions');
    }
};
