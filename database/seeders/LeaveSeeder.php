<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use Illuminate\Database\Seeder;

class LeaveSeeder extends Seeder
{
    public function run(): void
    {
        $leaveTypes = [
            ['name' => 'Annual Leave', 'days_allowed' => 21, 'color' => '#4f6ef7'],
            ['name' => 'Sick Leave', 'days_allowed' => 14, 'color' => '#f77c4f'],
            ['name' => 'Maternity Leave', 'days_allowed' => 84, 'color' => '#e91e8c'],
            ['name' => 'Paternity Leave', 'days_allowed' => 5, 'color' => '#00bcd4'],
            ['name' => 'Unpaid Leave', 'days_allowed' => 30, 'color' => '#9e9e9e'],
            ['name' => 'Study Leave', 'days_allowed' => 10, 'color' => '#8bc34a'],
        ];

        foreach ($leaveTypes as $type) {
            LeaveType::firstOrCreate(
                ['name' => $type['name']],
                array_merge($type, [
                    'carry_forward' => false,
                    'requires_approval' => true,
                    'status' => 'Active'
                ])
            );
        }

        $employees = Employee::where('employment_status', 'Active')->get();
        $leaveTypeIds = LeaveType::pluck('id')->toArray();
        $statuses = ['Pending', 'Approved', 'Approved', 'Rejected', 'Cancelled'];

        foreach ($employees as $employee) {
            for ($i = 0; $i < 3; $i++) {
                $from = now()->subDays(rand(10, 90));
                $to = $from->copy()->addDays(rand(1, 5));
                $days = $from->diffInWeekdays($to) + 1;

                LeaveRequest::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'from_date' => $from->toDateString(),
                    ],
                    [
                        'leave_type_id' => $leaveTypeIds[array_rand($leaveTypeIds)],
                        'to_date' => $to->toDateString(),
                        'days_requested' => $days,
                        'reason' => 'Personal leave request',
                        'status' => $statuses[array_rand($statuses)],
                    ]
                );
            }
        }
    }
}
