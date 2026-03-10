<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::withCount('employees')
            ->with('manager')
            ->when($request->search, fn ($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('code', 'like', '%' . $request->search . '%')
            )
            ->when($request->status, fn ($q) =>
                $q->where('status', $request->status)
            );

        $sortBy = $request->sort_by ?? 'created_at';
        $sortDir = $request->sort_dir ?? 'desc';

        if (in_array($sortBy, ['name', 'created_at', 'employees_count', 'code', 'status'], true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $departments = $query->paginate((int) ($request->per_page ?? 10));

        return response()->json(['departments' => $departments]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:departments,name',
            'code' => 'nullable|string|unique:departments,code',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:employees,id',
            'status' => 'required|in:Active,Inactive',
        ]);

        $department = Department::create($validated);
        return response()->json(['department' => $department], 201);
    }

    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|unique:departments,name,' . $id,
            'code' => 'nullable|string|unique:departments,code,' . $id,
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:employees,id',
            'status' => 'required|in:Active,Inactive',
        ]);

        $department->update($validated);
        return response()->json(['department' => $department]);
    }

    public function destroy($id)
    {
        $department = Department::withCount('employees')->findOrFail($id);

        if ($department->employees_count > 0) {
            return response()->json([
                'message' => 'Cannot delete department with active employees.'
            ], 422);
        }

        $department->delete();
        return response()->json(['message' => 'Department deleted.']);
    }
}
