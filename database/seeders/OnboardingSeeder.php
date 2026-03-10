<?php

namespace Database\Seeders;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeOnboarding;
use App\Models\HR\OnboardingTask;
use App\Models\HR\OnboardingTaskProgress;
use App\Models\HR\OnboardingTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OnboardingSeeder extends Seeder
{
    public function run(): void
    {
        $template = OnboardingTemplate::firstOrCreate(
            ['name' => 'Standard New Hire Checklist'],
            [
                'description' => 'Default onboarding for all new employees',
                'status' => 'Active',
            ]
        );

        $tasks = [
            ['title' => 'Send welcome email', 'category' => 'HR Documents', 'due_days' => 1, 'assigned_to_role' => 'HR', 'is_required' => true],
            ['title' => 'Set up work email account', 'category' => 'IT Setup', 'due_days' => 1, 'assigned_to_role' => 'IT', 'is_required' => true],
            ['title' => 'Provide laptop and equipment', 'category' => 'Equipment', 'due_days' => 1, 'assigned_to_role' => 'IT', 'is_required' => true],
            ['title' => 'Complete employment contract', 'category' => 'HR Documents', 'due_days' => 2, 'assigned_to_role' => 'HR', 'is_required' => true],
            ['title' => 'Office tour and introductions', 'category' => 'Orientation', 'due_days' => 1, 'assigned_to_role' => 'Manager', 'is_required' => true],
            ['title' => 'Set up system access and permissions', 'category' => 'Access & Security', 'due_days' => 2, 'assigned_to_role' => 'IT', 'is_required' => true],
            ['title' => 'Complete HR policy orientation', 'category' => 'Orientation', 'due_days' => 3, 'assigned_to_role' => 'HR', 'is_required' => true],
            ['title' => 'Meet with direct manager', 'category' => 'Orientation', 'due_days' => 1, 'assigned_to_role' => 'Manager', 'is_required' => true],
            ['title' => 'Complete mandatory safety training', 'category' => 'Training', 'due_days' => 5, 'assigned_to_role' => 'HR', 'is_required' => true],
            ['title' => 'Set up payroll and bank details', 'category' => 'HR Documents', 'due_days' => 3, 'assigned_to_role' => 'HR', 'is_required' => true],
            ['title' => '30-day check-in with manager', 'category' => 'Training', 'due_days' => 30, 'assigned_to_role' => 'Manager', 'is_required' => false],
            ['title' => 'Complete role-specific training', 'category' => 'Training', 'due_days' => 14, 'assigned_to_role' => 'Employee', 'is_required' => true],
        ];

        foreach ($tasks as $index => $taskData) {
            OnboardingTask::firstOrCreate(
                ['onboarding_template_id' => $template->id, 'title' => $taskData['title']],
                array_merge($taskData, ['onboarding_template_id' => $template->id, 'sort_order' => $index + 1])
            );
        }

        $targetEmployees = 10;
        $activeEmployees = Employee::where('employment_status', 'Active')->count();

        if ($activeEmployees < $targetEmployees) {
            $missing = $targetEmployees - $activeEmployees;
            $now = now();
            $rows = [];

            for ($i = 1; $i <= $missing; $i++) {
                $seed = rand(1000, 9999) . $i;
                $rows[] = [
                    'employee_id' => 'ONB' . str_pad((string) (Employee::count() + $i), 5, '0', STR_PAD_LEFT),
                    'first_name' => 'Onboard',
                    'last_name' => 'Demo' . $i,
                    'phone' => '055' . str_pad((string) $seed, 7, '0', STR_PAD_LEFT),
                    'personal_email' => "onboard.demo{$i}@example.com",
                    'employment_type' => 'Full-time',
                    'employment_status' => 'Active',
                    'join_date' => $now->copy()->subDays(rand(0, 20))->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('employees')->insert($rows);
        }

        $employees = Employee::where('employment_status', 'Active')->latest()->limit($targetEmployees)->get();
        if ($employees->isEmpty()) {
            return;
        }

        $buddy = Employee::first();
        $statuses = ['Not Started', 'In Progress', 'In Progress', 'Completed', 'Overdue'];

        foreach ($employees as $employee) {
            $startDate = now()->copy()->subDays(rand(0, 45));
            $status = $statuses[array_rand($statuses)];

            $onboarding = EmployeeOnboarding::firstOrCreate(
                ['employee_id' => $employee->id],
                [
                    'onboarding_template_id' => $template->id,
                    'start_date' => $startDate->toDateString(),
                    'expected_end_date' => $startDate->copy()->addDays(30)->toDateString(),
                    'status' => $status,
                    'assigned_buddy_id' => $buddy?->id,
                    'completed_date' => $status === 'Completed' ? $startDate->copy()->addDays(28)->toDateString() : null,
                ]
            );

            $templateTasks = OnboardingTask::where('onboarding_template_id', $template->id)->get();
            foreach ($templateTasks as $task) {
                $taskStatus = match ($status) {
                    'Completed' => 'Completed',
                    'Not Started' => 'Pending',
                    default => ['Pending', 'Completed', 'In Progress'][array_rand(['Pending', 'Completed', 'In Progress'])],
                };

                OnboardingTaskProgress::firstOrCreate(
                    ['employee_onboarding_id' => $onboarding->id, 'onboarding_task_id' => $task->id],
                    ['status' => $taskStatus, 'completed_at' => $taskStatus === 'Completed' ? now() : null]
                );
            }
        }
    }
}
