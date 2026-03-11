<?php

namespace Database\Seeders;

use App\Models\HR\Employee;
use App\Models\HR\Expense;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('employment_status', 'Active')->get();

        $categories = [
            'Travel',
            'Meals',
            'Accommodation',
            'Equipment',
            'Training',
            'Medical',
            'Communication',
            'Other',
        ];

        $statuses = [
            'Pending',
            'Pending',
            'Approved',
            'Approved',
            'Rejected',
            'Paid',
        ];

        $titles = [
            'Flight to client site',
            'Team lunch',
            'Hotel for conference',
            'External monitor',
            'Online training subscription',
            'Medical checkup',
            'Phone data bundle',
            'Office supplies',
        ];

        foreach ($employees as $employee) {
            $count = rand(2, 5);

            for ($i = 0; $i < $count; $i += 1) {
                $status = $statuses[array_rand($statuses)];
                $daysAgo = rand(1, 90);

                Expense::create([
                    'employee_id' => $employee->id,
                    'category' => $categories[array_rand($categories)],
                    'title' => $titles[array_rand($titles)],
                    'amount' => rand(50, 2000) + (rand(0, 99) / 100),
                    'currency' => 'GHS',
                    'expense_date' => now()->subDays($daysAgo)->toDateString(),
                    'status' => $status,
                    'description' => 'Business expense claim',
                    'approved_at' => in_array($status, ['Approved', 'Paid'], true)
                        ? now()->subDays(max($daysAgo - 1, 0))
                        : null,
                    'paid_at' => $status === 'Paid'
                        ? now()->subDays(max($daysAgo - 2, 0))
                        : null,
                ]);
            }
        }
    }
}
