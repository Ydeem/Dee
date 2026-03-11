<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeOnboarding;
use App\Models\HR\OnboardingTaskProgress;
use App\Models\HR\OnboardingTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OnboardingController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeOnboarding::with([
            'employee:id,first_name,last_name,employee_id,avatar,department_id',
            'employee.department:id,name',
            'template:id,name',
            'buddy:id,first_name,last_name',
        ])
            ->withCount([
                'taskProgress',
                'taskProgress as completed_tasks_count' => fn ($q) => $q->where('status', 'Completed'),
            ])
            ->when($request->search, fn ($q) =>
                $q->whereHas('employee', fn ($sq) =>
                    $sq->where('first_name', 'like', '%' . $request->search . '%')
                        ->orWhere('last_name', 'like', '%' . $request->search . '%')
                )
            )
            ->when($request->department, fn ($q) =>
                $q->whereHas('employee.department', fn ($sq) => $sq->where('name', $request->department))
            )
            ->when($request->status, fn ($q) => $q->where('status', $request->status));

        $onboardings = $query->orderBy('created_at', 'desc')
            ->paginate((int) ($request->per_page ?? 10));

        $onboardings->through(function (EmployeeOnboarding $ob) {
            $total = (int) $ob->task_progress_count;
            $done = (int) $ob->completed_tasks_count;
            $progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;

            return [
                'id' => $ob->id,
                'start_date' => Carbon::parse($ob->start_date)->format('M d, Y'),
                'start_date_raw' => optional($ob->start_date)->format('Y-m-d'),
                'expected_end' => $ob->expected_end_date
                    ? Carbon::parse($ob->expected_end_date)->format('M d, Y')
                    : null,
                'expected_end_raw' => optional($ob->expected_end_date)->format('Y-m-d'),
                'is_overdue' => $ob->expected_end_date
                    && Carbon::parse($ob->expected_end_date)->isPast()
                    && $ob->status !== 'Completed',
                'completed_at' => $ob->completed_at
                    ? Carbon::parse($ob->completed_at)->format('M d, Y')
                    : null,
                'status' => $ob->status,
                'status_color' => $ob->status_color,
                'progress' => $progress,
                'tasks_total' => $total,
                'tasks_done' => $done,
                'notes' => $ob->notes,
                'employee' => $ob->employee ? [
                    'id' => $ob->employee->id,
                    'name' => trim($ob->employee->first_name . ' ' . $ob->employee->last_name),
                    'emp_id' => $ob->employee->employee_id,
                    'avatar' => $ob->employee->avatar
                        ? asset('storage/' . $ob->employee->avatar)
                        : null,
                    'initials' => strtoupper(
                        substr($ob->employee->first_name ?? '', 0, 1)
                        . substr($ob->employee->last_name ?? '', 0, 1)
                    ),
                    'department' => $ob->employee->department?->name ?? '-',
                ] : null,
                'template' => $ob->template ? [
                    'id' => $ob->template->id,
                    'name' => $ob->template->name,
                ] : null,
                'buddy' => $ob->buddy ? [
                    'id' => $ob->buddy->id,
                    'name' => trim($ob->buddy->first_name . ' ' . $ob->buddy->last_name),
                ] : null,
            ];
        });

        $stats = [
            'not_started' => EmployeeOnboarding::where('status', 'Not Started')->count(),
            'in_progress' => EmployeeOnboarding::where('status', 'In Progress')->count(),
            'completed' => EmployeeOnboarding::where('status', 'Completed')->count(),
            'overdue' => EmployeeOnboarding::where('status', 'Overdue')->count(),
        ];

        return response()->json([
            'onboardings' => $onboardings,
            'stats' => $stats,
            'filters' => [
                'departments' => Department::active()->orderBy('name')->pluck('name'),
            ],
        ]);
    }

    public function boardView(Request $request)
    {
        $statuses = ['Not Started', 'In Progress', 'Completed', 'Overdue'];

        $board = collect($statuses)->map(function (string $status) use ($request) {
            $items = EmployeeOnboarding::with([
                'employee:id,first_name,last_name,avatar',
                'template:id,name',
                'buddy:id,first_name,last_name',
            ])
                ->withCount([
                    'taskProgress',
                    'taskProgress as done_count' => fn ($q) => $q->where('status', 'Completed'),
                ])
                ->where('status', $status)
                ->when($request->department, fn ($q) =>
                    $q->whereHas('employee.department', fn ($sq) => $sq->where('name', $request->department))
                )
                ->orderBy('expected_end_date')
                ->get()
                ->map(function (EmployeeOnboarding $ob) {
                    $total = (int) $ob->task_progress_count;
                    $done = (int) $ob->done_count;
                    $progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;

                    return [
                        'id' => $ob->id,
                        'progress' => $progress,
                        'tasks' => "{$done} / {$total}",
                        'is_overdue' => $ob->expected_end_date
                            && Carbon::parse($ob->expected_end_date)->isPast()
                            && $ob->status !== 'Completed',
                        'expected_end' => $ob->expected_end_date
                            ? Carbon::parse($ob->expected_end_date)->format('M d')
                            : null,
                        'employee' => $ob->employee ? [
                            'id' => $ob->employee->id,
                            'name' => trim($ob->employee->first_name . ' ' . $ob->employee->last_name),
                            'initials' => strtoupper(
                                substr($ob->employee->first_name ?? '', 0, 1)
                                . substr($ob->employee->last_name ?? '', 0, 1)
                            ),
                        ] : null,
                        'template' => $ob->template?->name,
                        'buddy' => $ob->buddy
                            ? trim($ob->buddy->first_name . ' ' . $ob->buddy->last_name)
                            : null,
                    ];
                });

            return [
                'status' => $status,
                'color' => match ($status) {
                    'Not Started' => 'grey',
                    'In Progress' => 'primary',
                    'Completed' => 'success',
                    'Overdue' => 'error',
                },
                'count' => $items->count(),
                'items' => $items,
            ];
        });

        return response()->json(['board' => $board]);
    }

    public function show(int $id)
    {
        $ob = EmployeeOnboarding::with([
            'employee.department',
            'template.tasks',
            'buddy',
            'taskProgress.task',
        ])->findOrFail($id);

        $tasks = $ob->template?->tasks->map(function ($task) use ($ob) {
            $progress = $ob->taskProgress->firstWhere('task_id', $task->id);

            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'category' => $task->category,
                'due_days' => $task->due_days,
                'required' => (bool) $task->required,
                'sort_order' => $task->sort_order,
                'due_date' => Carbon::parse($ob->start_date)->addDays((int) $task->due_days)->format('M d, Y'),
                'progress_id' => $progress?->id,
                'status' => $progress?->status ?? 'Pending',
                'completed_at' => $progress?->completed_at
                    ? Carbon::parse($progress->completed_at)->format('M d, Y')
                    : null,
                'notes' => $progress?->notes,
            ];
        }) ?? collect();

        $total = $tasks->count();
        $done = $tasks->where('status', 'Completed')->count();
        $progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        return response()->json([
            'onboarding' => [
                'id' => $ob->id,
                'status' => $ob->status,
                'status_color' => $ob->status_color,
                'progress' => $progress,
                'tasks_total' => $total,
                'tasks_done' => $done,
                'start_date' => Carbon::parse($ob->start_date)->format('M d, Y'),
                'expected_end' => $ob->expected_end_date
                    ? Carbon::parse($ob->expected_end_date)->format('M d, Y')
                    : null,
                'notes' => $ob->notes,
                'employee' => $ob->employee ? [
                    'id' => $ob->employee->id,
                    'name' => trim($ob->employee->first_name . ' ' . $ob->employee->last_name),
                    'emp_id' => $ob->employee->employee_id,
                    'dept' => $ob->employee->department?->name ?? '-',
                ] : null,
                'template' => $ob->template ? [
                    'id' => $ob->template->id,
                    'name' => $ob->template->name,
                ] : null,
                'buddy' => $ob->buddy ? [
                    'id' => $ob->buddy->id,
                    'name' => trim($ob->buddy->first_name . ' ' . $ob->buddy->last_name),
                ] : null,
                'tasks' => $tasks,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $templateId = $request->input('template_id', $request->input('onboarding_template_id'));
        $buddyId = $request->input('buddy_id', $request->input('assigned_buddy_id'));

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'start_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);
        if (! $templateId || ! OnboardingTemplate::whereKey($templateId)->exists()) {
            return response()->json(['message' => 'A valid template is required.'], 422);
        }
        if ($buddyId && ! Employee::whereKey($buddyId)->exists()) {
            return response()->json(['message' => 'Selected buddy is invalid.'], 422);
        }

        $template = OnboardingTemplate::with('tasks')->findOrFail((int) $templateId);
        $expectedEnd = Carbon::parse($request->start_date)->addDays((int) $template->days_to_complete);

        $onboardingPayload = [
            'employee_id' => $request->employee_id,
            'template_id' => $templateId,
            'buddy_id' => $buddyId,
            'start_date' => $request->start_date,
            'expected_end_date' => $expectedEnd->toDateString(),
            'status' => 'Not Started',
            'notes' => $request->notes,
        ];

        if ($this->hasColumn('employee_onboardings', 'onboarding_template_id')) {
            $onboardingPayload['onboarding_template_id'] = $templateId;
        }
        if ($this->hasColumn('employee_onboardings', 'assigned_buddy_id')) {
            $onboardingPayload['assigned_buddy_id'] = $buddyId;
        }

        $onboarding = EmployeeOnboarding::create($onboardingPayload);

        foreach ($template->tasks as $task) {
            $taskPayload = [
                'onboarding_id' => $onboarding->id,
                'task_id' => $task->id,
                'status' => 'Pending',
            ];

            if ($this->hasColumn('onboarding_task_progress', 'employee_onboarding_id')) {
                $taskPayload['employee_onboarding_id'] = $onboarding->id;
            }
            if ($this->hasColumn('onboarding_task_progress', 'onboarding_task_id')) {
                $taskPayload['onboarding_task_id'] = $task->id;
            }

            OnboardingTaskProgress::create($taskPayload);
        }

        $employeeName = Employee::find($request->employee_id)?->first_name ?? 'employee';

        return response()->json([
            'onboarding' => $onboarding->load(['employee', 'template', 'buddy']),
            'message' => 'Onboarding started for ' . $employeeName,
        ], 201);
    }

    public function updateTask(Request $request, int $onboardingId, int $taskId)
    {
        $progress = OnboardingTaskProgress::where('onboarding_id', $onboardingId)
            ->where('task_id', $taskId)
            ->firstOrFail();

        $request->validate([
            'status' => 'required|in:Pending,Completed,Skipped',
            'notes' => 'nullable|string',
        ]);

        $progress->update([
            'status' => $request->status,
            'completed_at' => $request->status === 'Completed' ? now() : null,
            'completed_by' => $request->status === 'Completed' ? auth()->id() : null,
            'notes' => $request->notes,
        ]);

        $onboarding = EmployeeOnboarding::findOrFail($onboardingId);
        $onboarding->recalculateStatus();

        if ($this->hasColumn('employee_onboardings', 'completed_date') && $onboarding->fresh()->status === 'Completed') {
            $onboarding->update([
                'completed_date' => now()->toDateString(),
            ]);
        }

        return response()->json([
            'message' => 'Task updated.',
            'progress' => $onboarding->fresh()->progress_percentage,
            'status' => $onboarding->fresh()->status,
        ]);
    }

    public function assignBuddy(Request $request, int $id)
    {
        $buddyId = $request->input('buddy_id', $request->input('assigned_buddy_id'));

        $request->validate([
            'buddy_id' => 'nullable',
            'assigned_buddy_id' => 'nullable',
        ]);
        if ($buddyId && ! Employee::whereKey($buddyId)->exists()) {
            return response()->json(['message' => 'Selected buddy is invalid.'], 422);
        }

        $ob = EmployeeOnboarding::findOrFail($id);
        $payload = ['buddy_id' => $buddyId];

        if ($this->hasColumn('employee_onboardings', 'assigned_buddy_id')) {
            $payload['assigned_buddy_id'] = $buddyId;
        }

        $ob->update($payload);

        return response()->json([
            'message' => $buddyId ? 'Buddy assigned.' : 'Buddy removed.',
        ]);
    }

    public function destroy(int $id)
    {
        $ob = EmployeeOnboarding::findOrFail($id);
        $ob->taskProgress()->delete();
        $ob->delete();

        return response()->json([
            'message' => 'Onboarding record deleted.',
        ]);
    }

    public function templates()
    {
        $templates = OnboardingTemplate::with('tasks')
            ->withCount(['onboardings', 'tasks'])
            ->orderBy('name')
            ->get()
            ->map(fn (OnboardingTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'days_to_complete' => $t->days_to_complete,
                'status' => $t->status,
                'tasks_count' => $t->tasks_count,
                'onboardings_count' => $t->onboardings_count,
                'tasks' => $t->tasks->map(fn ($tk) => [
                    'id' => $tk->id,
                    'title' => $tk->title,
                    'category' => $tk->category,
                    'due_days' => $tk->due_days,
                    'required' => (bool) $tk->required,
                    'sort_order' => $tk->sort_order,
                ]),
            ]);

        return response()->json(['templates' => $templates]);
    }

    public function storeTemplate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:onboarding_templates,name',
            'description' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
            'days_to_complete' => 'required|integer|min:1',
            'status' => 'required|string',
            'tasks' => 'array',
            'tasks.*.title' => 'required|string',
            'tasks.*.due_days' => 'required|integer|min:1',
        ]);

        $template = OnboardingTemplate::create($request->except('tasks'));

        foreach ($request->tasks ?? [] as $i => $task) {
            $required = (bool) ($task['required'] ?? $task['is_required'] ?? true);
            $payload = [
                'title' => $task['title'],
                'description' => $task['description'] ?? null,
                'category' => $task['category'] ?? null,
                'due_days' => $task['due_days'],
                'required' => $required,
                'sort_order' => $i,
            ];

            if ($this->hasColumn('onboarding_tasks', 'template_id')) {
                $payload['template_id'] = $template->id;
            }
            if ($this->hasColumn('onboarding_tasks', 'onboarding_template_id')) {
                $payload['onboarding_template_id'] = $template->id;
            }
            if ($this->hasColumn('onboarding_tasks', 'is_required')) {
                $payload['is_required'] = $required;
            }

            $template->tasks()->create($payload);
        }

        return response()->json([
            'template' => $template->load('tasks'),
            'message' => 'Template created.',
        ], 201);
    }

    public function updateTemplate(Request $request, int $id)
    {
        $template = OnboardingTemplate::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:onboarding_templates,name,' . $id,
            'description' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
            'days_to_complete' => 'required|integer|min:1',
            'status' => 'required|string',
            'tasks' => 'nullable|array',
        ]);

        $template->update($request->except('tasks'));

        if ($request->has('tasks')) {
            $template->tasks()->delete();

            foreach ($request->tasks as $i => $task) {
                $required = (bool) ($task['required'] ?? $task['is_required'] ?? true);
                $payload = [
                    'title' => $task['title'],
                    'description' => $task['description'] ?? null,
                    'category' => $task['category'] ?? null,
                    'due_days' => $task['due_days'] ?? 1,
                    'required' => $required,
                    'sort_order' => $i,
                ];

                if ($this->hasColumn('onboarding_tasks', 'template_id')) {
                    $payload['template_id'] = $template->id;
                }
                if ($this->hasColumn('onboarding_tasks', 'onboarding_template_id')) {
                    $payload['onboarding_template_id'] = $template->id;
                }
                if ($this->hasColumn('onboarding_tasks', 'is_required')) {
                    $payload['is_required'] = $required;
                }

                $template->tasks()->create($payload);
            }
        }

        return response()->json([
            'template' => $template->fresh()->load('tasks'),
            'message' => 'Template updated.',
        ]);
    }

    public function destroyTemplate(int $id)
    {
        $template = OnboardingTemplate::withCount('onboardings')->findOrFail($id);

        if ($template->onboardings_count > 0) {
            return response()->json([
                'message' => 'Cannot delete template in use by ' . $template->onboardings_count . ' onboardings.',
            ], 422);
        }

        $template->tasks()->delete();
        $template->delete();

        return response()->json([
            'message' => 'Template deleted.',
        ]);
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;

        if (! array_key_exists($key, $cache)) {
            $cache[$key] = Schema::hasColumn($table, $column);
        }

        return $cache[$key];
    }
}
