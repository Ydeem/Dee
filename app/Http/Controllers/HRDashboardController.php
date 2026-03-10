<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class HRDashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'on_leave' => Employee::where('employment_status', 'On Leave')->count(),
            'pending_approvals' => 3,
            'open_positions' => 0,
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total_employees' => Employee::count(),
            'on_leave_today' => Employee::where('employment_status', 'On Leave')->count(),
            'open_positions' => 0,
            'pending_approvals' => 5,
        ]);
    }

    public function attendanceChart(): JsonResponse
    {
        return response()->json([
            'months' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'attendance' => [88.1, 89.4, 90.0, 91.2, 90.5, 92.4, 93.1, 93.6, 94.0, 94.8, 95.6, 96.2],
            'leave' => [11.9, 10.6, 10.0, 8.8, 9.5, 7.6, 6.9, 6.4, 6.0, 5.2, 4.4, 3.8],
        ]);
    }

    public function pendingActions(): JsonResponse
    {
        return response()->json([
            'completed_tasks' => 4,
            'total_tasks' => 9,
            'actions' => [
                ['title' => 'Leave Request', 'subtitle' => 'John Mensah - Mar 11-13', 'status' => 'Pending'],
                ['title' => 'Onboarding', 'subtitle' => 'New Hire: Sarah Oti - Starts Mar 15', 'status' => 'In Progress'],
                ['title' => 'Performance Review', 'subtitle' => 'Engineering Dept - Due Mar 20', 'status' => 'In Progress'],
                ['title' => 'Payroll Approval', 'subtitle' => 'March 2026 Payroll', 'status' => 'Done'],
                ['title' => 'Exit Interview', 'subtitle' => 'Emmanuel Doe - Mar 12', 'status' => 'Pending'],
            ],
        ]);
    }

    public function recentHires(): JsonResponse
    {
        $hires = Employee::query()
            ->with(['department:id,name'])
            ->orderByDesc('join_date')
            ->limit(5)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'avatar' => $employee->avatar_url,
                'department' => $employee->department?->name,
                'join_date' => optional($employee->join_date)->format('M d, Y'),
                'status' => $employee->employment_status === 'Probation' ? 'Probation' : 'Active',
            ]);

        return response()->json(['hires' => $hires]);
    }

    public function upcomingEvents(): JsonResponse
    {
        return response()->json([
            'events' => [
                ['category' => 'Meeting', 'title' => 'All-hands HR Meeting', 'date' => 'Mar 12, 2026'],
                ['category' => 'Deadline', 'title' => 'Payroll Cutoff', 'date' => 'Mar 14, 2026'],
                ['category' => 'Review', 'title' => 'Q1 Performance Reviews', 'date' => 'Mar 20, 2026'],
                ['category' => 'Holiday', 'title' => 'Public Holiday', 'date' => 'Mar 25, 2026'],
            ],
        ]);
    }
}
