<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Applicant;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeOnboarding;
use App\Models\HR\Expense;
use App\Models\HR\JobOpening;
use App\Models\HR\LeaveRequest;
use App\Models\HR\PayrollRun;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Throwable;

class HRDashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $onLeave = \App\Models\HR\LeaveRequest
            ::where('status', 'Approved')
            ->whereDate('from_date', '<=', now())
            ->whereDate('to_date', '>=', now())
            ->count();

        $pendingApprovals =
            \App\Models\HR\LeaveRequest
                ::where('status', 'Pending')->count()
            + \App\Models\HR\Expense
                ::whereIn('status',
                    ['Submitted', 'Under Review'])
                ->count();

        $openPositions = \App\Models\HR\JobOpening
            ::where('status', 'Open')->count();

        return response()->json([
            'on_leave'          => $onLeave,
            'pending_approvals' => $pendingApprovals,
            'open_positions'    => $openPositions,
        ]);
    }

    public function stats(): JsonResponse
    {
        // Total employees
        $totalNow = \App\Models\HR\Employee
            ::where('employment_status', 'Active')
            ->count();

        $totalLastMonth = \App\Models\HR\Employee
            ::where('employment_status', 'Active')
            ->whereDate('created_at', '<=',
                now()->subMonth()->endOfMonth())
            ->count();

        $empChange = $totalLastMonth > 0
            ? round((($totalNow - $totalLastMonth)
                / $totalLastMonth) * 100, 1)
            : 0;

        // On leave today
        $onLeaveToday = \App\Models\HR\LeaveRequest
            ::where('status', 'Approved')
            ->whereDate('from_date', '<=', now())
            ->whereDate('to_date', '>=', now())
            ->count();

        $onLeaveYesterday = \App\Models\HR\LeaveRequest
            ::where('status', 'Approved')
            ->whereDate('from_date', '<=',
                now()->subDay())
            ->whereDate('to_date', '>=',
                now()->subDay())
            ->count();

        // Open positions
        $openPositions = \App\Models\HR\JobOpening
            ::where('status', 'Open')->count();

        $newJobsThisWeek = \App\Models\HR\JobOpening
            ::where('status', 'Open')
            ->whereBetween('created_at', [
                now()->startOfWeek(), now()
            ])->count();

        // Pending approvals
        $pendingLeave = \App\Models\HR\LeaveRequest
            ::where('status', 'Pending')->count();
        $pendingExpenses = \App\Models\HR\Expense
            ::whereIn('status',
                ['Submitted', 'Under Review'])
            ->count();
        $pendingApprovals = $pendingLeave
            + $pendingExpenses;

        return response()->json([
            'total_employees' => [
                'value'  => $totalNow,
                'change' => ($empChange >= 0 ? '+' : '')
                            .$empChange.'% from last month',
                'trend'  => $empChange >= 0 ? 'up' : 'down',
            ],
            'on_leave_today' => [
                'value'  => $onLeaveToday,
                'change' => ($onLeaveToday
                            - $onLeaveYesterday >= 0
                            ? '+' : '')
                            .($onLeaveToday
                            - $onLeaveYesterday)
                            .' from yesterday',
                'trend'  => 'neutral',
            ],
            'open_positions' => [
                'value'  => $openPositions,
                'change' => $newJobsThisWeek
                            .' new this week',
                'trend'  => 'up',
            ],
            'pending_approvals' => [
                'value'  => $pendingApprovals,
                'change' => $pendingApprovals > 0
                            ? 'Requires attention'
                            : 'All clear',
                'trend'  => $pendingApprovals > 0
                            ? 'down' : 'up',
            ],
        ]);
    }

    public function attendanceChart(): JsonResponse
    {
        $year   = now()->year;
        $months = [];
        $attendance = [];
        $leave  = [];

        $totalEmployees = \App\Models\HR\Employee
            ::where('employment_status', 'Active')
            ->count();

        if ($totalEmployees === 0) {
            $totalEmployees = 1; // avoid division by zero
        }

        for ($m = 1; $m <= 12; $m++) {
            $months[] = \Carbon\Carbon::create($year, $m, 1)
                            ->format('M');

            // Count working days in month
            $workDays = 0;
            $date = \Carbon\Carbon::create($year, $m, 1);
            while ($date->month === $m) {
                if (! $date->isWeekend()) $workDays++;
                $date->addDay();
            }

            $possibleDays = $workDays * $totalEmployees;

            $present = \App\Models\HR\Attendance
                ::whereYear('date', $year)
                ->whereMonth('date', $m)
                ->whereIn('status', ['Present', 'Late'])
                ->count();

            $onLeave = \App\Models\HR\LeaveRequest
                ::where('status', 'Approved')
                ->whereYear('from_date', $year)
                ->whereMonth('from_date', $m)
                ->sum('days_requested');

            $attendance[] = $possibleDays > 0
                ? min(100, round(
                    ($present / $possibleDays) * 100
                ))
                : 0;

            $leave[] = $possibleDays > 0
                ? min(100, round(
                    ($onLeave / $possibleDays) * 100
                ))
                : 0;
        }

        $currentIdx  = now()->month - 1;
        $currentRate = $attendance[$currentIdx] ?? 0;
        $prevRate    = $attendance[
                        max(0, $currentIdx - 1)
                    ] ?? 0;
        $change      = $currentRate - $prevRate;

        return response()->json([
            'months'       => $months,
            'attendance'   => $attendance,
            'leave'        => $leave,
            'current_rate' => $currentRate,
            'change'       => ($change >= 0 ? '+' : '')
                            .$change.'%',
            'trend'        => $change >= 0 ? 'up' : 'down',
        ]);
    }

    public function pendingActions(): JsonResponse
    {
        $actions = [];

        // Pending leave requests
        \App\Models\HR\LeaveRequest
            ::with('employee')
            ->where('status', 'Pending')
            ->latest()
            ->limit(3)
            ->get()
            ->each(function ($req) use (&$actions) {
                $name = $req->employee
                    ? trim($req->employee->first_name
                        .' '.$req->employee->last_name)
                    : 'Unknown';
                $actions[] = [
                    'type'     => 'Leave Request',
                    'title'    => 'Leave Request',
                    'subtitle' => $name.' · '
                        .\Carbon\Carbon::parse(
                            $req->from_date)->format('M d')
                        .'–'
                        .\Carbon\Carbon::parse(
                            $req->to_date)->format('M d'),
                    'status'   => 'Pending',
                    'color'    => 'warning',
                    'id'       => $req->id,
                    'link'     => '/hr/leave-management',
                ];
            });

        // In-progress onboardings
        \App\Models\HR\EmployeeOnboarding
            ::with('employee')
            ->whereIn('status',
                ['Not Started', 'In Progress'])
            ->latest()
            ->limit(2)
            ->get()
            ->each(function ($ob) use (&$actions) {
                $name = $ob->employee
                    ? trim($ob->employee->first_name
                        .' '.$ob->employee->last_name)
                    : 'Unknown';
                $actions[] = [
                    'type'     => 'Onboarding',
                    'title'    => 'Onboarding',
                    'subtitle' => 'New Hire: '.$name,
                    'status'   => $ob->status,
                    'color'    => 'primary',
                    'id'       => $ob->id,
                    'link'     => '/hr/onboarding',
                ];
            });

        // Payroll pending approval
        $payroll = \App\Models\HR\PayrollRun
            ::where('status', 'Pending Approval')
            ->latest()->first();
        if ($payroll) {
            $actions[] = [
                'type'     => 'Payroll Approval',
                'title'    => 'Payroll Approval',
                'subtitle' => $payroll->title
                            .' · Pending',
                'status'   => 'Pending Approval',
                'color'    => 'success',
                'id'       => $payroll->id,
                'link'     => '/hr/payroll',
            ];
        }

        // Pending expenses
        \App\Models\HR\Expense
            ::with('employee')
            ->whereIn('status',
                ['Submitted', 'Under Review'])
            ->latest()
            ->limit(2)
            ->get()
            ->each(function ($exp) use (&$actions) {
                $name = $exp->employee
                    ? trim($exp->employee->first_name
                        .' '.$exp->employee->last_name)
                    : 'Unknown';
                $actions[] = [
                    'type'     => 'Expense',
                    'title'    => 'Expense Approval',
                    'subtitle' => $name.' · GHS '
                        .number_format(
                            $exp->amount, 2),
                    'status'   => 'Pending',
                    'color'    => 'warning',
                    'id'       => $exp->id,
                    'link'     => '/hr/expenses',
                ];
            });

        $total     = count($actions) + 2;
        $completed = 2;

        return response()->json([
            'actions'   => array_slice($actions, 0, 6),
            'total'     => $total,
            'completed' => $completed,
            'percent'   => round(
                ($completed / max($total, 1)) * 100
            ),
        ]);
    }

    public function recentHires(): JsonResponse
    {
        $hires = \App\Models\HR\Employee
            ::with('department')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($emp) => [
                'id'         => $emp->id,
                'full_name'  => trim($emp->first_name
                                .' '.$emp->last_name),
                'avatar_url' => $emp->avatar
                    ? asset('storage/'.$emp->avatar)
                    : null,
                'initials'   => strtoupper(
                    substr($emp->first_name ?? '', 0, 1)
                    .substr($emp->last_name ?? '', 0, 1)
                ),
                'department' => $emp->department?->name
                                ?? '—',
                'join_date'  => $emp->join_date
                    ? \Carbon\Carbon::parse($emp->join_date)
                        ->format('M d, Y')
                    : '—',
                'status'     => $emp->employment_status,
            ]);

        return response()->json([
            'recent_hires' => $hires
        ]);
    }

    public function upcomingEvents(): JsonResponse
    {
        $events = [];

        // Approved leave starting within 14 days
        \App\Models\HR\LeaveRequest
            ::with('employee')
            ->where('status', 'Approved')
            ->whereDate('from_date', '>', now())
            ->whereDate('from_date', '<=',
                now()->addDays(14))
            ->orderBy('from_date')
            ->limit(2)
            ->get()
            ->each(function ($l) use (&$events) {
                $name = $l->employee
                    ? $l->employee->first_name
                    : 'Employee';
                $events[] = [
                    'category' => 'Leave',
                    'color'    => 'primary',
                    'title'    => $name.' on leave',
                    'date'     => \Carbon\Carbon::parse(
                        $l->from_date)->format('M d, Y'),
                    'date_raw' => $l->from_date,
                ];
            });

        // Upcoming payroll pay date
        $payroll = \App\Models\HR\PayrollRun
            ::whereIn('status',
                ['Approved', 'Pending Approval'])
            ->whereDate('pay_date', '>=', now())
            ->orderBy('pay_date')
            ->first();
        if ($payroll) {
            $events[] = [
                'category' => 'Deadline',
                'color'    => 'error',
                'title'    => $payroll->title
                            .' Pay Date',
                'date'     => \Carbon\Carbon::parse(
                    $payroll->pay_date)->format('M d, Y'),
                'date_raw' => $payroll->pay_date,
            ];
        }

        // Upcoming onboardings
        \App\Models\HR\EmployeeOnboarding
            ::with('employee')
            ->where('status', 'Not Started')
            ->whereDate('start_date', '>=', now())
            ->orderBy('start_date')
            ->limit(2)
            ->get()
            ->each(function ($ob) use (&$events) {
                $name = $ob->employee
                    ? $ob->employee->first_name
                    : 'New Hire';
                $events[] = [
                    'category' => 'Onboarding',
                    'color'    => 'success',
                    'title'    => 'Onboarding: '.$name,
                    'date'     => \Carbon\Carbon::parse(
                        $ob->start_date)->format('M d, Y'),
                    'date_raw' => $ob->start_date,
                ];
            });

        // Jobs closing within 7 days
        \App\Models\HR\JobOpening
            ::where('status', 'Open')
            ->whereNotNull('deadline')
            ->whereDate('deadline', '>=', now())
            ->whereDate('deadline', '<=',
                now()->addDays(7))
            ->orderBy('deadline')
            ->limit(1)
            ->get()
            ->each(function ($job) use (&$events) {
                $events[] = [
                    'category' => 'Deadline',
                    'color'    => 'warning',
                    'title'    => 'Closing: '.$job->title,
                    'date'     => \Carbon\Carbon::parse(
                        $job->deadline)->format('M d, Y'),
                    'date_raw' => $job->deadline,
                ];
            });

        // Sort by date
        usort($events, fn($a, $b) =>
            strcmp($a['date_raw'], $b['date_raw'])
        );

        return response()->json([
            'events' => array_slice($events, 0, 6)
        ]);
    }
}
