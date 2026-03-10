<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\EmployeeOnboarding;
use App\Models\HR\OnboardingTaskProgress;
use App\Models\HR\OnboardingTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeOnboarding::with([
            'employee.department',
            'template',
            'buddy',
            'taskProgress',
        ])
            ->when($request->search, fn ($q) =>
                $q->whereHas('employee', fn ($sub) =>
                    $sub->where('first_name', 'like', '%' . $request->search . '%')
                        ->orWhere('last_name', 'like', '%' . $request->search . '%')
                )
            )
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->department, fn ($q) =>
                $q->whereHas('employee.department', fn ($sub) => $sub->where('name', $request->department))
            );

        $onboardings = $query->orderBy('start_date', 'desc')->paginate((int) ($request->per_page ?? 10));

        $onboardings->getCollection()->transform(function ($onboarding) {
            if ($onboarding->status === 'In Progress' && $onboarding->expected_end_date && $onboarding->expected_end_date->isPast()) {
                EmployeeOnboarding::whereKey($onboarding->id)->update(['status' => 'Overdue']);
                $onboarding->status = 'Overdue';
            }

            $total = $onboarding->taskProgress->count();
            $completed = $onboarding->taskProgress->where('status', 'Completed')->count();
            $onboarding->progress = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

            return $onboarding;
        });

        $summary = [
            'not_started' => EmployeeOnboarding::where('status', 'Not Started')->count(),
            'in_progress' => EmployeeOnboarding::where('status', 'In Progress')->count(),
            'completed' => EmployeeOnboarding::where('status', 'Completed')->count(),
            'overdue' => EmployeeOnboarding::where('status', 'Overdue')->count(),
        ];

        return response()->json([
            'onboardings' => $onboardings,
            'summary' => $summary,
            'templates' => OnboardingTemplate::where('status', 'Active')->get(['id', 'name']),
            'departments' => \App\Models\HR\Department::pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'onboarding_template_id' => 'required|exists:onboarding_templates,id',
            'start_date' => 'required|date',
            'assigned_buddy_id' => 'nullable|exists:employees,id',
            'notes' => 'nullable|string',
        ]);

        $activeExists = EmployeeOnboarding::where('employee_id', $validated['employee_id'])
            ->whereIn('status', ['Not Started', 'In Progress', 'Overdue'])
            ->exists();
        if ($activeExists) {
            return response()->json(['message' => 'This employee already has an active onboarding in progress.'], 422);
        }

        $template = OnboardingTemplate::with('tasks')->findOrFail($validated['onboarding_template_id']);
        $maxDays = $template->tasks->max('due_days') ?? 30;
        $expectedEnd = Carbon::parse($validated['start_date'])->addDays((int) $maxDays)->toDateString();

        $onboarding = EmployeeOnboarding::create([
            ...$validated,
            'expected_end_date' => $expectedEnd,
            'status' => 'Not Started',
        ]);

        foreach ($template->tasks as $task) {
            OnboardingTaskProgress::create([
                'employee_onboarding_id' => $onboarding->id,
                'onboarding_task_id' => $task->id,
                'status' => 'Pending',
            ]);
        }

        return response()->json([
            'onboarding' => $onboarding->load(['employee.department', 'template', 'taskProgress.task']),
        ], 201);
    }

    public function show(int $id)
    {
        $onboarding = EmployeeOnboarding::with([
            'employee.department',
            'template.tasks',
            'buddy',
            'taskProgress.task',
            'taskProgress.completedByEmployee',
        ])->findOrFail($id);

        $total = $onboarding->taskProgress->count();
        $completed = $onboarding->taskProgress->where('status', 'Completed')->count();
        $onboarding->progress = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return response()->json(['onboarding' => $onboarding]);
    }

    public function updateTaskStatus(Request $request, int $id, int $taskId)
    {
        $progress = OnboardingTaskProgress::where('employee_onboarding_id', $id)
            ->where('onboarding_task_id', $taskId)
            ->firstOrFail();

        $validated = $request->validate([
            'status' => 'required|in:Pending,In Progress,Completed,Skipped',
            'notes' => 'nullable|string',
        ]);

        $employeeId = auth()->id();
        if ($employeeId && !\App\Models\HR\Employee::whereKey($employeeId)->exists()) {
            $employeeId = null;
        }

        $progress->update([
            'status' => $validated['status'],
            'completed_at' => $validated['status'] === 'Completed' ? now() : null,
            'completed_by' => $validated['status'] === 'Completed' ? $employeeId : null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $onboarding = EmployeeOnboarding::with('taskProgress')->findOrFail($id);
        $total = $onboarding->taskProgress->count();
        $completedCount = $onboarding->taskProgress->where('status', 'Completed')->count();
        $inProgressCount = $onboarding->taskProgress->whereIn('status', ['In Progress', 'Completed'])->count();

        if ($completedCount === $total && $total > 0) {
            $onboarding->update([
                'status' => 'Completed',
                'completed_date' => now()->toDateString(),
            ]);
        } elseif ($inProgressCount > 0) {
            $onboarding->update(['status' => 'In Progress']);
        }

        return response()->json(['message' => 'Task updated.']);
    }

    public function update(Request $request, int $id)
    {
        $onboarding = EmployeeOnboarding::findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|in:Not Started,In Progress,Completed,Overdue,Cancelled',
            'assigned_buddy_id' => 'nullable|exists:employees,id',
            'notes' => 'nullable|string',
        ]);
        $onboarding->update($validated);
        return response()->json(['onboarding' => $onboarding]);
    }

    public function destroy(int $id)
    {
        EmployeeOnboarding::findOrFail($id)->delete();
        return response()->json(['message' => 'Onboarding record deleted.']);
    }
}
