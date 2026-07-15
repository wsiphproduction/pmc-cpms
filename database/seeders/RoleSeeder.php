<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['requestor', 'approver', 'assistant_manager', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
