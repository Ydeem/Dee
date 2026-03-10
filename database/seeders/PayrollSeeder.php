<?php

namespace Database\Seeders;

use App\Models\HR\Employee;
use App\Models\HR\PayrollRun;
use App\Models\HR\Payslip;
use App\Models\HR\SalaryStructure;
use Illuminate\Database\Seeder;

class PayrollSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('employment_status', 'Active')->get();

        $allowanceTemplates = [
            [
                ['name' => 'Housing Allowance', 'amount' => 500],
                ['name' => 'Transport Allowance', 'amount' => 200],
            ],
            [
                ['name' => 'Housing Allowance', 'amount' => 800],
                ['name' => 'Transport Allowance', 'amount' => 300],
                ['name' => 'Medical Allowance', 'amount' => 200],
            ],
            [
                ['name' => 'Transport Allowance', 'amount' => 150],
            ],
        ];

        foreach ($employees as $employee) {
            $basicSalary = rand(2500, 12000);
            $allowances = $allowanceTemplates[array_rand($allowanceTemplates)];

            SalaryStructure::firstOrCreate(
                ['employee_id' => $employee->id],
                [
                    'basic_salary' => $basicSalary,
                    'allowances' => $allowances,
                    'effective_date' => now()->subMonths(rand(1, 12))->toDateString(),
                    'currency' => 'GHS',
                    'pay_frequency' => 'Monthly',
                ]
            );
        }

        for ($i = 3; $i >= 1; $i--) {
            $baseDate = now()->subMonths($i);
            $periodMonth = $baseDate->month;
            $periodYear = $baseDate->year;

            $run = PayrollRun::firstOrCreate(
                [
                    'period_month' => $periodMonth,
                    'period_year' => $periodYear,
                ],
                [
                    'title' => $baseDate->format('F Y') . ' Payroll',
                    'pay_date' => $baseDate->copy()->endOfMonth()->toDateString(),
                    'status' => 'Paid',
                    'employee_count' => $employees->count(),
                    'total_gross' => 0,
                    'total_deductions' => 0,
                    'total_net' => 0,
                ]
            );

            $totalGross = 0.0;
            $totalDed = 0.0;
            $totalNet = 0.0;

            foreach ($employees as $employee) {
                $salary = SalaryStructure::where('employee_id', $employee->id)->first();
                if (!$salary) {
                    continue;
                }

                $allowanceTotal = collect($salary->allowances ?? [])->sum('amount');
                $gross = (float) $salary->basic_salary + (float) $allowanceTotal;
                $tax = round($gross * 0.12, 2);
                $ssnit = round($gross * 0.055, 2);
                $net = round($gross - $tax - $ssnit, 2);

                Payslip::firstOrCreate(
                    [
                        'payroll_run_id' => $run->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'basic_salary' => $salary->basic_salary,
                        'allowances' => $salary->allowances ?? [],
                        'deductions' => [],
                        'gross_salary' => $gross,
                        'tax_amount' => $tax,
                        'ssnit_employee' => $ssnit,
                        'ssnit_employer' => round($gross * 0.13, 2),
                        'other_deductions' => 0,
                        'net_salary' => $net,
                        'payment_status' => 'Paid',
                        'payment_method' => 'Bank Transfer',
                        'payment_date' => $run->pay_date,
                        'bank_name' => $employee->bank_name,
                        'account_number' => $employee->account_number,
                    ]
                );

                $totalGross += $gross;
                $totalDed += ($tax + $ssnit);
                $totalNet += $net;
            }

            $run->update([
                'status' => 'Paid',
                'total_gross' => round($totalGross, 2),
                'total_deductions' => round($totalDed, 2),
                'total_net' => round($totalNet, 2),
                'employee_count' => $employees->count(),
            ]);
        }

        $thisMonth = now();
        PayrollRun::firstOrCreate(
            [
                'period_month' => $thisMonth->month,
                'period_year' => $thisMonth->year,
            ],
            [
                'title' => $thisMonth->format('F Y') . ' Payroll',
                'pay_date' => $thisMonth->copy()->endOfMonth()->toDateString(),
                'status' => 'Draft',
                'employee_count' => 0,
                'total_gross' => 0,
                'total_deductions' => 0,
                'total_net' => 0,
            ]
        );
    }
}

