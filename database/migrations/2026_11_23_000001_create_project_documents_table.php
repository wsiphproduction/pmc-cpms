<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One table behind every upload-only section of the Operations Hub that is a
 * plain document register — SOW, drawings, BOQ, as-built drawings and the
 * like. `section` names which hub item a file was filed under; the list of
 * valid sections lives on ProjectDocument::SECTIONS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('section', 50);
            $table->text('description');
            $table->string('file_path', 500);
            $table->string('filename');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['project_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};
