<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Divisions sit above departments: each department belongs to one division,
 * and a "Division Manager User" is tied to one division the same way a
 * department user is tied to a department — by name, matching how
 * users.department already links to departments.name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->string('division', 191)->nullable()->after('description');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('division', 191)->nullable()->after('department');
        });

        // The role rows are otherwise only written by RoleSeeder, which an
        // existing install will not re-run.
        if (! DB::table('roles')->where('name', User::ROLE_DIVISION_MANAGER_USER)->exists()) {
            DB::table('roles')->insert([
                'name'       => User::ROLE_DIVISION_MANAGER_USER,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('division');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('division');
        });

        Schema::dropIfExists('divisions');
    }
};
