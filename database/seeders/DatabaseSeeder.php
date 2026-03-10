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

        $this->call(EmployeesModuleSeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(DesignationSeeder::class);
        $this->call(AttendanceSeeder::class);
        $this->call(LeaveSeeder::class);
        $this->call(ShiftSeeder::class);
        $this->call(JobOpeningSeeder::class);
        $this->call(ApplicantSeeder::class);
        $this->call(OnboardingSeeder::class);
    }
}
