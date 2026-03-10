<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Designation;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    public function index(Request $request)
    {
        $query = Designation::withCount('employees')
            ->with('department')
            ->when($request->search, fn ($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
            )
            ->when($request->department, fn ($q) =>
                $q->whereHas('department', fn ($sub) => $sub->where('name', $request->department))
            )
            ->when($request->level, fn ($q) =>
                $q->where('level', $request->level)
            )
            ->when($request->status, fn ($q) =>
                $q->where('status', $request->status)
            );

        $sortBy = $request->sort_by ?? 'created_at';
        $sortDir = $request->sort_dir ?? 'desc';

        if (in_array($sortBy, ['name', 'level', 'created_at', 'employees_count', 'status'], true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $designations = $query->paginate((int) ($request->per_page ?? 10));

        return response()->json([
            'designations' => $designations,
            'departments' => \App\Models\HR\Department::pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:designations,name',
            'department_id' => 'nullable|exists:departments,id',
            'level' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);

        $designation = Designation::create($validated);
        return response()->json(['designation' => $designation], 201);
    }

    public function update(Request $request, $id)
    {
        $designation = Designation::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|unique:designations,name,' . $id,
            'department_id' => 'nullable|exists:departments,id',
            'level' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);

        $designation->update($validated);
        return response()->json(['designation' => $designation]);
    }

    public function destroy($id)
    {
        $designation = Designation::withCount('employees')->findOrFail($id);

        if ($designation->employees_count > 0) {
            return response()->json([
                'message' => 'Cannot delete a designation assigned to employees.'
            ], 422);
        }

        $designation->delete();
        return response()->json(['message' => 'Designation deleted.']);
    }
}
