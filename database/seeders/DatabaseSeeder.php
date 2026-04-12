<?php

namespace Database\Seeders;

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
        User::create([
            'name'              => 'Admin User',
            'email'             => 'admin@cpms.com',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Test user
        User::create([
            'name'              => 'Test User',
            'email'             => 'test@cpms.com',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Extra fake users
        User::factory(10)->create();
    }
}
