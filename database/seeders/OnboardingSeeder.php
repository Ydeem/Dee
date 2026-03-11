<?php

namespace Database\Seeders;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeOnboarding;
use App\Models\HR\OnboardingTaskProgress;
use App\Models\HR\OnboardingTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OnboardingSeeder extends Seeder
{
    public function run(): void
    {
        $this->deleteInvalidOnboardings();

        $employees = Employee::active()->orderBy('id')->take(8)->get();
        if ($employees->isEmpty()) {
            return;
        }

        $template = OnboardingTemplate::first();
        if (! $template) {
            $template = OnboardingTemplate::create([
                'name' => 'Standard New Hire Checklist',
                'description' => 'Default onboarding checklist for all new hires',
                'days_to_complete' => 30,
                'status' => 'Active',
            ]);

            $tasks = [
                ['title' => 'Sign Employment Contract', 'category' => 'Documentation', 'due_days' => 1, 'required' => true],
                ['title' => 'Complete Tax Forms', 'category' => 'Documentation', 'due_days' => 2, 'required' => true],
                ['title' => 'Setup Work Email & Systems', 'category' => 'IT Setup', 'due_days' => 1, 'required' => true],
                ['title' => 'Setup Laptop & Access Cards', 'category' => 'IT Setup', 'due_days' => 2, 'required' => true],
                ['title' => 'Company Orientation', 'category' => 'Orientation', 'due_days' => 3, 'required' => true],
                ['title' => 'Meet the Team', 'category' => 'Orientation', 'due_days' => 5, 'required' => false],
                ['title' => 'HR Policy Training', 'category' => 'Training', 'due_days' => 7, 'required' => true],
                ['title' => 'Role-Specific Training', 'category' => 'Training', 'due_days' => 14, 'required' => true],
                ['title' => '30-Day Check-in', 'category' => 'HR', 'due_days' => 30, 'required' => true],
            ];

            foreach ($tasks as $i => $task) {
                $payload = [
                    'template_id' => $template->id,
                    'title' => $task['title'],
                    'category' => $task['category'],
                    'due_days' => $task['due_days'],
                    'required' => $task['required'],
                    'sort_order' => $i,
                ];

                if (Schema::hasColumn('onboarding_tasks', 'onboarding_template_id')) {
                    $payload['onboarding_template_id'] = $template->id;
                }
                if (Schema::hasColumn('onboarding_tasks', 'is_required')) {
                    $payload['is_required'] = $task['required'];
                }

                $template->tasks()->create($payload);
            }
        }

        OnboardingTaskProgress::query()->delete();
        EmployeeOnboarding::query()->delete();

        $scenarios = [
            ['status' => 'Not Started', 'days_ago' => 2, 'progress' => 0],
            ['status' => 'Not Started', 'days_ago' => 5, 'progress' => 0],
            ['status' => 'In Progress', 'days_ago' => 10, 'progress' => 40],
            ['status' => 'In Progress', 'days_ago' => 15, 'progress' => 70],
            ['status' => 'Completed', 'days_ago' => 40, 'progress' => 100],
            ['status' => 'Overdue', 'days_ago' => 35, 'progress' => 33],
            ['status' => 'Overdue', 'days_ago' => 40, 'progress' => 0],
            ['status' => 'Overdue', 'days_ago' => 45, 'progress' => 33],
        ];

        $tasksAll = $template->tasks()->orderBy('sort_order')->get();
        if ($tasksAll->isEmpty()) {
            return;
        }

        foreach ($employees as $i => $emp) {
            $scene = $scenarios[$i] ?? $scenarios[0];
            $start = now()->subDays((int) $scene['days_ago']);
            $end = $start->copy()->addDays((int) $template->days_to_complete);

            $buddyPool = $employees->where('id', '!=', $emp->id)->values();
            $buddy = $buddyPool->isNotEmpty() ? $buddyPool->random() : null;

            $payload = [
                'employee_id' => $emp->id,
                'template_id' => $template->id,
                'buddy_id' => $buddy?->id,
                'start_date' => $start->toDateString(),
                'expected_end_date' => $end->toDateString(),
                'status' => $scene['status'],
                'completed_at' => $scene['status'] === 'Completed' ? now() : null,
            ];

            if (Schema::hasColumn('employee_onboardings', 'onboarding_template_id')) {
                $payload['onboarding_template_id'] = $template->id;
            }
            if (Schema::hasColumn('employee_onboardings', 'assigned_buddy_id')) {
                $payload['assigned_buddy_id'] = $buddy?->id;
            }
            if (Schema::hasColumn('employee_onboardings', 'completed_date')) {
                $payload['completed_date'] = $scene['status'] === 'Completed' ? now()->toDateString() : null;
            }

            $ob = EmployeeOnboarding::create($payload);

            $target = (int) floor(((int) $scene['progress'] / 100) * $tasksAll->count());

            foreach ($tasksAll as $j => $task) {
                $taskDone = $j < $target;
                $taskPayload = [
                    'onboarding_id' => $ob->id,
                    'task_id' => $task->id,
                    'status' => $taskDone ? 'Completed' : 'Pending',
                    'completed_at' => $taskDone ? now() : null,
                ];

                if (Schema::hasColumn('onboarding_task_progress', 'employee_onboarding_id')) {
                    $taskPayload['employee_onboarding_id'] = $ob->id;
                }
                if (Schema::hasColumn('onboarding_task_progress', 'onboarding_task_id')) {
                    $taskPayload['onboarding_task_id'] = $task->id;
                }

                OnboardingTaskProgress::create($taskPayload);
            }
        }
    }

    private function deleteInvalidOnboardings(): void
    {
        $validEmployeeIds = Employee::query()->pluck('id');
        if ($validEmployeeIds->isEmpty()) {
            EmployeeOnboarding::query()->delete();
            return;
        }

        EmployeeOnboarding::query()
            ->whereNotIn('employee_id', $validEmployeeIds)
            ->delete();
    }
}
