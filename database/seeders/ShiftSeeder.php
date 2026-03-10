<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\HR\Shift;
use App\Models\HR\ShiftSchedule;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = [
            [
                'name' => 'Morning Shift',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'break_duration' => 60,
                'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'color' => '#4f6ef7',
                'status' => 'Active',
            ],
            [
                'name' => 'Afternoon Shift',
                'start_time' => '13:00',
                'end_time' => '22:00',
                'break_duration' => 60,
                'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'color' => '#f77c4f',
                'status' => 'Active',
            ],
            [
                'name' => 'Night Shift',
                'start_time' => '22:00',
                'end_time' => '06:00',
                'break_duration' => 30,
                'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'color' => '#9c27b0',
                'status' => 'Active',
            ],
            [
                'name' => 'Weekend Shift',
                'start_time' => '09:00',
                'end_time' => '15:00',
                'break_duration' => 30,
                'working_days' => ['Saturday', 'Sunday'],
                'color' => '#00bcd4',
                'status' => 'Active',
            ],
        ];

        foreach ($shifts as $shiftData) {
            Shift::firstOrCreate(['name' => $shiftData['name']], $shiftData);
        }

        $employees = Employee::where('employment_status', 'Active')->get();
        $allShifts = Shift::where('status', 'Active')->get();

        foreach ($employees as $employee) {
            $shift = $allShifts->random();

            ShiftSchedule::firstOrCreate(
                ['employee_id' => $employee->id, 'effective_to' => null],
                [
                    'shift_id' => $shift->id,
                    'effective_from' => now()->subMonths(rand(1, 6))->toDateString(),
                    'effective_to' => null,
                ]
            );

            $employee->update(['shift_id' => $shift->id]);
        }
    }
}
