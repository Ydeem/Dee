<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = LeaveRequest::with(['employee.department', 'leaveType', 'approvedBy'])
            ->when($request->search, fn ($q) =>
                $q->whereHas('employee', fn ($employeeQuery) =>
                    $employeeQuery->where('first_name', 'like', '%' . $request->search . '%')
                        ->orWhere('last_name', 'like', '%' . $request->search . '%')
                )
            )
            ->when($request->department, fn ($q) =>
                $q->whereHas('employee.department', fn ($departmentQuery) =>
                    $departmentQuery->where('name', $request->department)
                )
            )
            ->when($request->leave_type, fn ($q) =>
                $q->where('leave_type_id', $request->leave_type)
            )
            ->when($request->status, fn ($q) =>
                $q->where('status', $request->status)
            )
            ->when($request->month, fn ($q) =>
                $q->whereMonth('from_date', Carbon::parse($request->month)->month)
                    ->whereYear('from_date', Carbon::parse($request->month)->year)
            );

        $requests = $query->orderBy('created_at', 'desc')->paginate((int) ($request->per_page ?? 10));

        $summary = [
            'pending' => LeaveRequest::where('status', 'Pending')->count(),
            'approved' => LeaveRequest::where('status', 'Approved')->whereMonth('from_date', now()->month)->count(),
            'rejected' => LeaveRequest::where('status', 'Rejected')->whereMonth('from_date', now()->month)->count(),
            'on_leave_today' => LeaveRequest::where('status', 'Approved')
                ->whereDate('from_date', '<=', now())
                ->whereDate('to_date', '>=', now())
                ->count(),
        ];

        return response()->json([
            'requests' => $requests,
            'summary' => $summary,
            'leave_types' => LeaveType::where('status', 'Active')->get(['id', 'name', 'color', 'days_allowed']),
            'departments' => \App\Models\HR\Department::pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'nullable|string',
        ]);

        $days = $this->businessDays($validated['from_date'], $validated['to_date']);

        $overlap = LeaveRequest::where('employee_id', $validated['employee_id'])
            ->whereIn('status', ['Pending', 'Approved'])
            ->where(function ($q) use ($validated) {
                $q->whereBetween('from_date', [$validated['from_date'], $validated['to_date']])
                    ->orWhereBetween('to_date', [$validated['from_date'], $validated['to_date']])
                    ->orWhere(function ($inner) use ($validated) {
                        $inner->where('from_date', '<=', $validated['from_date'])
                            ->where('to_date', '>=', $validated['to_date']);
                    });
            })->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Employee already has a leave request for this date range.'
            ], 422);
        }

        $leaveRequest = LeaveRequest::create([
            ...$validated,
            'days_requested' => $days,
            'status' => 'Pending',
        ]);

        return response()->json(['request' => $leaveRequest], 201);
    }

    public function update(Request $request, int $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'nullable|string',
        ]);

        $days = $this->businessDays($validated['from_date'], $validated['to_date']);

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
            })->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Employee already has a leave request for this date range.'
            ], 422);
        }

        $leaveRequest->update([
            ...$validated,
            'days_requested' => $days,
        ]);

        return response()->json(['request' => $leaveRequest]);
    }

    public function approve(int $id)
    {
        $leave = LeaveRequest::findOrFail($id);
        if ($leave->status !== 'Pending') {
            return response()->json([
                'message' => 'Only pending requests can be approved.'
            ], 422);
        }

        $approverId = auth()->id();
        $approver = $approverId ? Employee::find($approverId) : null;

        $leave->update([
            'status' => 'Approved',
            'approved_by' => $approver?->id,
            'approved_at' => now(),
        ]);

        return response()->json(['message' => 'Leave request approved.']);
    }

    public function reject(Request $request, int $id)
    {
        $leave = LeaveRequest::findOrFail($id);

        $validated = $request->validate([
            'rejection_reason' => 'required|string'
        ]);

        $approverId = auth()->id();
        $approver = $approverId ? Employee::find($approverId) : null;

        $leave->update([
            'status' => 'Rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => $approver?->id,
            'approved_at' => now(),
        ]);

        return response()->json(['message' => 'Leave request rejected.']);
    }

    public function cancel(int $id)
    {
        $leave = LeaveRequest::findOrFail($id);
        if (!in_array($leave->status, ['Pending', 'Approved'], true)) {
            return response()->json([
                'message' => 'This request cannot be cancelled.'
            ], 422);
        }

        $leave->update(['status' => 'Cancelled']);
        return response()->json(['message' => 'Leave request cancelled.']);
    }

    public function destroy(int $id)
    {
        LeaveRequest::findOrFail($id)->delete();
        return response()->json(['message' => 'Record deleted.']);
    }

    public function balances(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);

        $employees = Employee::with([
            'department',
            'leaveRequests' => fn ($q) =>
                $q->where('status', 'Approved')
                    ->whereYear('from_date', $year)
                    ->with('leaveType')
        ])->where('employment_status', 'Active')->get();

        $leaveTypes = LeaveType::where('status', 'Active')->get();

        $balances = $employees->map(function ($employee) use ($leaveTypes) {
            $usedByType = $employee->leaveRequests
                ->groupBy('leave_type_id')
                ->map(fn ($reqs) => $reqs->sum('days_requested'));

            $perType = $leaveTypes->map(function ($type) use ($usedByType) {
                $used = (int) ($usedByType[$type->id] ?? 0);
                $remaining = max(0, (int) $type->days_allowed - $used);
                return [
                    'leave_type_id' => $type->id,
                    'name' => $type->name,
                    'color' => $type->color,
                    'allowed' => (int) $type->days_allowed,
                    'used' => $used,
                    'remaining' => $remaining,
                ];
            })->values();

            return [
                'employee' => [
                    'id' => $employee->id,
                    'full_name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                    'avatar_url' => $employee->avatar_url,
                    'department' => $employee->department?->name,
                ],
                'balances' => $perType,
            ];
        })->values();

        return response()->json([
            'balances' => $balances,
            'leave_types' => $leaveTypes,
        ]);
    }

    private function businessDays(string $fromDate, string $toDate): int
    {
        $from = Carbon::parse($fromDate);
        $to = Carbon::parse($toDate);

        $days = 0;
        $current = $from->copy();
        while ($current->lte($to)) {
            if (!$current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }
}
