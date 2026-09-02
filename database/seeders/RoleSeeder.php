<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(User::ROLE_LABELS) as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
