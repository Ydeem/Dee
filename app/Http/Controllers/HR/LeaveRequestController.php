<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Notifications\HR\LeaveRequestNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $month = (int) ($request->month ?? now()->month);
        $year = (int) ($request->year ?? now()->year);

        $query = LeaveRequest::with([
            'employee:id,first_name,last_name,employee_id,avatar,department_id',
            'employee.department:id,name',
            'leaveType:id,name,color',
        ])
            ->whereHas('employee')
            ->when($request->boolean('today'), function ($query) {
                $today = now()->toDateString();
                $query->whereDate('from_date', '<=', $today)
                    ->whereDate('to_date', '>=', $today);
            })
            ->when($month, fn ($q) => $q->whereMonth('from_date', $month))
            ->when($year, fn ($q) => $q->whereYear('from_date', $year))
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
            ->when($request->leave_type, function ($q) use ($request) {
                $leaveType = $request->leave_type;

                if (is_numeric($leaveType)) {
                    $q->where('leave_type_id', $leaveType);
                } else {
                    $q->whereHas('leaveType', fn ($leaveTypeQuery) =>
                        $leaveTypeQuery->where('name', $leaveType)
                    );
                }
            })
            ->when($request->status, fn ($q) =>
                $q->where('status', $request->status)
            );

        $requests = $query
            ->orderBy('created_at', 'desc')
            ->paginate((int) ($request->per_page ?? 10));

        $requests->through(function (LeaveRequest $leaveRequest) {
            return [
                'id' => $leaveRequest->id,
                'from_date' => $leaveRequest->from_date?->format('M d, Y'),
                'to_date' => $leaveRequest->to_date?->format('M d, Y'),
                'from_date_raw' => $leaveRequest->from_date?->format('Y-m-d'),
                'to_date_raw' => $leaveRequest->to_date?->format('Y-m-d'),
                'days_requested' => (int) $leaveRequest->days_requested,
                'reason' => $leaveRequest->reason,
                'status' => $leaveRequest->status,
                'status_color' => $leaveRequest->status_color,
                'can_approve' => $leaveRequest->can_approve,
                'can_reject' => $leaveRequest->can_reject,
                'can_cancel' => $leaveRequest->can_cancel,
                'rejection_reason' => $leaveRequest->rejection_reason,
                'applied_on' => $leaveRequest->created_at?->format('M d, Y'),
                'approved_at' => $leaveRequest->approved_at?->format('M d, Y'),
                'employee' => $leaveRequest->employee ? [
                    'id' => $leaveRequest->employee->id,
                    'name' => $leaveRequest->employee->full_name,
                    'employee_id' => $leaveRequest->employee->employee_id,
                    'avatar' => $leaveRequest->employee->avatar_url,
                    'initials' => $leaveRequest->employee->initials,
                    'department' => $leaveRequest->employee->department?->name ?? '-',
                ] : null,
                'leave_type' => $leaveRequest->leaveType ? [
                    'id' => $leaveRequest->leaveType->id,
                    'name' => $leaveRequest->leaveType->name,
                    'color' => $leaveRequest->leaveType->color ?? '#4f6ef7',
                ] : null,
            ];
        });

        return response()->json([
            'requests' => $requests,
            'stats' => $this->getStats(),
            'filters' => [
                'departments' => Department::active()->orderBy('name')->pluck('name'),
                'leave_types' => LeaveType::active()->orderBy('name')->pluck('name'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date' => 'required|date|after_or_equal:today',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'nullable|string|max:500',
        ]);

        $overlap = LeaveRequest::where('employee_id', $validated['employee_id'])
            ->whereIn('status', ['Pending', 'Approved'])
            ->where(function ($q) use ($validated) {
                $q->whereBetween('from_date', [$validated['from_date'], $validated['to_date']])
                    ->orWhereBetween('to_date', [$validated['from_date'], $validated['to_date']])
                    ->orWhere(function ($inner) use ($validated) {
                        $inner->where('from_date', '<=', $validated['from_date'])
                            ->where('to_date', '>=', $validated['to_date']);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Employee already has a leave request overlapping these dates.',
            ], 422);
        }

        $leave = LeaveRequest::create([
            ...$validated,
            'days_requested' => LeaveRequest::calculateDays($validated['from_date'], $validated['to_date']),
            'status' => 'Pending',
        ]);

        return response()->json([
            'leave' => $leave->load(['employee', 'leaveType']),
            'message' => 'Leave request submitted.',
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'nullable|string|max:500',
        ]);

        $overlap = LeaveRequest::where('employee_id', $validated['employee_id'])
            ->where('id', '!=', $leaveRequest->id)
            ->whereIn('status', ['Pending', 'Approved'])
            ->where(function ($q) use ($validated) {
                $q->whereBetween('from_date', [$validated['from_date'], $validated['to_date']])
                    ->orWhereBetween('to_date', [$validated['from_date'], $validated['to_date']])
                    ->orWhere(function ($inner) use ($validated) {
                        $inner->where('from_date', '<=', $validated['from_date'])
                            ->where('to_date', '>=', $validated['to_date']);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Employee already has a leave request overlapping these dates.',
            ], 422);
        }

        $leaveRequest->update([
            ...$validated,
            'days_requested' => LeaveRequest::calculateDays($validated['from_date'], $validated['to_date']),
        ]);

        return response()->json([
            'leave' => $leaveRequest->fresh()->load(['employee', 'leaveType']),
            'message' => 'Leave request updated.',
        ]);
    }

    public function approve(int $id)
    {
        abort_if(! $this->canAny(['approve leave', 'approve leave requests']), 403, 'Forbidden');

        $leave = LeaveRequest::with('employee')->findOrFail($id);

        if ($leave->status !== 'Pending') {
            return response()->json([
                'message' => 'Only pending requests can be approved.',
            ], 422);
        }

        $leave->update([
            'status' => 'Approved',
            'approved_by' => $this->currentEmployeeId(),
            'rejected_by' => null,
            'rejection_reason' => null,
            'approved_at' => now(),
        ]);

        $recipient = $this->resolveUserForEmployee($leave->employee);
        if ($recipient && $recipient->settings->notify_leave_approved) {
            $recipient->notify(new LeaveRequestNotification(
                message: 'Your leave request was approved',
                type: 'leave_approved',
                link: '/hr/leave-management',
                icon: 'mdi-calendar-check',
                color: 'success',
            ));
        }

        return response()->json([
            'message' => 'Leave approved for ' . ($leave->employee?->first_name ?? 'employee') . '.',
        ]);
    }

    public function reject(Request $request, int $id)
    {
        abort_if(! $this->canAny(['approve leave', 'approve leave requests']), 403, 'Forbidden');

        $leave = LeaveRequest::with('employee')->findOrFail($id);

        if (! in_array($leave->status, ['Pending', 'Approved'], true)) {
            return response()->json([
                'message' => 'This request cannot be rejected.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $leave->update([
            'status' => 'Rejected',
            'rejected_by' => $this->currentEmployeeId(),
            'rejection_reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'Leave request rejected.',
        ]);
    }

    public function cancel(int $id)
    {
        $leave = LeaveRequest::findOrFail($id);

        if (! in_array($leave->status, ['Pending', 'Approved'], true)) {
            return response()->json([
                'message' => 'This request cannot be cancelled.',
            ], 422);
        }

        if (! Carbon::parse($leave->from_date)->isFuture()) {
            return response()->json([
                'message' => 'Only future leave requests can be cancelled.',
            ], 422);
        }

        $leave->update(['status' => 'Cancelled']);

        return response()->json([
            'message' => 'Leave request cancelled.',
        ]);
    }

    public function destroy(int $id)
    {
        $leave = LeaveRequest::findOrFail($id);

        if ($leave->status === 'Approved') {
            return response()->json([
                'message' => 'Cannot delete an approved leave request. Cancel it first.',
            ], 422);
        }

        $leave->delete();

        return response()->json([
            'message' => 'Leave request deleted.',
        ]);
    }

    public function balances(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);

        $employees = Employee::with('department')
            ->where('employment_status', '!=', 'Inactive')
            ->when($request->search, fn ($q) =>
                $q->where(function ($employeeQuery) use ($request) {
                    $employeeQuery->where('first_name', 'like', '%' . $request->search . '%')
                        ->orWhere('last_name', 'like', '%' . $request->search . '%')
                        ->orWhere('employee_id', 'like', '%' . $request->search . '%');
                })
            )
            ->when($request->department, fn ($q) =>
                $q->whereHas('department', fn ($departmentQuery) =>
                    $departmentQuery->where('name', $request->department)
                )
            )
            ->orderBy('first_name')
            ->paginate((int) ($request->per_page ?? 10));

        $leaveTypes = LeaveType::active()->orderBy('name')->get();

        $employees->through(function (Employee $employee) use ($leaveTypes, $year) {
            $balances = $leaveTypes->map(function (LeaveType $leaveType) use ($employee, $year) {
                $used = LeaveRequest::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('status', 'Approved')
                    ->whereYear('from_date', $year)
                    ->sum('days_requested');

                return [
                    'leave_type_id' => $leaveType->id,
                    'name' => $leaveType->name,
                    'color' => $leaveType->color ?? '#4f6ef7',
                    'allowed' => (int) $leaveType->days_allowed,
                    'used' => (int) $used,
                    'remaining' => max(0, (int) $leaveType->days_allowed - (int) $used),
                ];
            })->values();

            return [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_id' => $employee->employee_id,
                'avatar' => $employee->avatar_url,
                'initials' => $employee->initials,
                'department' => $employee->department?->name ?? '-',
                'balances' => $balances,
            ];
        });

        return response()->json([
            'employees' => $employees,
            'leave_types' => $leaveTypes->map(fn (LeaveType $leaveType) => [
                'id' => $leaveType->id,
                'name' => $leaveType->name,
                'color' => $leaveType->color ?? '#4f6ef7',
            ]),
        ]);
    }

    private function getStats(): array
    {
        return [
            'pending' => LeaveRequest::where('status', 'Pending')->count(),
            'approved' => LeaveRequest::where('status', 'Approved')->count(),
            'rejected' => LeaveRequest::where('status', 'Rejected')->count(),
            'on_leave_today' => LeaveRequest::where('status', 'Approved')
                ->whereDate('from_date', '<=', now())
                ->whereDate('to_date', '>=', now())
                ->count(),
        ];
    }

    private function currentEmployeeId(): ?int
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

    private function resolveUserForEmployee(?Employee $employee): ?User
    {
        if (! $employee) {
            return null;
        }

        $emails = collect([$employee->work_email, $employee->personal_email])
            ->filter()
            ->values()
            ->all();

        if ($emails === []) {
            return null;
        }

        return User::query()
            ->whereIn('email', $emails)
            ->first();
    }
}
