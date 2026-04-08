<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_requests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('job_type');
            $table->text('description');
            $table->integer('requester_id');
            $table->string('job_location');
            $table->string('costcode');
            $table->integer('opex')->default(0);
            $table->integer('capex')->default(0);
            $table->integer('for_budgeting')->default(0);
            $table->string('status')->default('pending');
            $table->softDeletes('deleted_at', precision: 0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_requests');
    }
};
