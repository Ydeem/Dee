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
            ['name'=>'HR Manager',       'dept'=>'Human Resources', 'level'=>'Manager',   'min'=>7000,  'max'=>12000],
            ['name'=>'HR Officer',       'dept'=>'Human Resources', 'level'=>'Mid-level', 'min'=>3500,  'max'=>6000],
            ['name'=>'HR Assistant',     'dept'=>'Human Resources', 'level'=>'Junior',    'min'=>2000,  'max'=>3500],
            ['name'=>'Software Engineer','dept'=>'Engineering',     'level'=>'Mid-level', 'min'=>5000,  'max'=>9000],
            ['name'=>'Senior Engineer',  'dept'=>'Engineering',     'level'=>'Senior',    'min'=>8000,  'max'=>15000],
            ['name'=>'Tech Lead',        'dept'=>'Engineering',     'level'=>'Lead',      'min'=>12000, 'max'=>20000],
            ['name'=>'Junior Developer', 'dept'=>'Engineering',     'level'=>'Junior',    'min'=>2500,  'max'=>4500],
            ['name'=>'Finance Officer',  'dept'=>'Finance',         'level'=>'Mid-level', 'min'=>4000,  'max'=>7000],
            ['name'=>'Finance Manager',  'dept'=>'Finance',         'level'=>'Manager',   'min'=>8000,  'max'=>14000],
            ['name'=>'Accountant',       'dept'=>'Finance',         'level'=>'Mid-level', 'min'=>3500,  'max'=>6500],
            ['name'=>'Sales Executive',  'dept'=>'Sales',           'level'=>'Junior',    'min'=>2500,  'max'=>5000],
            ['name'=>'Sales Manager',    'dept'=>'Sales',           'level'=>'Manager',   'min'=>7000,  'max'=>13000],
            ['name'=>'Marketing Officer','dept'=>'Marketing',       'level'=>'Mid-level', 'min'=>3500,  'max'=>6500],
            ['name'=>'Marketing Manager','dept'=>'Marketing',       'level'=>'Manager',   'min'=>7000,  'max'=>12000],
            ['name'=>'Operations Lead',  'dept'=>'Operations',      'level'=>'Lead',      'min'=>6000,  'max'=>11000],
            ['name'=>'Operations Officer','dept'=>'Operations',     'level'=>'Mid-level', 'min'=>3500,  'max'=>6000],
            ['name'=>'Support Agent',    'dept'=>'Customer Support','level'=>'Junior',    'min'=>2000,  'max'=>4000],
            ['name'=>'Support Lead',     'dept'=>'Customer Support','level'=>'Lead',      'min'=>5000,  'max'=>9000],
            ['name'=>'Chief Executive',  'dept'=>'Operations',      'level'=>'C-Level',   'min'=>30000, 'max'=>60000],
            ['name'=>'Director',         'dept'=>'Operations',      'level'=>'Director',  'min'=>20000, 'max'=>40000],
        ];

        $departments = Department::all()->keyBy('name');
        $names = collect($designations)->pluck('name')->all();

        Designation::whereNotIn('name', $names)
            ->whereDoesntHave('employees')
            ->delete();

        foreach ($designations as $item) {
            $department = $departments[$item['dept']] ?? null;

            Designation::updateOrCreate(
                ['name' => $item['name']],
                [
                    'department_id' => $department?->id,
                    'level' => $item['level'],
                    'status' => 'Active',
                    'min_salary' => $item['min'],
                    'max_salary' => $item['max'],
                    'description' => $item['name'] . ' position in ' . $item['dept'],
                ]
            );
        }
    }
}
