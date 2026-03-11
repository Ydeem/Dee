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
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        $query = Attendance::with([
                'employee:id,first_name,last_name,employee_id,avatar,department_id',
                'employee.department:id,name',
            ])
            ->whereHas('employee')
            ->forMonth($month, $year)
            ->when($request->search, fn ($q) => $q->whereHas('employee', fn ($employeeQuery) => $employeeQuery
                ->where('first_name', 'like', '%' . $request->search . '%')
                ->orWhere('last_name', 'like', '%' . $request->search . '%')
                ->orWhere('employee_id', 'like', '%' . $request->search . '%')))
            ->when($request->department, fn ($q) => $q->whereHas('employee.department', fn ($departmentQuery) => $departmentQuery->where('name', $request->department)))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->from, fn ($q) => $q->whereDate('date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('date', '<=', $request->to));

        $records = $query
            ->orderBy('date', 'desc')
            ->orderBy('employee_id')
            ->paginate((int) ($request->per_page ?? 10))
            ->through(function (Attendance $record) {
                return [
                    'id' => $record->id,
                    'date' => Carbon::parse($record->date)->format('M d, Y'),
                    'date_raw' => $record->date->format('Y-m-d'),
                    'day' => Carbon::parse($record->date)->format('D'),
                    'check_in' => $record->check_in ? Carbon::parse($record->check_in)->format('h:i A') : '-',
                    'check_out' => $record->check_out ? Carbon::parse($record->check_out)->format('h:i A') : '-',
                    'hours_worked' => $record->hours_worked ? $record->hours_worked . 'h' : '-',
                    'status' => $record->status,
                    'status_color' => $record->status_color,
                    'note' => $record->note,
                    'employee' => $record->employee
                        ? [
                            'id' => $record->employee->id,
                            'name' => trim($record->employee->first_name . ' ' . $record->employee->last_name),
                            'employee_id' => $record->employee->employee_id,
                            'avatar' => $record->employee->avatar ? asset('storage/' . $record->employee->avatar) : null,
                            'initials' => strtoupper(
                                substr((string) $record->employee->first_name, 0, 1)
                                . substr((string) $record->employee->last_name, 0, 1)
                            ),
                            'department' => $record->employee->department?->name ?? '-',
                        ]
                        : null,
                ];
            });

        return response()->json([
            'records' => $records,
            'today' => $this->getTodayStats(),
            'filters' => [
                'departments' => Department::active()->orderBy('name')->pluck('name'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'status' => 'required|in:Present,Absent,Late,Half Day,On Leave,Holiday',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i|after:check_in',
            'note' => 'nullable|string|max:500',
        ]);

        $existing = Attendance::where('employee_id', $validated['employee_id'])
            ->whereDate('date', $validated['date'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Attendance already marked for this employee on ' . Carbon::parse($validated['date'])->format('M d, Y') . '. Use edit to update it.',
            ], 422);
        }

        $attendance = Attendance::create([
            ...$validated,
            'check_in' => ! empty($validated['check_in']) ? $validated['date'] . ' ' . $validated['check_in'] : null,
            'check_out' => ! empty($validated['check_out']) ? $validated['date'] . ' ' . $validated['check_out'] : null,
            'marked_by' => $this->currentMarkerId(),
        ]);

        return response()->json([
            'attendance' => $attendance->load('employee'),
            'message' => 'Attendance marked successfully.',
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:Present,Absent,Late,Half Day,On Leave,Holiday',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i|after:check_in',
            'note' => 'nullable|string|max:500',
        ]);

        $attendance->update([
            'status' => $validated['status'],
            'check_in' => ! empty($validated['check_in']) ? $attendance->date->format('Y-m-d') . ' ' . $validated['check_in'] : null,
            'check_out' => ! empty($validated['check_out']) ? $attendance->date->format('Y-m-d') . ' ' . $validated['check_out'] : null,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'attendance' => $attendance->fresh()->load('employee'),
            'message' => 'Attendance updated.',
        ]);
    }

    public function destroy($id)
    {
        Attendance::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Record deleted.',
        ]);
    }

    public function bulkMark(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'status' => 'required|in:Present,Absent,Late,Half Day,On Leave,Holiday',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
        ]);

        $marked = 0;
        $skipped = 0;

        foreach ($validated['employee_ids'] as $employeeId) {
            $exists = Attendance::where('employee_id', $employeeId)
                ->whereDate('date', $validated['date'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Attendance::create([
                'employee_id' => $employeeId,
                'date' => $validated['date'],
                'status' => $validated['status'],
                'check_in' => ! empty($validated['check_in']) ? $validated['date'] . ' ' . $validated['check_in'] : null,
                'check_out' => ! empty($validated['check_out']) ? $validated['date'] . ' ' . $validated['check_out'] : null,
                'marked_by' => $this->currentMarkerId(),
            ]);

            $marked++;
        }

        return response()->json([
            'message' => "Marked {$marked} employees. " . ($skipped > 0 ? "{$skipped} already had records." : ''),
            'marked' => $marked,
            'skipped' => $skipped,
        ]);
    }

    public function todayAttendance()
    {
        $today = now()->toDateString();
        $employees = Employee::with('department:id,name')
            ->where('employment_status', 'Active')
            ->orderBy('first_name')
            ->get();

        $todayRecords = Attendance::whereDate('date', $today)->get()->keyBy('employee_id');

        $result = $employees->map(function (Employee $employee) use ($todayRecords) {
            $record = $todayRecords[$employee->id] ?? null;

            return [
                'employee_id' => $employee->id,
                'name' => trim($employee->first_name . ' ' . $employee->last_name),
                'emp_id' => $employee->employee_id,
                'department' => $employee->department?->name ?? '-',
                'avatar' => $employee->avatar ? asset('storage/' . $employee->avatar) : null,
                'initials' => strtoupper(substr((string) $employee->first_name, 0, 1) . substr((string) $employee->last_name, 0, 1)),
                'attendance_id' => $record?->id,
                'status' => $record?->status ?? 'Not Marked',
                'check_in' => $record?->check_in,
                'check_out' => $record?->check_out,
                'is_marked' => ! is_null($record),
            ];
        });

        return response()->json([
            'employees' => $result,
            'date' => Carbon::parse($today)->format('M d, Y'),
            'total' => $employees->count(),
            'marked' => $todayRecords->count(),
            'unmarked' => $employees->count() - $todayRecords->count(),
        ]);
    }

    private function getTodayStats(): array
    {
        $today = now()->toDateString();

        return [
            'present' => Attendance::whereDate('date', $today)->where('status', 'Present')->count(),
            'absent' => Attendance::whereDate('date', $today)->where('status', 'Absent')->count(),
            'late' => Attendance::whereDate('date', $today)->where('status', 'Late')->count(),
            'on_leave' => Attendance::whereDate('date', $today)->where('status', 'On Leave')->count(),
        ];
    }

    private function currentMarkerId(): ?int
    {
        $user = auth()->user();

        if (! $user?->email) {
            return null;
        }

        return Employee::where('work_email', $user->email)
            ->orWhere('personal_email', $user->email)
            ->value('id');
    }
}
