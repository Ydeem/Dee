<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with(['employee.department'])
            ->when($request->search, fn ($q) =>
                $q->whereHas('employee', fn ($employeeQuery) =>
                    $employeeQuery->where('first_name', 'like', '%' . $request->search . '%')
                        ->orWhere('last_name', 'like', '%' . $request->search . '%')
                        ->orWhere('employee_id', 'like', '%' . $request->search . '%')
                )
            )
            ->when($request->department, fn ($q) =>
                $q->whereHas('employee.department', fn ($departmentQuery) =>
                    $departmentQuery->where('name', $request->department)
                )
            )
            ->when($request->status, fn ($q) =>
                $q->where('status', $request->status)
            )
            ->when($request->date_from, fn ($q) =>
                $q->whereDate('date', '>=', $request->date_from)
            )
            ->when($request->date_to, fn ($q) =>
                $q->whereDate('date', '<=', $request->date_to)
            )
            ->when($request->month, fn ($q) =>
                $q->whereMonth('date', Carbon::parse($request->month)->month)
                    ->whereYear('date', Carbon::parse($request->month)->year)
            );

        $sortBy = $request->query('sort_by', 'date');
        $sortDir = $request->query('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'employee_name') {
            $query->join('employees', 'employees.id', '=', 'attendance.employee_id')
                ->orderBy('employees.first_name', $sortDir)
                ->select('attendance.*');
        } elseif ($sortBy === 'hours_worked') {
            $query->orderBy('hours_worked', $sortDir);
        } else {
            $query->orderBy('date', $sortDir);
        }

        $records = $query->paginate((int) ($request->per_page ?? 10));

        $today = now()->toDateString();
        $summary = [
            'present' => Attendance::whereDate('date', $today)->where('status', 'Present')->count(),
            'absent' => Attendance::whereDate('date', $today)->where('status', 'Absent')->count(),
            'late' => Attendance::whereDate('date', $today)->where('status', 'Late')->count(),
            'on_leave' => Attendance::whereDate('date', $today)->where('status', 'On Leave')->count(),
        ];

        return response()->json([
            'attendance' => $records,
            'summary' => $summary,
            'departments' => Department::pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:Present,Absent,Late,Half Day,On Leave,Holiday',
            'note' => 'nullable|string',
        ]);

        $hoursWorked = $this->calculateHours($validated['date'], $validated['check_in'] ?? null, $validated['check_out'] ?? null);

        $attendance = Attendance::updateOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'date' => $validated['date'],
            ],
            [
                'check_in' => !empty($validated['check_in']) ? $validated['date'] . ' ' . $validated['check_in'] : null,
                'check_out' => !empty($validated['check_out']) ? $validated['date'] . ' ' . $validated['check_out'] : null,
                'hours_worked' => $hoursWorked,
                'status' => $validated['status'],
                'note' => $validated['note'] ?? null,
            ]
        );

        return response()->json(['attendance' => $attendance], 201);
    }

    public function update(Request $request, int $id)
    {
        $attendance = Attendance::findOrFail($id);

        $validated = $request->validate([
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:Present,Absent,Late,Half Day,On Leave,Holiday',
            'note' => 'nullable|string',
        ]);

        $hoursWorked = $this->calculateHours($attendance->date->toDateString(), $validated['check_in'] ?? null, $validated['check_out'] ?? null);

        $attendance->update([
            'check_in' => !empty($validated['check_in']) ? $attendance->date->toDateString() . ' ' . $validated['check_in'] : null,
            'check_out' => !empty($validated['check_out']) ? $attendance->date->toDateString() . ' ' . $validated['check_out'] : null,
            'hours_worked' => $hoursWorked,
            'status' => $validated['status'],
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json(['attendance' => $attendance]);
    }

    public function destroy(int $id)
    {
        Attendance::findOrFail($id)->delete();
        return response()->json(['message' => 'Record deleted.']);
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'status' => 'required|in:Present,Absent,Late,Holiday',
        ]);

        $employeeIds = Employee::where('employment_status', 'Active')->pluck('id');

        foreach ($employeeIds as $employeeId) {
            Attendance::firstOrCreate(
                ['employee_id' => $employeeId, 'date' => $validated['date']],
                ['status' => $validated['status']]
            );
        }

        return response()->json([
            'message' => 'Bulk attendance marked for ' . $employeeIds->count() . ' employees.',
            'count' => $employeeIds->count(),
        ]);
    }

    private function calculateHours(string $date, ?string $checkIn, ?string $checkOut): ?float
    {
        if (!$checkIn || !$checkOut) {
            return null;
        }

        $in = Carbon::parse($date . ' ' . $checkIn);
        $out = Carbon::parse($date . ' ' . $checkOut);

        if ($out->lessThanOrEqualTo($in)) {
            return null;
        }

        return round($out->diffInMinutes($in) / 60, 2);
    }
}
