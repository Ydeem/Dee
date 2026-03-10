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
        User::updateOrCreate(
            ['email' => 'pontian@npontu.com'],
            [
                'name' => 'Pontian',
                'password' => Hash::make('password12#'),
                'email_verified_at' => now(),
            ]
        );
    }
}
