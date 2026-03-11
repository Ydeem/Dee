<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class EmployeesModuleSeeder extends Seeder
{
    public function run(): void
    {
        $departments = collect(['People Operations', 'Engineering', 'Finance', 'Sales', 'Customer Success'])
            ->map(fn ($name) => Department::firstOrCreate(['name' => $name]));

        $designationMap = [
            'People Operations' => ['HR Manager', 'Talent Acquisition Specialist'],
            'Engineering' => ['Software Engineer', 'Engineering Lead'],
            'Finance' => ['Accountant', 'Payroll Specialist'],
            'Sales' => ['Sales Executive', 'Account Manager'],
            'Customer Success' => ['Customer Success Manager', 'Support Specialist'],
        ];

        foreach ($designationMap as $departmentName => $designationNames) {
            $department = $departments->firstWhere('name', $departmentName);
            foreach ($designationNames as $designationName) {
                Designation::firstOrCreate([
                    'department_id' => $department?->id,
                    'name' => $designationName,
                ]);
            }
        }

        $shiftMorning = Shift::firstOrCreate(['name' => 'Morning Shift'], ['start_time' => '08:00', 'end_time' => '17:00']);
        $shiftHybrid = Shift::firstOrCreate(['name' => 'Hybrid Shift'], ['start_time' => '09:00', 'end_time' => '18:00']);

        // Employee seeding disabled — employees are added manually through the UI.
    }
}
