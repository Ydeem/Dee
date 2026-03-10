<?php

namespace Database\Seeders;

use App\Models\HR\Department;
use App\Models\HR\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    public function run(): void
    {
        $designations = [
            ['name' => 'HR Manager', 'level' => 'Manager', 'dept' => 'Human Resources'],
            ['name' => 'HR Officer', 'level' => 'Mid-level', 'dept' => 'Human Resources'],
            ['name' => 'Software Engineer', 'level' => 'Mid-level', 'dept' => 'Engineering'],
            ['name' => 'Senior Engineer', 'level' => 'Senior', 'dept' => 'Engineering'],
            ['name' => 'Tech Lead', 'level' => 'Lead', 'dept' => 'Engineering'],
            ['name' => 'Finance Officer', 'level' => 'Mid-level', 'dept' => 'Finance'],
            ['name' => 'Finance Manager', 'level' => 'Manager', 'dept' => 'Finance'],
            ['name' => 'Sales Executive', 'level' => 'Junior', 'dept' => 'Sales'],
            ['name' => 'Sales Manager', 'level' => 'Manager', 'dept' => 'Sales'],
            ['name' => 'Marketing Officer', 'level' => 'Mid-level', 'dept' => 'Marketing'],
            ['name' => 'Operations Lead', 'level' => 'Lead', 'dept' => 'Operations'],
            ['name' => 'Support Agent', 'level' => 'Junior', 'dept' => 'Customer Support'],
            ['name' => 'Chief Executive', 'level' => 'C-Level', 'dept' => 'Operations'],
            ['name' => 'Chief Finance', 'level' => 'C-Level', 'dept' => 'Finance'],
        ];

        foreach ($designations as $item) {
            $dept = Department::where('name', $item['dept'])->first();

            Designation::firstOrCreate(
                ['name' => $item['name']],
                [
                    'level' => $item['level'],
                    'department_id' => $dept?->id,
                    'status' => 'Active',
                ]
            );
        }
    }
}
