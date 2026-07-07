<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('type')->nullable()->after('filepath');
        });

        // Backfill existing rows from the folder segment in their filepath
        // (e.g. "requests/5/reports/xyz.pdf" → "report").
        foreach (['picture', 'drawing', 'report'] as $type) {
            DB::table('attachments')
                ->where('filepath', 'like', "%/{$type}s/%")
                ->update(['type' => $type]);
        }
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
