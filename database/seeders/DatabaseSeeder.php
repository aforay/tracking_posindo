<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Default Administrator Account
        User::firstOrCreate(
            ['email' => 'admin@posindo.com'],
            [
                'name' => 'Administrator Posindo',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // 2. Default Customer Service (CS) Account
        User::firstOrCreate(
            ['email' => 'cs@posindo.com'],
            [
                'name' => 'Staff Customer Service',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'cs',
                'email_verified_at' => now(),
            ]
        );
    }
}
