<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeOnboarding;
use App\Models\HR\Expense;
use App\Models\HR\JobOpening;
use App\Models\HR\LeaveRequest;
use App\Models\HR\PayrollRun;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    private function expensePendingStatuses(): array
    {
        return ['Pending', 'Submitted', 'Under Review'];
    }

    public function summary(): JsonResponse
    {
        $onLeave = LeaveRequest::where('status', 'Approved')
            ->whereDate('from_date', '<=', now())
            ->whereDate('to_date', '>=', now())
            ->count();

        $pendingLeave = LeaveRequest::where('status', 'Pending')->count();
        $pendingExpenses = Expense::whereIn('status', $this->expensePendingStatuses())->count();

        return response()->json([
            'on_leave' => $onLeave,
            'pending_approvals' => $pendingLeave + $pendingExpenses,
            'open_positions' => JobOpening::where('status', 'Open')->count(),
        ]);
    }

    public function stats(): JsonResponse
    {
        $totalActive = Employee::where('employment_status', 'Active')->count();
        $lastMonthTotal = Employee::where('employment_status', 'Active')
            ->where('created_at', '<=', now()->subMonth()->endOfMonth())
            ->count();

        $empChange = $lastMonthTotal > 0
            ? round((($totalActive - $lastMonthTotal) / $lastMonthTotal) * 100, 1)
            : 0;

        $onLeaveToday = LeaveRequest::where('status', 'Approved')
            ->whereDate('from_date', '<=', now())
            ->whereDate('to_date', '>=', now())
            ->count();

        $onLeaveYesterday = LeaveRequest::where('status', 'Approved')
            ->whereDate('from_date', '<=', now()->subDay())
            ->whereDate('to_date', '>=', now()->subDay())
            ->count();

        $openPositions = JobOpening::where('status', 'Open')->count();
        $newJobsThisWeek = JobOpening::where('status', 'Open')
            ->whereBetween('created_at', [now()->startOfWeek(), now()])
            ->count();

        $pendingApprovals = LeaveRequest::where('status', 'Pending')->count()
            + Expense::whereIn('status', $this->expensePendingStatuses())->count();

        return response()->json([
            'total_employees' => [
                'value' => $totalActive,
                'change' => ($empChange >= 0 ? '+' : '').$empChange.'% from last month',
                'trend' => $empChange >= 0 ? 'up' : 'down',
                'icon' => 'mdi-account-group',
                'color' => 'primary',
            ],
            'on_leave_today' => [
                'value' => $onLeaveToday,
                'change' => ($onLeaveToday - $onLeaveYesterday >= 0 ? '+' : '')
                    .($onLeaveToday - $onLeaveYesterday).' from yesterday',
                'trend' => 'neutral',
                'icon' => 'mdi-calendar-remove',
                'color' => 'warning',
            ],
            'open_positions' => [
                'value' => $openPositions,
                'change' => $newJobsThisWeek.' new this week',
                'trend' => 'up',
                'icon' => 'mdi-briefcase-open',
                'color' => 'success',
            ],
            'pending_approvals' => [
                'value' => $pendingApprovals,
                'change' => 'Requires attention',
                'trend' => $pendingApprovals > 0 ? 'down' : 'up',
                'icon' => 'mdi-clock-alert',
                'color' => 'error',
            ],
        ]);
    }

    public function attendanceChart(): JsonResponse
    {
        $year = now()->year;
        $months = [];
        $attendance = [];
        $onLeave = [];

        $totalActive = Employee::where('employment_status', 'Active')->count();

        for ($m = 1; $m <= 12; $m++) {
            $months[] = Carbon::create($year, $m, 1)->format('M');

            $workDays = 0;
            $date = Carbon::create($year, $m, 1);
            while ($date->month === $m) {
                if (! $date->isWeekend()) {
                    $workDays++;
                }
                $date->addDay();
            }

            $possible = $workDays * max($totalActive, 1);

            $presentCount = Attendance::whereYear('date', $year)
                ->whereMonth('date', $m)
                ->whereIn('status', ['Present', 'Late'])
                ->count();

            $leaveCount = (int) LeaveRequest::where('status', 'Approved')
                ->whereYear('from_date', $year)
                ->whereMonth('from_date', $m)
                ->sum('days_requested');

            $attendance[] = min(100, $possible > 0
                ? round(($presentCount / $possible) * 100)
                : 0);

            $onLeave[] = min(100, $possible > 0
                ? round(($leaveCount / $possible) * 100)
                : 0);
        }

        $currentIdx = now()->month - 1;
        $currentRate = $attendance[$currentIdx] ?? 0;
        $prevRate = $attendance[max(0, $currentIdx - 1)] ?? 0;
        $change = $currentRate - $prevRate;

        return response()->json([
            'months' => $months,
            'attendance' => $attendance,
            'leave' => $onLeave,
            'current_rate' => $currentRate,
            'change' => ($change >= 0 ? '+' : '').$change.'%',
            'trend' => $change >= 0 ? 'up' : 'down',
        ]);
    }

    public function pendingActions(): JsonResponse
    {
        $actions = [];

        $leaves = LeaveRequest::with('employee')
            ->where('status', 'Pending')
            ->latest()
            ->limit(3)
            ->get();

        foreach ($leaves as $req) {
            $actions[] = [
                'type' => 'leave',
                'title' => 'Leave Request',
                'subtitle' => ($req->employee?->full_name ?? 'Employee').' · '
                    .Carbon::parse($req->from_date)->format('M d')
                    .'–'
                    .Carbon::parse($req->to_date)->format('M d'),
                'status' => 'Pending',
                'color' => 'warning',
                'id' => $req->id,
                'link' => '/hr/leave-management',
            ];
        }

        $expenses = Expense::with('employee')
            ->whereIn('status', $this->expensePendingStatuses())
            ->latest()
            ->limit(2)
            ->get();

        foreach ($expenses as $exp) {
            $actions[] = [
                'type' => 'expense',
                'title' => 'Expense Approval',
                'subtitle' => ($exp->employee?->full_name ?? 'Employee')
                    .' · GHS '.number_format((float) $exp->amount, 2),
                'status' => $exp->status,
                'color' => 'orange',
                'id' => $exp->id,
                'link' => '/hr/expenses',
            ];
        }

        $onboardings = EmployeeOnboarding::with('employee')
            ->whereIn('status', ['Not Started', 'In Progress'])
            ->latest()
            ->limit(2)
            ->get();

        foreach ($onboardings as $ob) {
            $actions[] = [
                'type' => 'onboarding',
                'title' => 'Onboarding',
                'subtitle' => 'New hire: '.($ob->employee?->full_name ?? 'Employee'),
                'status' => $ob->status,
                'color' => 'primary',
                'id' => $ob->id,
                'link' => '/hr/onboarding',
            ];
        }

        $payroll = PayrollRun::where('status', 'Pending Approval')->latest()->first();
        if ($payroll) {
            $actions[] = [
                'type' => 'payroll',
                'title' => 'Payroll Approval',
                'subtitle' => ($payroll->month_label ?: 'Payroll Run')
                    .' · '.number_format((float) $payroll->total_net, 2).' GHS net',
                'status' => 'Pending Approval',
                'color' => 'success',
                'id' => $payroll->id,
                'link' => '/hr/payroll',
            ];
        }

        $total = count($actions) + 2;
        $completed = 2;

        return response()->json([
            'actions' => $actions,
            'total' => $total,
            'completed' => $completed,
            'percent' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ]);
    }

    public function recentHires(): JsonResponse
    {
        $hires = Employee::with([
            'department:id,name',
            'designation:id,name',
        ])->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($emp) => [
                'id' => $emp->id,
                'full_name' => $emp->full_name,
                'initials' => $emp->initials,
                'avatar_url' => $emp->avatar_url,
                'employee_id' => $emp->employee_id,
                'department' => $emp->department?->name ?? '—',
                'designation' => $emp->designation?->name ?? '—',
                'join_date' => $emp->join_date
                    ? Carbon::parse($emp->join_date)->format('M d, Y')
                    : '—',
                'status' => $emp->employment_status,
                'status_color' => match ($emp->employment_status) {
                    'Active' => 'success',
                    'Inactive' => 'error',
                    'On Leave' => 'warning',
                    'Probation' => 'info',
                    default => 'default',
                },
            ]);

        return response()->json([
            'recent_hires' => $hires,
        ]);
    }

    public function upcomingEvents(): JsonResponse
    {
        $events = [];

        $leaves = LeaveRequest::with('employee')
            ->where('status', 'Approved')
            ->whereDate('from_date', '>', now())
            ->whereDate('from_date', '<=', now()->addDays(14))
            ->orderBy('from_date')
            ->limit(3)
            ->get();

        foreach ($leaves as $leave) {
            $events[] = [
                'category' => 'Leave',
                'color' => 'primary',
                'icon' => 'mdi-calendar-remove',
                'title' => ($leave->employee?->full_name ?? 'Employee').' going on leave',
                'date' => Carbon::parse($leave->from_date)->format('M d, Y'),
                'date_raw' => $leave->from_date?->toDateString() ?? (string) $leave->from_date,
                'link' => '/hr/leave-management',
            ];
        }

        $payroll = PayrollRun::whereIn('status', ['Approved', 'Processing', 'Pending Approval'])
            ->whereDate('pay_date', '>=', now())
            ->orderBy('pay_date')
            ->first();

        if ($payroll) {
            $events[] = [
                'category' => 'Deadline',
                'color' => 'error',
                'icon' => 'mdi-cash-multiple',
                'title' => ($payroll->month_label ?: 'Payroll').' Pay Date',
                'date' => Carbon::parse($payroll->pay_date)->format('M d, Y'),
                'date_raw' => $payroll->pay_date?->toDateString() ?? (string) $payroll->pay_date,
                'link' => '/hr/payroll',
            ];
        }

        $onboardings = EmployeeOnboarding::with('employee')
            ->where('status', 'Not Started')
            ->whereDate('start_date', '>=', now())
            ->orderBy('start_date')
            ->limit(2)
            ->get();

        foreach ($onboardings as $ob) {
            $events[] = [
                'category' => 'Onboarding',
                'color' => 'success',
                'icon' => 'mdi-account-plus',
                'title' => 'Onboarding: '.($ob->employee?->full_name ?? 'New Hire'),
                'date' => Carbon::parse($ob->start_date)->format('M d, Y'),
                'date_raw' => $ob->start_date?->toDateString() ?? (string) $ob->start_date,
                'link' => '/hr/onboarding',
            ];
        }

        $jobs = JobOpening::where('status', 'Open')
            ->whereNotNull('deadline')
            ->whereDate('deadline', '>=', now())
            ->whereDate('deadline', '<=', now()->addDays(7))
            ->orderBy('deadline')
            ->limit(2)
            ->get();

        foreach ($jobs as $job) {
            $events[] = [
                'category' => 'Deadline',
                'color' => 'warning',
                'icon' => 'mdi-briefcase-clock',
                'title' => 'Job Closing: '.$job->title,
                'date' => Carbon::parse($job->deadline)->format('M d, Y'),
                'date_raw' => $job->deadline?->toDateString() ?? (string) $job->deadline,
                'link' => '/hr/job-openings',
            ];
        }

        usort($events, fn ($a, $b) => strcmp((string) $a['date_raw'], (string) $b['date_raw']));

        return response()->json([
            'events' => array_slice($events, 0, 6),
        ]);
    }
}
