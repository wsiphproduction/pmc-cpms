<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Dept Asst Manager/Supervisor raises project requests for their
 * department; the department user (the department manager) reviews each one
 * before it reaches the project engineers.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The role rows are otherwise only written by RoleSeeder, which an
        // existing install will not re-run.
        if (! DB::table('roles')->where('name', User::ROLE_DEPT_SUPERVISOR)->exists()) {
            DB::table('roles')->insert([
                'name'       => User::ROLE_DEPT_SUPERVISOR,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Spatie caches the role list; without this the users page cannot
        // assign the new role until the cache expires on its own.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('roles')->where('name', User::ROLE_DEPT_SUPERVISOR)->delete();
    }
};
