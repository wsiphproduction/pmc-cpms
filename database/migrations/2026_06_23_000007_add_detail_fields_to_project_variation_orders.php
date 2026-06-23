<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_variation_orders', function (Blueprint $table) {
            $table->string('requestor', 255)->nullable();
            $table->date('date_of_request')->nullable();
            $table->string('priority', 255)->nullable();
            $table->string('attachment', 500)->nullable();
            $table->text('scope_original')->nullable();
            $table->text('scope_proposed')->nullable();
            $table->text('scope_remark')->nullable();
            $table->text('schedule_original')->nullable();
            $table->text('schedule_proposed')->nullable();
            $table->text('schedule_remark')->nullable();
            $table->text('cost_original')->nullable();
            $table->text('cost_proposed')->nullable();
            $table->text('cost_remark')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_variation_orders', function (Blueprint $table) {
            $table->dropColumn([
                'requestor', 'date_of_request', 'priority', 'attachment',
                'scope_original', 'scope_proposed', 'scope_remark',
                'schedule_original', 'schedule_proposed', 'schedule_remark',
                'cost_original', 'cost_proposed', 'cost_remark',
            ]);
        });
    }
};
