<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::with([
                'manager:id,first_name,last_name,avatar',
            ])
            ->withCount('employees')
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($request->status, fn ($q) => $q->where('status', $request->status));

        $sortBy = in_array($request->sort_by, ['name', 'code', 'created_at', 'employees_count'], true)
            ? $request->sort_by
            : 'name';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';

        if ($sortBy === 'employees_count') {
            $query->orderBy('employees_count', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $departments = $query
            ->paginate((int) ($request->per_page ?? 10))
            ->through(function (Department $department) {
                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code ?? '—',
                    'description' => $department->description,
                    'status' => $department->status,
                    'color' => $department->color,
                    'initials' => $department->initials,
                    'employee_count' => $department->employees_count,
                    'created_at' => optional($department->created_at)?->format('M d, Y'),
                    'manager' => $department->manager
                        ? [
                            'id' => $department->manager->id,
                            'name' => trim($department->manager->first_name . ' ' . $department->manager->last_name),
                            'avatar' => $department->manager->avatar
                                ? asset('storage/' . $department->manager->avatar)
                                : null,
                            'initials' => strtoupper(
                                substr((string) $department->manager->first_name, 0, 1)
                                . substr((string) $department->manager->last_name, 0, 1)
                            ),
                        ]
                        : null,
                ];
            });

        return response()->json([
            'departments' => $departments,
            'stats' => [
                'total' => Department::count(),
                'active' => Department::where('status', 'Active')->count(),
                'without_manager' => Department::whereNull('manager_id')->count(),
            ],
            'managers' => Employee::where('employment_status', 'Active')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => trim($employee->first_name . ' ' . $employee->last_name),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:departments,name',
            'code' => 'nullable|string|max:10|unique:departments,code',
            'description' => 'nullable|string|max:500',
            'manager_id' => 'nullable|exists:employees,id',
            'parent_id' => 'nullable|exists:departments,id',
            'status' => 'required|in:Active,Inactive',
            'color' => 'nullable|string|max:7',
        ]);

        $code = $validated['code'] ?? null;

        if (! $code) {
            $words = preg_split('/\s+/', trim($validated['name'])) ?: [];
            $code = strtoupper(implode('', array_map(fn ($word) => substr($word, 0, 1), $words)));
            $original = $code ?: 'DEP';
            $suffix = 1;

            while (Department::where('code', $code)->exists()) {
                $code = $original . $suffix;
                $suffix++;
            }
        }

        $department = Department::create([
            ...$validated,
            'code' => strtoupper($code),
        ]);

        return response()->json([
            'department' => $department->load('manager'),
            'message' => 'Department created successfully.',
        ], 201);
    }

    public function show($id)
    {
        $department = Department::with([
            'manager:id,first_name,last_name,avatar',
            'employees' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'employee_id', 'designation_id', 'employment_status', 'avatar', 'department_id')
                    ->with('designation:id,name')
                    ->where('employment_status', '!=', 'Inactive');
            },
            'parent:id,name',
            'children:id,name,code,parent_id',
        ])->withCount('employees')->findOrFail($id);

        return response()->json([
            'department' => $department,
        ]);
    }

    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:departments,name,' . $id,
            'code' => 'nullable|string|max:10|unique:departments,code,' . $id,
            'description' => 'nullable|string|max:500',
            'manager_id' => 'nullable|exists:employees,id',
            'parent_id' => 'nullable|exists:departments,id',
            'status' => 'required|in:Active,Inactive',
            'color' => 'nullable|string|max:7',
        ]);

        $department->update([
            ...$validated,
            'code' => ! empty($validated['code'])
                ? strtoupper($validated['code'])
                : $department->code,
        ]);

        return response()->json([
            'department' => $department->fresh()->load('manager'),
            'message' => 'Department updated successfully.',
        ]);
    }

    public function destroy($id)
    {
        $department = Department::withCount('employees')->findOrFail($id);

        if ($department->employees_count > 0) {
            return response()->json([
                'message' => 'Cannot delete department. ' . $department->employees_count . ' employee(s) are assigned to it. Please reassign them first.',
            ], 422);
        }

        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully.',
        ]);
    }

    public function assignManager(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'manager_id' => 'required|exists:employees,id',
        ]);

        $department->update([
            'manager_id' => $validated['manager_id'],
        ]);

        return response()->json([
            'message' => 'Manager assigned successfully.',
            'department' => $department->fresh()->load('manager'),
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:Active,Inactive',
        ]);

        $department->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Status updated to ' . $validated['status'],
        ]);
    }
}
