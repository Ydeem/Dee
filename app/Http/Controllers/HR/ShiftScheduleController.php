<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\HR\Shift;
use App\Models\HR\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ShiftScheduleController extends Controller
{
    public function index(Request $request)
    {
        $query = ShiftSchedule::with(['employee.department', 'shift'])
            ->when($request->search, fn ($q) =>
                $q->whereHas('employee', fn ($employeeQuery) =>
                    $employeeQuery->where('first_name', 'like', '%' . $request->search . '%')
                        ->orWhere('last_name', 'like', '%' . $request->search . '%')
                        ->orWhere('employee_id', 'like', '%' . $request->search . '%')
                )
            )
            ->when($request->shift_id, fn ($q) => $q->where('shift_id', $request->shift_id))
            ->when($request->department, fn ($q) =>
                $q->whereHas('employee.department', fn ($departmentQuery) =>
                    $departmentQuery->where('name', $request->department)
                )
            )
            ->when($request->active_only, fn ($q) =>
                $q->where(function ($sub) {
                    $sub->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', now());
                })
            );

        $schedules = $query->orderBy('effective_from', 'desc')
            ->paginate((int) ($request->per_page ?? 10));

        $assignedEmployees = ShiftSchedule::where(function ($q) {
            $q->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', now());
        })->distinct('employee_id')->count('employee_id');

        $unassignedEmployees = Employee::where('employment_status', 'Active')
            ->whereDoesntHave('schedules', function ($q) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', now());
            })->count();

        $summary = [
            'total_shifts' => Shift::where('status', 'Active')->count(),
            'assigned_employees' => $assignedEmployees,
            'unassigned_employees' => $unassignedEmployees,
        ];

        return response()->json([
            'schedules' => $schedules,
            'summary' => $summary,
            'shifts' => Shift::where('status', 'Active')->get(),
            'departments' => \App\Models\HR\Department::pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'note' => 'nullable|string',
        ]);

        ShiftSchedule::where('employee_id', $validated['employee_id'])
            ->whereNull('effective_to')
            ->update(['effective_to' => Carbon::parse($validated['effective_from'])->subDay()->toDateString()]);

        $schedule = ShiftSchedule::create($validated);

        Employee::where('id', $validated['employee_id'])->update(['shift_id' => $validated['shift_id']]);

        return response()->json(['schedule' => $schedule], 201);
    }

    public function update(Request $request, int $id)
    {
        $schedule = ShiftSchedule::findOrFail($id);

        $validated = $request->validate([
            'shift_id' => 'required|exists:shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'note' => 'nullable|string',
        ]);

        $schedule->update($validated);
        Employee::where('id', $schedule->employee_id)->update(['shift_id' => $validated['shift_id']]);

        return response()->json(['schedule' => $schedule]);
    }

    public function destroy(int $id)
    {
        ShiftSchedule::findOrFail($id)->delete();
        return response()->json(['message' => 'Schedule removed.']);
    }

    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'effective_from' => 'required|date',
            'note' => 'nullable|string',
        ]);

        foreach ($validated['employee_ids'] as $employeeId) {
            ShiftSchedule::where('employee_id', $employeeId)
                ->whereNull('effective_to')
                ->update(['effective_to' => Carbon::parse($validated['effective_from'])->subDay()->toDateString()]);

            ShiftSchedule::create([
                'employee_id' => $employeeId,
                'shift_id' => $validated['shift_id'],
                'effective_from' => $validated['effective_from'],
                'effective_to' => null,
                'note' => $validated['note'] ?? null,
            ]);

            Employee::where('id', $employeeId)->update(['shift_id' => $validated['shift_id']]);
        }

        return response()->json([
            'message' => 'Shift assigned to ' . count($validated['employee_ids']) . ' employees.'
        ]);
    }
}
