<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tables() as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('description', 500)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables()) as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }

    private function tables(): array
    {
        return [
            'sites',
            'classes',
            'priorities',
            'statuses',
            'departments',
            'categories',
            'service_types',
            'work_forces',
            'structures',
        ];
    }
};
