<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Department;
use App\Models\HR\Designation;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    public const LEVELS = [
        'Junior', 'Mid-level', 'Senior',
        'Lead', 'Manager', 'Director', 'C-Level',
    ];

    public function index(Request $request)
    {
        $query = Designation::with('department:id,name')
            ->withCount('employees')
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($request->department, fn ($q) => $q->whereHas('department', fn ($sub) => $sub->where('name', $request->department)))
            ->when($request->level, fn ($q) => $q->where('level', $request->level))
            ->when($request->status, fn ($q) => $q->where('status', $request->status));

        $sortBy = in_array($request->sort_by, ['name', 'level', 'created_at', 'employees_count'], true)
            ? $request->sort_by
            : 'name';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';

        if ($sortBy === 'employees_count') {
            $query->orderBy('employees_count', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $designations = $query
            ->paginate((int) ($request->per_page ?? 10))
            ->through(function (Designation $designation) {
                return [
                    'id' => $designation->id,
                    'name' => $designation->name,
                    'initials' => $designation->initials,
                    'description' => $designation->description,
                    'level' => $designation->level,
                    'level_color' => $designation->level_color,
                    'status' => $designation->status,
                    'min_salary' => $designation->min_salary,
                    'max_salary' => $designation->max_salary,
                    'employee_count' => $designation->employees_count,
                    'created_at' => optional($designation->created_at)?->format('M d, Y'),
                    'department' => $designation->department
                        ? [
                            'id' => $designation->department->id,
                            'name' => $designation->department->name,
                        ]
                        : null,
                ];
            });

        return response()->json([
            'designations' => $designations,
            'stats' => [
                'total' => Designation::count(),
                'active' => Designation::where('status', 'Active')->count(),
                'unassigned' => Designation::whereNull('department_id')->count(),
            ],
            'filters' => [
                'departments' => Department::active()->orderBy('name')->pluck('name'),
                'levels' => self::LEVELS,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'department_id' => 'nullable|exists:departments,id',
            'level' => 'nullable|in:' . implode(',', self::LEVELS),
            'description' => 'nullable|string|max:500',
            'status' => 'required|in:Active,Inactive',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
        ]);

        $designation = Designation::create($validated);

        return response()->json([
            'designation' => $designation->load('department'),
            'message' => 'Designation created successfully.',
        ], 201);
    }

    public function show($id)
    {
        $designation = Designation::with([
            'department:id,name',
            'employees' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'employee_id', 'employment_status', 'avatar', 'designation_id')
                    ->where('employment_status', '!=', 'Inactive');
            },
        ])->withCount('employees')->findOrFail($id);

        return response()->json([
            'designation' => $designation,
        ]);
    }

    public function update(Request $request, $id)
    {
        $designation = Designation::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'department_id' => 'nullable|exists:departments,id',
            'level' => 'nullable|in:' . implode(',', self::LEVELS),
            'description' => 'nullable|string|max:500',
            'status' => 'required|in:Active,Inactive',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
        ]);

        $designation->update($validated);

        return response()->json([
            'designation' => $designation->fresh()->load('department'),
            'message' => 'Designation updated successfully.',
        ]);
    }

    public function destroy($id)
    {
        $designation = Designation::withCount('employees')->findOrFail($id);

        if ($designation->employees_count > 0) {
            return response()->json([
                'message' => 'Cannot delete designation. ' . $designation->employees_count . ' employee(s) have this designation. Please reassign them first.',
            ], 422);
        }

        $designation->delete();
        return response()->json(['message' => 'Designation deleted successfully.']);
    }

    public function updateStatus(Request $request, $id)
    {
        $designation = Designation::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:Active,Inactive',
        ]);

        $designation->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Status updated to ' . $validated['status'],
        ]);
    }
}
