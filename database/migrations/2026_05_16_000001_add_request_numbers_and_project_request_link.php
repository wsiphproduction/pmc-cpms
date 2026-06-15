<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_requests', function (Blueprint $table) {
            $table->string('request_no', 40)->nullable()->unique();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_request_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('project_requests')
                ->nullOnDelete();
        });

        $sequenceByYear = [];

        DB::table('project_requests')
            ->select(['id', 'created_at'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function ($request) use (&$sequenceByYear) {
                $year = $request->created_at
                    ? date('Y', strtotime($request->created_at))
                    : now()->format('Y');

                $sequenceByYear[$year] = ($sequenceByYear[$year] ?? 0) + 1;

                DB::table('project_requests')
                    ->where('id', $request->id)
                    ->update([
                        'request_no' => sprintf('REQ-%s-%04d', $year, $sequenceByYear[$year]),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_request_id');
        });

        Schema::table('project_requests', function (Blueprint $table) {
            $table->dropUnique(['request_no']);
            $table->dropColumn('request_no');
        });
    }
};
