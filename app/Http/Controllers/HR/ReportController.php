<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Applicant;
use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Expense;
use App\Models\HR\JobOpening;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\HR\PayrollRun;
use Illuminate\Http\Request;
class ReportController extends Controller
{
    public function workforce(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $month = (int) ($request->month ?? now()->month);

        return response()->json([
            'by_department' => Department::withCount([
                'employees as total',
                'employees as active' => fn ($q) => $q->where('employment_status', 'Active'),
            ])->get(['id', 'name']),
            'by_type' => Employee::where('employment_status', 'Active')
                ->selectRaw('employment_type, count(*) as total')
                ->groupBy('employment_type')
                ->get(),
            'monthly_hires' => Employee::whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
            'by_gender' => Employee::where('employment_status', 'Active')
                ->selectRaw('gender, count(*) as total')
                ->groupBy('gender')
                ->get(),
            'total_active' => Employee::where('employment_status', 'Active')->count(),
            'total_inactive' => Employee::where('employment_status', 'Inactive')->count(),
            'new_this_month' => Employee::whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->count(),
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
                'employees.attendances' => fn ($q) =>
                    $q->whereMonth('date', $month)->whereYear('date', $year),
            ])->get()->map(fn ($dept) => [
                'department' => $dept->name,
                'present_rate' => round($dept->employees->flatMap(fn ($employee) => $employee->attendances)->avg(
                    fn ($attendance) => $attendance->status === 'Present' ? 100 : 0
                ) ?? 0, 2),
            ])->values(),
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

        return response()->json([
            'by_type' => LeaveType::leftJoin('leave_requests', function ($join) use ($year) {
                $join->on('leave_types.id', '=', 'leave_requests.leave_type_id')
                    ->where('leave_requests.status', '=', 'Approved')
                    ->whereYear('leave_requests.from_date', '=', $year);
            })
                ->groupBy('leave_types.id', 'leave_types.name')
                ->selectRaw('leave_types.id, leave_types.name, COALESCE(SUM(leave_requests.days_requested), 0) as total_days, COUNT(leave_requests.id) as total_requests')
                ->orderBy('leave_types.name')
                ->get(),
            'monthly' => LeaveRequest::where('status', 'Approved')
                ->whereYear('from_date', $year)
                ->selectRaw('MONTH(from_date) as month, SUM(days_requested) as days')
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
            'by_department' => Department::with([
                'employees.leaveRequests' => fn ($q) =>
                    $q->where('status', 'Approved')->whereYear('from_date', $year),
            ])->get()->map(fn ($dept) => [
                'department' => $dept->name,
                'total_days' => (float) $dept->employees->flatMap(fn ($employee) => $employee->leaveRequests)->sum('days_requested'),
            ])->values(),
            'pending_count' => LeaveRequest::where('status', 'Pending')->count(),
            'total_days_approved' => (float) LeaveRequest::where('status', 'Approved')->whereYear('from_date', $year)->sum('days_requested'),
        ]);
    }

    public function payroll(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);

        return response()->json([
            'monthly' => PayrollRun::where('period_year', $year)
                ->where('status', 'Paid')
                ->orderBy('period_month')
                ->get(['period_month', 'total_gross', 'total_deductions', 'total_net', 'employee_count']),
            'year_total_gross' => (float) PayrollRun::where('period_year', $year)->where('status', 'Paid')->sum('total_gross'),
            'year_total_net' => (float) PayrollRun::where('period_year', $year)->where('status', 'Paid')->sum('total_net'),
            'year_total_deductions' => (float) PayrollRun::where('period_year', $year)->where('status', 'Paid')->sum('total_deductions'),
            'by_department' => Department::with(['employees.salaryStructure'])->get()->map(fn ($dept) => [
                'department' => $dept->name,
                'avg_salary' => round($dept->employees->map(fn ($employee) => $employee->salaryStructure?->basic_salary)->filter()->avg() ?? 0, 2),
                'total_headcount' => $dept->employees->count(),
            ])->values(),
        ]);
    }

    public function recruitment(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);

        return response()->json([
            'monthly_applications' => Applicant::whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
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

        return response()->json([
            'monthly' => Expense::whereYear('expense_date', $year)
                ->whereIn('status', ['Approved', 'Paid'])
                ->selectRaw('MONTH(expense_date) as month, SUM(amount) as total')
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
            'by_category' => Expense::whereIn('status', ['Approved', 'Paid'])
                ->whereYear('expense_date', $year)
                ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get(),
            'by_department' => Department::with([
                'employees.expenses' => fn ($q) =>
                    $q->whereIn('status', ['Approved', 'Paid'])->whereYear('expense_date', $year),
            ])->get()->map(fn ($dept) => [
                'department' => $dept->name,
                'total' => (float) $dept->employees->flatMap(fn ($employee) => $employee->expenses)->sum('amount'),
            ])->values(),
            'total_year' => (float) Expense::whereIn('status', ['Approved', 'Paid'])->whereYear('expense_date', $year)->sum('amount'),
            'pending_count' => Expense::whereIn('status', ['Submitted', 'Under Review'])->count(),
        ]);
    }
}
