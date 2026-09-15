<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Division;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            JobTypeSeeder::class,
            JobLocationSeeder::class,
            CategorySeeder::class,
            ServiceTypeSeeder::class,
            WorkForceSeeder::class,
            StructureSeeder::class,
            PrioritySeeder::class,
        ]);

        // A department user needs a department and a division manager user
        // needs a division, the same way the user form requires them.
        $division = Division::firstOrCreate(
            ['name' => 'Operations'],
            ['description' => 'Operations Division', 'is_active' => true]
        );

        $department = Department::firstOrCreate(
            ['name' => 'Engineering'],
            ['description' => 'Engineering Department', 'division' => $division->name, 'is_active' => true]
        );

        // One user per role, all with the password "password".
        $users = [
            User::ROLE_ADMIN                 => ['name' => 'Admin User',                 'email' => 'admin@cpms.com'],
            User::ROLE_REQUESTOR             => ['name' => 'Department User',            'email' => 'requestor@cpms.com', 'department' => $department->name],
            User::ROLE_ENGINEER              => ['name' => 'Project Engineer',           'email' => 'engineer@cpms.com'],
            User::ROLE_ASSISTANT_MANAGER     => ['name' => 'Assistant Manager',          'email' => 'assistant.manager@cpms.com'],
            User::ROLE_PMD_ASST_MANAGER      => ['name' => 'PMD Assistant Manager',      'email' => 'pmd.asst.manager@cpms.com'],
            User::ROLE_PMD_DEPT_MANAGER      => ['name' => 'PMD Department Manager',     'email' => 'pmd.dept.manager@cpms.com'],
            User::ROLE_DIVISION_MANAGER      => ['name' => 'Division Manager',           'email' => 'division.manager@cpms.com'],
            User::ROLE_DIVISION_MANAGER_USER => ['name' => 'Division Manager User',      'email' => 'division.manager.user@cpms.com', 'division' => $division->name],
        ];

        foreach ($users as $role => $attributes) {
            $user = User::firstOrCreate(
                ['email' => $attributes['email']],
                $attributes + [
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$role]);
        }

        Setting::firstOrCreate(
            ['key' => 'project_completion_kpi'],
            ['value' => '80']
        );
    }
}
