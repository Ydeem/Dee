<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Shift;
use App\Models\HR\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $query = ShiftSchedule::with([
            'employee:id,first_name,last_name,employee_id,avatar,department_id',
            'employee.department:id,name',
            'shift:id,name,start_time,end_time,color,working_days,break_duration',
        ])
            ->when($request->active_only !== 'false', fn ($q) => $q->current())
            ->when($request->search, function ($q) use ($request) {
                $search = trim((string) $request->search);

                $q->whereHas('employee', function ($employeeQuery) use ($search) {
                    $employeeQuery->where(function ($nested) use ($search) {
                        $nested->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhere('employee_id', 'like', '%' . $search . '%');
                    });
                });
            })
            ->when($request->shift_id, fn ($q) => $q->where('shift_id', $request->shift_id))
            ->when($request->department, fn ($q) => $q->whereHas('employee.department', fn ($departmentQuery) => $departmentQuery->where('name', $request->department)));

        $schedules = $query
            ->orderByDesc('effective_from')
            ->paginate((int) ($request->per_page ?? 10));

        $schedules->through(function (ShiftSchedule $schedule) {
            return [
                'id' => $schedule->id,
                'effective_from' => optional($schedule->effective_from)?->format('M d, Y'),
                'effective_from_raw' => optional($schedule->effective_from)?->format('Y-m-d'),
                'effective_to' => $schedule->effective_to_label,
                'effective_to_raw' => optional($schedule->effective_to)?->format('Y-m-d'),
                'status' => $schedule->status,
                'note' => $schedule->note,
                'employee' => $schedule->employee ? [
                    'id' => $schedule->employee->id,
                    'name' => $schedule->employee->full_name,
                    'employee_id' => $schedule->employee->employee_id,
                    'avatar' => $schedule->employee->avatar_url,
                    'initials' => $schedule->employee->initials,
                    'department' => $schedule->employee->department?->name ?? '-',
                ] : null,
                'shift' => $schedule->shift ? [
                    'id' => $schedule->shift->id,
                    'name' => $schedule->shift->name,
                    'color' => $schedule->shift->color ?? '#4f6ef7',
                    'schedule_label' => $schedule->shift->schedule_label,
                    'working_days' => $this->normalizeWorkingDays($schedule->shift->working_days),
                ] : null,
            ];
        });

        $totalActive = Shift::active()->count();
        $assigned = ShiftSchedule::current()->distinct('employee_id')->count('employee_id');
        $totalEmployees = Employee::active()->count();
        $unassigned = max(0, $totalEmployees - $assigned);

        return response()->json([
            'schedules' => $schedules,
            'stats' => [
                'active_shifts' => $totalActive,
                'assigned' => $assigned,
                'unassigned' => $unassigned,
            ],
            'shifts' => Shift::active()
                ->orderBy('name')
                ->get()
                ->map(fn (Shift $shift) => $this->serializeShift($shift))
                ->values(),
            'departments' => Department::active()->orderBy('name')->pluck('name')->values(),
        ]);
    }

    public function weeklyView(Request $request)
    {
        $weekStart = $request->week_start
            ? Carbon::parse($request->week_start)->startOfWeek()
            : now()->startOfWeek();

        $days = collect(range(0, 6))
            ->map(fn (int $offset) => $weekStart->copy()->addDays($offset))
            ->values();

        $schedules = ShiftSchedule::with([
            'employee:id,first_name,last_name,employee_id,department_id',
            'shift:id,name,color,start_time,end_time,working_days',
        ])
            ->current()
            ->get()
            ->groupBy('employee_id');

        $grid = Employee::active()
            ->with('department:id,name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Employee $employee) use ($days, $schedules) {
                $employeeSchedule = $schedules->get($employee->id)?->first();

                return [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->full_name,
                        'emp_id' => $employee->employee_id,
                        'initials' => $employee->initials,
                        'dept' => $employee->department?->name,
                    ],
                    'days' => $days->map(function (Carbon $day) use ($employeeSchedule) {
                        $dayCode = $day->format('D');
                        $workingDays = $employeeSchedule?->shift
                            ? $this->normalizeWorkingDays($employeeSchedule->shift->working_days)
                            : collect();
                        $hasShift = $employeeSchedule && $workingDays->contains($dayCode);

                        return [
                            'date' => $day->toDateString(),
                            'day_name' => $dayCode,
                            'has_shift' => $hasShift,
                            'shift' => $hasShift && $employeeSchedule?->shift ? [
                                'name' => $employeeSchedule->shift->name,
                                'color' => $employeeSchedule->shift->color ?? '#4f6ef7',
                                'time' => $employeeSchedule->shift->schedule_label,
                            ] : null,
                            'is_weekend' => $day->isWeekend(),
                        ];
                    })->values(),
                ];
            })->values();

        return response()->json([
            'week_start' => $weekStart->format('M d'),
            'week_end' => $weekStart->copy()->addDays(6)->format('M d, Y'),
            'days' => $days->map(fn (Carbon $day) => [
                'date' => $day->toDateString(),
                'label' => $day->format('D'),
                'date_label' => $day->format('M d'),
                'is_today' => $day->isToday(),
                'is_weekend' => $day->isWeekend(),
            ])->values(),
            'grid' => $grid,
        ]);
    }

    public function assignShift(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'note' => 'nullable|string',
        ]);

        $schedule = DB::transaction(function () use ($validated) {
            $effectiveFrom = Carbon::parse($validated['effective_from'])->toDateString();

            ShiftSchedule::where('employee_id', $validated['employee_id'])
                ->where('status', 'Active')
                ->where(function ($q) use ($effectiveFrom) {
                    $q->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $effectiveFrom);
                })
                ->update([
                    'effective_to' => Carbon::parse($effectiveFrom)->subDay()->toDateString(),
                    'status' => 'Inactive',
                ]);

            $schedule = ShiftSchedule::create([
                'employee_id' => $validated['employee_id'],
                'shift_id' => $validated['shift_id'],
                'effective_from' => $effectiveFrom,
                'effective_to' => $validated['effective_to'] ?? null,
                'status' => 'Active',
                'assigned_by' => $this->resolveAssignedByEmployeeId(),
                'note' => $validated['note'] ?? null,
            ]);

            Employee::whereKey($validated['employee_id'])->update([
                'shift_id' => $validated['shift_id'],
            ]);

            return $schedule;
        });

        return response()->json([
            'schedule' => $schedule->load(['employee.department', 'shift']),
            'message' => 'Shift assigned successfully.',
        ], 201);
    }

    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        $assigned = DB::transaction(function () use ($request, $validated) {
            $effectiveFrom = Carbon::parse($validated['effective_from'])->toDateString();
            $assignedBy = $this->resolveAssignedByEmployeeId();
            $count = 0;

            foreach ($validated['employee_ids'] as $employeeId) {
                ShiftSchedule::where('employee_id', $employeeId)
                    ->where('status', 'Active')
                    ->where(function ($q) use ($effectiveFrom) {
                        $q->whereNull('effective_to')
                            ->orWhereDate('effective_to', '>=', $effectiveFrom);
                    })
                    ->update([
                        'effective_to' => Carbon::parse($effectiveFrom)->subDay()->toDateString(),
                        'status' => 'Inactive',
                    ]);

                ShiftSchedule::create([
                    'employee_id' => $employeeId,
                    'shift_id' => $validated['shift_id'],
                    'effective_from' => $effectiveFrom,
                    'effective_to' => $validated['effective_to'] ?? null,
                    'status' => 'Active',
                    'assigned_by' => $assignedBy,
                    'note' => $request->note ?? null,
                ]);

                Employee::whereKey($employeeId)->update([
                    'shift_id' => $validated['shift_id'],
                ]);

                $count++;
            }

            return $count;
        });

        return response()->json([
            'message' => "Shift assigned to {$assigned} employees.",
            'assigned' => $assigned,
        ]);
    }

    public function endSchedule(Request $request, int $id)
    {
        $schedule = ShiftSchedule::with('employee')->findOrFail($id);

        $validated = $request->validate([
            'end_date' => 'required|date|after_or_equal:' . Carbon::parse($schedule->effective_from)->toDateString(),
        ]);

        $schedule->update([
            'effective_to' => $validated['end_date'],
            'status' => 'Inactive',
        ]);

        if ($schedule->employee && $schedule->employee->shift_id === $schedule->shift_id) {
            $hasCurrentSchedule = ShiftSchedule::where('employee_id', $schedule->employee_id)
                ->current()
                ->exists();

            if (! $hasCurrentSchedule) {
                $schedule->employee->update(['shift_id' => null]);
            }
        }

        return response()->json([
            'message' => 'Schedule ended successfully.',
        ]);
    }

    public function destroy(int $id)
    {
        $schedule = ShiftSchedule::findOrFail($id);
        $employeeId = $schedule->employee_id;
        $shiftId = $schedule->shift_id;

        $schedule->delete();

        $employee = Employee::find($employeeId);
        if ($employee && $employee->shift_id === $shiftId) {
            $currentSchedule = ShiftSchedule::current()
                ->where('employee_id', $employeeId)
                ->latest('effective_from')
                ->first();

            $employee->update([
                'shift_id' => $currentSchedule?->shift_id,
            ]);
        }

        return response()->json([
            'message' => 'Schedule deleted.',
        ]);
    }

    public function shifts()
    {
        $shifts = Shift::withCount(['activeSchedules as assigned_count'])
            ->orderBy('name')
            ->get()
            ->map(fn (Shift $shift) => $this->serializeShift($shift, true))
            ->values();

        return response()->json([
            'shifts' => $shifts,
        ]);
    }

    public function storeShift(Request $request)
    {
        $validated = $this->validateShift($request);
        $validated['working_days'] = $this->normalizeWorkingDays($validated['working_days'])->values()->all();
        $validated['break_duration'] = (int) ($validated['break_duration'] ?? 60);
        $validated['description'] = $validated['description'] ?? null;

        $shift = Shift::create($validated);

        return response()->json([
            'shift' => $this->serializeShift($shift->fresh(), true),
            'message' => 'Shift created.',
        ], 201);
    }

    public function updateShift(Request $request, int $id)
    {
        $shift = Shift::findOrFail($id);

        $validated = $this->validateShift($request, $id);
        $validated['working_days'] = $this->normalizeWorkingDays($validated['working_days'])->values()->all();
        $validated['break_duration'] = (int) ($validated['break_duration'] ?? 60);
        $validated['description'] = $validated['description'] ?? null;

        $shift->update($validated);

        return response()->json([
            'shift' => $this->serializeShift($shift->fresh(), true),
            'message' => 'Shift updated.',
        ]);
    }

    public function destroyShift(int $id)
    {
        $shift = Shift::withCount('activeSchedules')->findOrFail($id);

        if ($shift->active_schedules_count > 0) {
            return response()->json([
                'message' => 'Cannot delete shift. ' . $shift->active_schedules_count . ' employees are on this shift.',
            ], 422);
        }

        $shift->delete();

        return response()->json([
            'message' => 'Shift deleted.',
        ]);
    }

    private function validateShift(Request $request, ?int $id = null): array
    {
        $unique = 'required|string|unique:shifts,name';
        if ($id) {
            $unique .= ',' . $id;
        }

        return $request->validate([
            'name' => $unique,
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'color' => 'nullable|string|max:20',
            'working_days' => 'required|array|min:1',
            'working_days.*' => 'required|string',
            'break_duration' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);
    }

    private function serializeShift(Shift $shift, bool $includeAssignedCount = false): array
    {
        $payload = [
            'id' => $shift->id,
            'name' => $shift->name,
            'start_time' => $shift->start_time ? Carbon::parse($shift->start_time)->format('H:i') : null,
            'end_time' => $shift->end_time ? Carbon::parse($shift->end_time)->format('H:i') : null,
            'schedule_label' => $shift->schedule_label,
            'color' => $shift->color ?? '#4f6ef7',
            'working_days' => $this->normalizeWorkingDays($shift->working_days)->values()->all(),
            'break_duration' => (int) ($shift->break_duration ?? 60),
            'duration_hours' => $shift->duration_hours,
            'description' => $shift->description,
            'status' => $shift->status ?? 'Active',
        ];

        if ($includeAssignedCount) {
            $payload['assigned_count'] = (int) ($shift->assigned_count ?? 0);
        }

        return $payload;
    }

    private function normalizeWorkingDays(array|string|null $days): Collection
    {
        $map = [
            'Mon' => 'Mon',
            'Monday' => 'Mon',
            'Tue' => 'Tue',
            'Tues' => 'Tue',
            'Tuesday' => 'Tue',
            'Wed' => 'Wed',
            'Wednesday' => 'Wed',
            'Thu' => 'Thu',
            'Thur' => 'Thu',
            'Thursday' => 'Thu',
            'Fri' => 'Fri',
            'Friday' => 'Fri',
            'Sat' => 'Sat',
            'Saturday' => 'Sat',
            'Sun' => 'Sun',
            'Sunday' => 'Sun',
        ];

        $values = is_array($days) ? $days : [];

        return collect($values)
            ->map(fn ($day) => $map[$day] ?? null)
            ->filter()
            ->values();
    }

    private function resolveAssignedByEmployeeId(): ?int
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return Employee::query()
            ->where('work_email', $user->email)
            ->orWhere('personal_email', $user->email)
            ->value('id');
    }
}
