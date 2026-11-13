<?php

use App\Support\PhilippineHolidays;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Holidays master list: days a working-days project skips besides
     * Sundays. Starts with the Philippine regular holidays for 2025–2030;
     * more years, special and local holidays are added from Master Data.
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 191);
            $table->string('type', 20)->default('regular'); // regular | special | local
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        foreach (range(2025, 2030) as $year) {
            DB::table('holidays')->insert(array_map(fn (array $day) => $day + [
                'type'       => 'regular',
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], PhilippineHolidays::regular($year)));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
