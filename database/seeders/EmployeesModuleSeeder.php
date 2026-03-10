<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeActivityLog;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeLeaveHistory;
use App\Models\EmployeePayroll;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class EmployeesModuleSeeder extends Seeder
{
    public function run(): void
    {
        $departments = collect(['People Operations', 'Engineering', 'Finance', 'Sales', 'Customer Success'])
            ->map(fn ($name) => Department::firstOrCreate(['name' => $name]));

        $designationMap = [
            'People Operations' => ['HR Manager', 'Talent Acquisition Specialist'],
            'Engineering' => ['Software Engineer', 'Engineering Lead'],
            'Finance' => ['Accountant', 'Payroll Specialist'],
            'Sales' => ['Sales Executive', 'Account Manager'],
            'Customer Success' => ['Customer Success Manager', 'Support Specialist'],
        ];

        foreach ($designationMap as $departmentName => $designationNames) {
            $department = $departments->firstWhere('name', $departmentName);
            foreach ($designationNames as $designationName) {
                Designation::firstOrCreate([
                    'department_id' => $department?->id,
                    'name' => $designationName,
                ]);
            }
        }

        $shiftMorning = Shift::firstOrCreate(['name' => 'Morning Shift'], ['start_time' => '08:00', 'end_time' => '17:00']);
        $shiftHybrid = Shift::firstOrCreate(['name' => 'Hybrid Shift'], ['start_time' => '09:00', 'end_time' => '18:00']);

        $manager = Employee::updateOrCreate(
            ['employee_id' => 'EMP00001'],
            [
                'first_name' => 'Pontian',
                'last_name' => 'Npontu',
                'phone' => '+233240000001',
                'personal_email' => 'pontian.npontu@example.com',
                'work_email' => 'pontian.npontu@company.com',
                'department_id' => $departments->firstWhere('name', 'People Operations')?->id,
                'designation_id' => Designation::where('name', 'HR Manager')->value('id'),
                'employment_type' => 'Full-time',
                'employment_status' => 'Active',
                'join_date' => '2024-01-08',
                'work_location' => 'Hybrid',
                'shift_id' => $shiftHybrid->id,
                'basic_salary' => 9500,
                'pay_frequency' => 'Monthly',
                'allowances' => [
                    ['type' => 'Transport', 'amount' => 450],
                    ['type' => 'Housing', 'amount' => 1200],
                ],
                'skills' => ['People Management', 'Recruitment', 'Payroll'],
                'bio' => 'Leads HR operations and policy execution.',
                'notes' => 'Primary approver for workforce changes.',
                'last_active_at' => now()->subMinutes(15),
            ]
        );

        $employees = [
            [
                'employee_id' => 'EMP00002',
                'first_name' => 'Sarah',
                'last_name' => 'Oti',
                'phone' => '+233240000002',
                'personal_email' => 'sarah.oti@example.com',
                'work_email' => 'sarah.oti@company.com',
                'department' => 'People Operations',
                'designation' => 'Talent Acquisition Specialist',
                'employment_type' => 'Full-time',
                'employment_status' => 'Probation',
                'join_date' => '2026-03-03',
            ],
            [
                'employee_id' => 'EMP00003',
                'first_name' => 'Daniel',
                'last_name' => 'Kofi',
                'phone' => '+233240000003',
                'personal_email' => 'daniel.kofi@example.com',
                'work_email' => 'daniel.kofi@company.com',
                'department' => 'Engineering',
                'designation' => 'Software Engineer',
                'employment_type' => 'Full-time',
                'employment_status' => 'Active',
                'join_date' => '2025-10-14',
            ],
            [
                'employee_id' => 'EMP00004',
                'first_name' => 'Amanda',
                'last_name' => 'Boateng',
                'phone' => '+233240000004',
                'personal_email' => 'amanda.boateng@example.com',
                'work_email' => 'amanda.boateng@company.com',
                'department' => 'Finance',
                'designation' => 'Payroll Specialist',
                'employment_type' => 'Contract',
                'employment_status' => 'On Leave',
                'join_date' => '2025-05-01',
            ],
        ];

        foreach ($employees as $employeeData) {
            $employee = Employee::updateOrCreate(
                ['employee_id' => $employeeData['employee_id']],
                [
                    'first_name' => $employeeData['first_name'],
                    'last_name' => $employeeData['last_name'],
                    'phone' => $employeeData['phone'],
                    'personal_email' => $employeeData['personal_email'],
                    'work_email' => $employeeData['work_email'],
                    'department_id' => $departments->firstWhere('name', $employeeData['department'])?->id,
                    'designation_id' => Designation::where('name', $employeeData['designation'])->value('id'),
                    'employment_type' => $employeeData['employment_type'],
                    'employment_status' => $employeeData['employment_status'],
                    'join_date' => $employeeData['join_date'],
                    'reporting_manager_id' => $manager->id,
                    'work_location' => 'Office',
                    'shift_id' => $shiftMorning->id,
                    'basic_salary' => 4200,
                    'pay_frequency' => 'Monthly',
                    'allowances' => [['type' => 'Transport', 'amount' => 250]],
                    'skills' => ['Communication', 'Teamwork'],
                    'last_active_at' => now()->subHours(rand(1, 24)),
                ]
            );

            EmployeeLeaveBalance::updateOrCreate(
                ['employee_id' => $employee->id, 'leave_type' => 'Annual Leave'],
                ['used_days' => 4, 'total_days' => 20]
            );

            EmployeeLeaveBalance::updateOrCreate(
                ['employee_id' => $employee->id, 'leave_type' => 'Sick Leave'],
                ['used_days' => 1, 'total_days' => 10]
            );

            EmployeeLeaveHistory::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'leave_type' => 'Annual Leave',
                    'from_date' => '2026-02-10',
                    'to_date' => '2026-02-12',
                ],
                ['days' => 3, 'status' => 'Approved', 'approved_by' => 'Pontian Npontu']
            );

            EmployeeAttendance::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'date' => now()->toDateString(),
                ],
                ['check_in' => '08:57', 'check_out' => '17:10', 'hours' => 8.2, 'status' => 'Present']
            );

            EmployeePayroll::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'pay_month' => '2026-02-01',
                ],
                ['gross' => 4500, 'deductions' => 320, 'net' => 4180, 'status' => 'Processed']
            );

            EmployeeActivityLog::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'action' => 'Profile Updated',
                    'description' => 'Employee profile synced with HR module defaults.',
                ],
                ['actor_name' => 'System']
            );
        }
    }
}
