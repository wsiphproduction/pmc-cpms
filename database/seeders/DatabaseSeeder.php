<?php

namespace Database\Seeders;

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
       
        // Admin user
        User::firstOrCreate(
            ['email' => 'admin@cpms.com'],
            [
                'name'              => 'Admin User',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Test user
        User::firstOrCreate(
            ['email' => 'test@cpms.com'],
            [
                'name'              => 'Test User',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Extra fake users
        User::factory(10)->create();

        Setting::firstOrCreate(
            ['key' => 'project_completion_kpi'],
            ['value' => '80']
        );
    }
}
