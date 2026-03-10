<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\HR\Attendance;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('employment_status', 'Active')->get();
        $statuses = ['Present', 'Present', 'Present', 'Present', 'Late', 'Absent', 'On Leave'];

        for ($i = 0; $i < 30; $i++) {
            $date = now()->subDays($i)->toDateString();

            $dayOfWeek = Carbon::parse($date)->dayOfWeek;
            if ($dayOfWeek === 0 || $dayOfWeek === 6) {
                continue;
            }

            foreach ($employees as $employee) {
                $status = $statuses[array_rand($statuses)];

                $checkIn = null;
                $checkOut = null;
                $hours = null;

                if (in_array($status, ['Present', 'Late'], true)) {
                    $checkIn = $status === 'Late' ? '09:30' : '08:00';
                    $checkOut = '17:00';
                    $hours = $status === 'Late' ? 7.5 : 9.0;
                }

                Attendance::firstOrCreate(
                    ['employee_id' => $employee->id, 'date' => $date],
                    [
                        'check_in' => $checkIn ? $date . ' ' . $checkIn : null,
                        'check_out' => $checkOut ? $date . ' ' . $checkOut : null,
                        'hours_worked' => $hours,
                        'status' => $status,
                    ]
                );
            }
        }
    }
}
