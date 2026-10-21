<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two hub sections: Minutes of the Meeting (one document per meeting date) and
 * Photos (an album per date, holding any number of photos).
 *
 * No foreign key to projects: the live database refuses one (errno 150), so
 * the ids are plain indexed columns, as on project_ntp_signatories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->date('meeting_date');
            $table->string('file_path', 500);
            $table->string('filename');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('project_id');
        });

        Schema::create('project_photo_albums', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->date('photo_date');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'photo_date']);
        });

        Schema::create('project_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_photo_album_id');
            $table->string('file_path', 500);
            $table->string('filename');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('project_photo_album_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_photos');
        Schema::dropIfExists('project_photo_albums');
        Schema::dropIfExists('project_meeting_minutes');
    }
};
