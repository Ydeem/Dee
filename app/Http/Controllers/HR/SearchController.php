<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\JobOpening;
use App\Models\HR\LeaveRequest;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $q = trim((string) $request->q);

        if (strlen($q) < 2) {
            return response()->json([
                'results' => [],
            ]);
        }

        $results = [];

        $employees = Employee::where(function ($query) use ($q) {
            $query->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('employee_id', 'like', "%{$q}%")
                ->orWhere('personal_email', 'like', "%{$q}%");
        })->with('department:id,name')
            ->limit(5)
            ->get();

        foreach ($employees as $emp) {
            $results[] = [
                'id' => $emp->id,
                'type' => 'Employees',
                'icon' => 'mdi-account',
                'title' => $emp->full_name,
                'subtitle' => ($emp->department?->name ?? 'No dept').' · '.$emp->employee_id,
                'link' => '/hr/employees/'.$emp->id,
            ];
        }

        $leaves = LeaveRequest::with('employee:id,first_name,last_name')
            ->whereHas('employee', function ($query) use ($q) {
                $query->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%");
            })
            ->latest()
            ->limit(3)
            ->get();

        foreach ($leaves as $leave) {
            $results[] = [
                'id' => $leave->id,
                'type' => 'Leave',
                'icon' => 'mdi-calendar-remove',
                'title' => ($leave->employee?->full_name ?? 'Employee').' — Leave Request',
                'subtitle' => $leave->status.' · '.$leave->from_date.' to '.$leave->to_date,
                'link' => '/hr/leave-management',
            ];
        }

        $jobs = JobOpening::with('department:id,name')
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhereHas('department', fn ($departmentQuery) => $departmentQuery->where('name', 'like', "%{$q}%"));
            })
            ->limit(3)
            ->get();

        foreach ($jobs as $job) {
            $results[] = [
                'id' => $job->id,
                'type' => 'Jobs',
                'icon' => 'mdi-briefcase',
                'title' => $job->title,
                'subtitle' => ($job->department?->name ?? 'No dept').' · '.$job->status,
                'link' => '/hr/job-openings',
            ];
        }

        $departments = Department::where('name', 'like', "%{$q}%")
            ->limit(3)
            ->get();

        foreach ($departments as $department) {
            $results[] = [
                'id' => $department->id,
                'type' => 'Departments',
                'icon' => 'mdi-office-building',
                'title' => $department->name,
                'subtitle' => 'Department',
                'link' => '/hr/departments',
            ];
        }

        return response()->json([
            'results' => $results,
        ]);
    }
}
