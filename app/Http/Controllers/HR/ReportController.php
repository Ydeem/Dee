<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Applicant;
use App\Models\HR\Attendance;
use App\Models\HR\ChatbotAudit;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Expense;
use App\Models\HR\JobOpening;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\HR\PayrollRun;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    private function payrollMonthColumn(): string
    {
        return Schema::hasColumn('payroll_runs', 'month')
            ? 'month'
            : 'period_month';
    }

    private function payrollYearColumn(): string
    {
        return Schema::hasColumn('payroll_runs', 'year')
            ? 'year'
            : 'period_year';
    }

    private function expensePendingStatuses(): array
    {
        return ['Pending', 'Submitted', 'Under Review'];
    }

    public function workforce(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $month = (int) ($request->month ?? now()->month);

        $monthlyHires = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyHires[] = [
                'month' => $m,
                'label' => Carbon::create($year, $m, 1)->format('M'),
                'count' => Employee::whereYear('created_at', $year)
                    ->whereMonth('created_at', $m)
                    ->count(),
            ];
        }

        return response()->json([
            'by_department' => Department::withCount([
                'employees as total',
                'employees as active' => fn ($q) => $q->where('employment_status', 'Active'),
            ])->orderByDesc('total')->get(['id', 'name']),
            'by_type' => Employee::where('employment_status', 'Active')
                ->selectRaw('employment_type, count(*) as total')
                ->groupBy('employment_type')
                ->get(),
            'monthly_hires' => $monthlyHires,
            'by_gender' => Employee::where('employment_status', 'Active')
                ->selectRaw('gender, count(*) as total')
                ->whereNotNull('gender')
                ->groupBy('gender')
                ->get(),
            'total_active' => Employee::where('employment_status', 'Active')->count(),
            'total_inactive' => Employee::where('employment_status', 'Inactive')->count(),
            'new_this_month' => Employee::whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->count(),
            'total_departments' => Department::count(),
        ]);
    }

    public function attendance(Request $request)
    {
        $month = (int) ($request->month ?? now()->month);
        $year = (int) ($request->year ?? now()->year);

        return response()->json([
            'daily_summary' => Attendance::whereMonth('date', $month)
                ->whereYear('date', $year)
                ->selectRaw('date,
                    SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late,
                    SUM(CASE WHEN status = "On Leave" THEN 1 ELSE 0 END) as on_leave')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'by_department' => Department::with([
                'employees' => fn ($q) => $q->select('id', 'department_id'),
            ])->get()->map(function ($dept) use ($month, $year) {
                $employeeIds = $dept->employees->pluck('id');

                if ($employeeIds->isEmpty()) {
                    return [
                        'department' => $dept->name,
                        'present_rate' => 0,
                        'absent_rate' => 0,
                    ];
                }

                $total = Attendance::whereIn('employee_id', $employeeIds)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->count();

                $present = Attendance::whereIn('employee_id', $employeeIds)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->whereIn('status', ['Present', 'Late'])
                    ->count();

                return [
                    'department' => $dept->name,
                    'present_rate' => $total > 0
                        ? round(($present / $total) * 100)
                        : 0,
                    'absent_rate' => $total > 0
                        ? round((($total - $present) / $total) * 100)
                        : 0,
                ];
            })->filter(fn ($row) => $row['present_rate'] > 0 || $row['absent_rate'] > 0)->values(),
            'totals' => [
                'present' => Attendance::whereMonth('date', $month)->whereYear('date', $year)->where('status', 'Present')->count(),
                'absent' => Attendance::whereMonth('date', $month)->whereYear('date', $year)->where('status', 'Absent')->count(),
                'late' => Attendance::whereMonth('date', $month)->whereYear('date', $year)->where('status', 'Late')->count(),
            ],
        ]);
    }

    public function leave(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly[] = [
                'month' => $m,
                'label' => Carbon::create($year, $m, 1)->format('M'),
                'days' => (int) LeaveRequest::where('status', 'Approved')
                    ->whereYear('from_date', $year)
                    ->whereMonth('from_date', $m)
                    ->sum('days_requested'),
            ];
        }

        return response()->json([
            'by_type' => LeaveType::withCount([
                'leaveRequests as total_requests',
                'leaveRequests as approved' => fn ($q) => $q
                    ->where('status', 'Approved')
                    ->whereYear('from_date', $year),
            ])->withSum([
                'leaveRequests as total_days' => fn ($q) => $q
                    ->where('status', 'Approved')
                    ->whereYear('from_date', $year),
            ], 'days_requested')->get(['id', 'name', 'color']),
            'monthly' => $monthly,
            'by_department' => Department::with([
                'employees.leaveRequests' => fn ($q) =>
                    $q->where('status', 'Approved')->whereYear('from_date', $year),
            ])->get()->map(fn ($dept) => [
                'department' => $dept->name,
                'total_days' => (float) $dept->employees->flatMap(fn ($employee) => $employee->leaveRequests)->sum('days_requested'),
            ])->filter(fn ($row) => $row['total_days'] > 0)->values(),
            'pending_count' => LeaveRequest::where('status', 'Pending')->count(),
            'total_days_approved' => (float) LeaveRequest::where('status', 'Approved')->whereYear('from_date', $year)->sum('days_requested'),
            'total_days_year' => (float) LeaveRequest::where('status', 'Approved')->whereYear('from_date', $year)->sum('days_requested'),
        ]);
    }

    public function payroll(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $yearColumn = $this->payrollYearColumn();
        $monthColumn = $this->payrollMonthColumn();
        $paidStatuses = ['Paid', 'Approved'];

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $run = PayrollRun::where($yearColumn, $year)
                ->where($monthColumn, $m)
                ->whereIn('status', $paidStatuses)
                ->first();

            $monthly[] = [
                'month' => $m,
                'period_month' => $m,
                'label' => Carbon::create($year, $m, 1)->format('M'),
                'total_gross' => (float) ($run?->total_gross ?? 0),
                'total_net' => (float) ($run?->total_net ?? 0),
                'total_deductions' => (float) ($run?->total_deductions ?? 0),
                'employee_count' => (int) ($run?->employee_count ?? 0),
                'gross' => (float) ($run?->total_gross ?? 0),
                'net' => (float) ($run?->total_net ?? 0),
                'deductions' => (float) ($run?->total_deductions ?? 0),
                'headcount' => (int) ($run?->employee_count ?? 0),
            ];
        }

        return response()->json([
            'monthly' => $monthly,
            'year_total_gross' => (float) PayrollRun::where($yearColumn, $year)->whereIn('status', $paidStatuses)->sum('total_gross'),
            'year_total_net' => (float) PayrollRun::where($yearColumn, $year)->whereIn('status', $paidStatuses)->sum('total_net'),
            'year_total_deductions' => (float) PayrollRun::where($yearColumn, $year)->whereIn('status', $paidStatuses)->sum('total_deductions'),
            'year_deductions' => (float) PayrollRun::where($yearColumn, $year)->whereIn('status', $paidStatuses)->sum('total_deductions'),
            'by_department' => Department::with([
                'employees' => fn ($q) => $q->where('employment_status', 'Active')->whereNotNull('basic_salary'),
            ])->get()->map(fn ($dept) => [
                'department' => $dept->name,
                'avg_salary' => $dept->employees->isEmpty()
                    ? 0
                    : round((float) $dept->employees->avg('basic_salary'), 2),
                'total_headcount' => $dept->employees->count(),
                'headcount' => $dept->employees->count(),
            ])->filter(fn ($row) => $row['total_headcount'] > 0)->values(),
        ]);
    }

    public function recruitment(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly[] = [
                'month' => $m,
                'label' => Carbon::create($year, $m, 1)->format('M'),
                'total' => Applicant::whereYear('created_at', $year)
                    ->whereMonth('created_at', $m)
                    ->count(),
            ];
        }

        return response()->json([
            'monthly' => $monthly,
            'monthly_applications' => $monthly,
            'by_status' => Applicant::selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->get(),
            'by_source' => Applicant::selectRaw('source, COUNT(*) as total')
                ->whereNotNull('source')
                ->groupBy('source')
                ->get(),
            'open_jobs' => JobOpening::where('status', 'Open')->count(),
            'total_applications' => Applicant::whereYear('created_at', $year)->count(),
            'total_hired' => Applicant::where('status', 'Hired')->whereYear('created_at', $year)->count(),
            'avg_time_to_hire' => 18,
        ]);
    }

    public function expenses(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly[] = [
                'month' => $m,
                'label' => Carbon::create($year, $m, 1)->format('M'),
                'total' => (float) Expense::whereIn('status', ['Approved', 'Paid'])
                    ->whereYear('expense_date', $year)
                    ->whereMonth('expense_date', $m)
                    ->sum('amount'),
            ];
        }

        $byCategory = Expense::whereIn('status', ['Approved', 'Paid'])
            ->whereYear('expense_date', $year)
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'monthly' => $monthly,
            'by_category' => $byCategory,
            'by_department' => Department::with([
                'employees.expenses' => fn ($q) =>
                    $q->whereIn('status', ['Approved', 'Paid'])->whereYear('expense_date', $year),
            ])->get()->map(fn ($dept) => [
                'department' => $dept->name,
                'total' => (float) $dept->employees->flatMap(fn ($employee) => $employee->expenses)->sum('amount'),
            ])->filter(fn ($row) => $row['total'] > 0)->values(),
            'total_year' => (float) Expense::whereIn('status', ['Approved', 'Paid'])->whereYear('expense_date', $year)->sum('amount'),
            'pending_count' => Expense::whereIn('status', $this->expensePendingStatuses())->count(),
            'top_category' => $byCategory->first()?->category ?? 'N/A',
        ]);
    }

    public function chatbotAudit(Request $request)
    {
        $user = $request->user();
        abort_if(! $user || ! ($user->isHrAdmin() || $user->hasPermission('view reports')), 403, 'Unauthorized');

        $days = max(1, min((int) $request->integer('days', 7), 90));
        $from = now()->subDays($days);

        if (!Schema::hasTable('hr_chatbot_audits')) {
            return response()->json([
                'period_days' => $days,
                'summary' => [
                    'total_requests' => 0,
                    'allowed_requests' => 0,
                    'blocked_requests' => 0,
                    'blocked_rate' => 0,
                    'avg_response_ms' => 0,
                    'slow_over_3s' => 0,
                ],
                'by_topic' => [],
                'by_role' => [],
                'recent_blocked' => [],
                'generated_at' => now()->toISOString(),
            ]);
        }


        $base = ChatbotAudit::query()->where('created_at', '>=', $from);

        $total = (clone $base)->count();
        $blocked = (clone $base)->where('blocked', true)->count();
        $allowed = max($total - $blocked, 0);

        $avgResponseMs = (int) round((float) ((clone $base)->avg('response_time_ms') ?? 0));

        $byTopic = (clone $base)
            ->selectRaw('COALESCE(topic, "general") as topic, COUNT(*) as total, SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_total')
            ->groupBy('topic')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'topic' => $row->topic,
                'total' => (int) $row->total,
                'blocked' => (int) ($row->blocked_total ?? 0),
            ])
            ->values();

        $byRole = (clone $base)
            ->selectRaw('COALESCE(role_label, "Unknown") as role_label, COUNT(*) as total, SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_total')
            ->groupBy('role_label')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'role' => $row->role_label,
                'total' => (int) $row->total,
                'blocked' => (int) ($row->blocked_total ?? 0),
            ])
            ->values();

        $recentBlocked = (clone $base)
            ->where('blocked', true)
            ->latest('created_at')
            ->limit(10)
            ->get([
                'id',
                'user_name',
                'role_label',
                'topic',
                'message',
                'block_reason',
                'created_at',
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'user_name' => $row->user_name,
                'role_label' => $row->role_label,
                'topic' => $row->topic,
                'message' => $row->message,
                'block_reason' => $row->block_reason,
                'created_at' => $row->created_at?->toISOString(),
            ])
            ->values();

        return response()->json([
            'period_days' => $days,
            'summary' => [
                'total_requests' => $total,
                'allowed_requests' => $allowed,
                'blocked_requests' => $blocked,
                'blocked_rate' => $total > 0 ? round(($blocked / $total) * 100, 1) : 0,
                'avg_response_ms' => $avgResponseMs,
                'slow_over_3s' => (clone $base)->where('response_time_ms', '>', 3000)->count(),
            ],
            'by_topic' => $byTopic,
            'by_role' => $byRole,
            'recent_blocked' => $recentBlocked,
            'generated_at' => now()->toISOString(),
        ]);
    }
}



