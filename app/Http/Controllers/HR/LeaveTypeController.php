<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function index()
    {
        $types = LeaveType::withCount('leaveRequests')
            ->orderBy('name')
            ->get();

        return response()->json(['leave_types' => $types]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:leave_types,name',
            'days_allowed' => 'required|integer|min:1',
            'carry_forward' => 'boolean',
            'requires_approval' => 'boolean',
            'color' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);

        $type = LeaveType::create($validated);
        return response()->json(['leave_type' => $type], 201);
    }

    public function update(Request $request, int $id)
    {
        $type = LeaveType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|unique:leave_types,name,' . $id,
            'days_allowed' => 'required|integer|min:1',
            'carry_forward' => 'boolean',
            'requires_approval' => 'boolean',
            'color' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);

        $type->update($validated);
        return response()->json(['leave_type' => $type]);
    }

    public function destroy(int $id)
    {
        $type = LeaveType::withCount('leaveRequests')->findOrFail($id);
        if ($type->leave_requests_count > 0) {
            return response()->json([
                'message' => 'Cannot delete a leave type with existing requests.'
            ], 422);
        }

        $type->delete();
        return response()->json(['message' => 'Leave type deleted.']);
    }
}
