<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\OnboardingTask;
use App\Models\HR\OnboardingTemplate;
use Illuminate\Http\Request;

class OnboardingTemplateController extends Controller
{
    public function index()
    {
        $templates = OnboardingTemplate::with([
            'tasks' => fn ($q) => $q->orderBy('sort_order'),
            'department',
            'designation',
        ])->withCount('tasks')->get();

        return response()->json(['templates' => $templates]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:onboarding_templates,name',
            'description' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'status' => 'required|in:Active,Inactive',
        ]);

        $template = OnboardingTemplate::create($validated);
        return response()->json(['template' => $template], 201);
    }

    public function update(Request $request, int $id)
    {
        $template = OnboardingTemplate::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|unique:onboarding_templates,name,' . $id,
            'description' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'status' => 'required|in:Active,Inactive',
        ]);
        $template->update($validated);
        return response()->json(['template' => $template]);
    }

    public function destroy(int $id)
    {
        OnboardingTemplate::findOrFail($id)->delete();
        return response()->json(['message' => 'Template deleted.']);
    }

    public function addTask(Request $request, int $id)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'due_days' => 'required|integer|min:1',
            'assigned_to_role' => 'nullable|string',
            'is_required' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $task = OnboardingTask::create([
            ...$validated,
            'onboarding_template_id' => $id,
            'sort_order' => $validated['sort_order'] ?? (OnboardingTask::where('onboarding_template_id', $id)->max('sort_order') + 1),
        ]);

        return response()->json(['task' => $task], 201);
    }

    public function updateTask(Request $request, int $id, int $taskId)
    {
        $task = OnboardingTask::where('onboarding_template_id', $id)->findOrFail($taskId);
        $validated = $request->validate([
            'title' => 'sometimes|required|string',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'due_days' => 'sometimes|required|integer|min:1',
            'assigned_to_role' => 'nullable|string',
            'is_required' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);
        $task->update($validated);
        return response()->json(['task' => $task]);
    }

    public function deleteTask(int $id, int $taskId)
    {
        OnboardingTask::where('onboarding_template_id', $id)->findOrFail($taskId)->delete();
        return response()->json(['message' => 'Task removed.']);
    }
}

