<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $shifts = Shift::withCount('employees')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('name')
            ->get();

        return response()->json(['shifts' => $shifts]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:shifts,name',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_duration' => 'nullable|integer|min:0',
            'working_days' => 'required|array|min:1',
            'working_days.*' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'color' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);

        $shift = Shift::create($validated);
        return response()->json(['shift' => $shift], 201);
    }

    public function update(Request $request, int $id)
    {
        $shift = Shift::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|unique:shifts,name,' . $id,
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_duration' => 'nullable|integer|min:0',
            'working_days' => 'nullable|array|min:1',
            'working_days.*' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'color' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);

        $shift->update($validated);
        return response()->json(['shift' => $shift]);
    }

    public function destroy(int $id)
    {
        $shift = Shift::withCount('employees')->findOrFail($id);
        if ($shift->employees_count > 0) {
            return response()->json(['message' => 'Cannot delete a shift assigned to employees.'], 422);
        }

        $shift->delete();
        return response()->json(['message' => 'Shift deleted.']);
    }
}
