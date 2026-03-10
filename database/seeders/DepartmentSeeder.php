<?php

namespace Database\Seeders;

use App\Models\HR\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Human Resources', 'code' => 'HR', 'status' => 'Active'],
            ['name' => 'Engineering', 'code' => 'ENG', 'status' => 'Active'],
            ['name' => 'Finance', 'code' => 'FIN', 'status' => 'Active'],
            ['name' => 'Sales', 'code' => 'SAL', 'status' => 'Active'],
            ['name' => 'Marketing', 'code' => 'MKT', 'status' => 'Active'],
            ['name' => 'Operations', 'code' => 'OPS', 'status' => 'Active'],
            ['name' => 'Customer Support', 'code' => 'CS', 'status' => 'Active'],
            ['name' => 'Legal', 'code' => 'LEG', 'status' => 'Inactive'],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['name' => $dept['name']],
                ['code' => $dept['code'], 'status' => $dept['status']]
            );
        }
    }
}
