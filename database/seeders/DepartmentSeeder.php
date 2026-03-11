<?php

namespace Database\Seeders;

use App\Models\HR\Department;
use App\Models\HR\Employee;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Human Resources', 'code' => 'HR', 'color' => '#4f6ef7', 'description' => 'Manages people operations', 'status' => 'Active'],
            ['name' => 'Engineering', 'code' => 'ENG', 'color' => '#22c55e', 'description' => 'Software development team', 'status' => 'Active'],
            ['name' => 'Finance', 'code' => 'FIN', 'color' => '#f59e0b', 'description' => 'Financial operations', 'status' => 'Active'],
            ['name' => 'Marketing', 'code' => 'MKT', 'color' => '#ec4899', 'description' => 'Marketing and growth', 'status' => 'Active'],
            ['name' => 'Sales', 'code' => 'SAL', 'color' => '#8b5cf6', 'description' => 'Sales and business development', 'status' => 'Active'],
            ['name' => 'Operations', 'code' => 'OPS', 'color' => '#06b6d4', 'description' => 'Business operations', 'status' => 'Active'],
            ['name' => 'Customer Support', 'code' => 'CS', 'color' => '#f97316', 'description' => 'Customer service team', 'status' => 'Active'],
            ['name' => 'Legal', 'code' => 'LEG', 'color' => '#64748b', 'description' => 'Legal and compliance', 'status' => 'Inactive'],
            ['name' => 'Product', 'code' => 'PRD', 'color' => '#10b981', 'description' => 'Product management', 'status' => 'Active'],
            ['name' => 'People Operations', 'code' => 'PO', 'color' => '#f43f5e', 'description' => 'People and culture', 'status' => 'Active'],
        ];

        $names = collect($departments)->pluck('name')->all();

        Department::whereNotIn('name', $names)
            ->whereDoesntHave('employees')
            ->delete();

        foreach ($departments as $dept) {
            $department = Department::updateOrCreate(
                ['name' => $dept['name']],
                $dept
            );

            if (! $department->manager_id) {
                $manager = Employee::where('department_id', $department->id)
                    ->where('employment_status', 'Active')
                    ->orderBy('first_name')
                    ->first();

                if ($manager) {
                    $department->update([
                        'manager_id' => $manager->id,
                    ]);
                }
            }
        }
    }
}
