<?php

namespace Database\Seeders;

use App\Models\HR\Employee;
use App\Models\HR\PayrollRun;
use App\Models\HR\Payslip;
use App\Models\HR\SalaryStructure;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PayrollSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSalaryStructures();
        $this->assignStructuresToEmployees();
        $this->seedPayrollRunsAndPayslips();
    }

    private function seedSalaryStructures(): void
    {
        $structures = [
            [
                'name' => 'Junior Staff',
                'basic_salary' => 3000,
                'housing_allowance' => 500,
                'transport_allowance' => 300,
                'meal_allowance' => 200,
                'other_allowances' => 0,
                'ssnit_employee' => 5.5,
                'ssnit_employer' => 13.0,
            ],
            [
                'name' => 'Mid-Level Staff',
                'basic_salary' => 5000,
                'housing_allowance' => 800,
                'transport_allowance' => 500,
                'meal_allowance' => 300,
                'other_allowances' => 0,
                'ssnit_employee' => 5.5,
                'ssnit_employer' => 13.0,
            ],
            [
                'name' => 'Senior Staff',
                'basic_salary' => 8000,
                'housing_allowance' => 1500,
                'transport_allowance' => 800,
                'meal_allowance' => 500,
                'other_allowances' => 0,
                'ssnit_employee' => 5.5,
                'ssnit_employer' => 13.0,
            ],
            [
                'name' => 'Management',
                'basic_salary' => 12000,
                'housing_allowance' => 2500,
                'transport_allowance' => 1200,
                'meal_allowance' => 800,
                'other_allowances' => 500,
                'ssnit_employee' => 5.5,
                'ssnit_employer' => 13.0,
            ],
            [
                'name' => 'Executive',
                'basic_salary' => 20000,
                'housing_allowance' => 5000,
                'transport_allowance' => 2000,
                'meal_allowance' => 1000,
                'other_allowances' => 2000,
                'ssnit_employee' => 5.5,
                'ssnit_employer' => 13.0,
            ],
        ];

        foreach ($structures as $data) {
            SalaryStructure::firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['status' => 'Active', 'income_tax_rate' => 0])
            );
        }

        SalaryStructure::query()
            ->whereNotIn('name', collect($structures)->pluck('name'))
            ->delete();
    }

    private function assignStructuresToEmployees(): void
    {
        $employees = Employee::active()->get();
        $structures = SalaryStructure::active()->orderBy('id')->get();

        if ($employees->isEmpty() || $structures->isEmpty()) {
            return;
        }

        foreach ($employees as $index => $employee) {
            if (! $employee->salary_structure_id) {
                $structure = $structures[$index % $structures->count()];
                $employee->update(['salary_structure_id' => $structure->id]);
            }
        }
    }

    private function seedPayrollRunsAndPayslips(): void
    {
        $employees = Employee::active()->with('salaryStructure')->get();
        if ($employees->isEmpty()) {
            return;
        }

        for ($offset = 2; $offset >= 0; $offset--) {
            $period = now()->subMonths($offset);
            $month = $period->month;
            $year = $period->year;

            $status = $offset === 0 ? 'Pending Approval' : 'Paid';

            $run = PayrollRun::updateOrCreate(
                ['month' => $month, 'year' => $year],
                [
                    'pay_date' => $period->copy()->endOfMonth()->toDateString(),
                    'status' => $status,
                    'employee_count' => $employees->count(),
                    'notes' => null,
                ]
            );

            $totalGross = 0.0;
            $totalDeductions = 0.0;
            $totalNet = 0.0;

            foreach ($employees as $employee) {
                $structure = $employee->salaryStructure;

                $basic = (float) ($employee->basic_salary ?? $structure?->basic_salary ?? 0);
                $housing = (float) ($structure?->housing_allowance ?? 0);
                $transport = (float) ($structure?->transport_allowance ?? 0);
                $meal = (float) ($structure?->meal_allowance ?? 0);
                $other = (float) ($structure?->other_allowances ?? 0);

                $gross = $basic + $housing + $transport + $meal + $other;
                $ssnitEmp = round($gross * ((float) ($structure?->ssnit_employee ?? 5.5) / 100), 2);
                $ssnitEmpr = round($gross * ((float) ($structure?->ssnit_employer ?? 13.0) / 100), 2);
                $incomeTax = SalaryStructure::calculateGhanaTax($gross - $ssnitEmp);
                $totalDeduction = round($ssnitEmp + $incomeTax, 2);
                $net = round($gross - $totalDeduction, 2);

                Payslip::updateOrCreate(
                    [
                        'payroll_run_id' => $run->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'salary_structure_id' => $employee->salary_structure_id,
                        'basic_salary' => $basic,
                        'housing_allowance' => $housing,
                        'transport_allowance' => $transport,
                        'meal_allowance' => $meal,
                        'other_allowances' => $other,
                        'gross_salary' => $gross,
                        'ssnit_employee' => $ssnitEmp,
                        'ssnit_employer' => $ssnitEmpr,
                        'income_tax' => $incomeTax,
                        'other_deductions' => 0,
                        'total_deductions' => $totalDeduction,
                        'net_salary' => $net,
                        'status' => $status === 'Paid' ? 'Paid' : 'Draft',
                        'paid_at' => $status === 'Paid'
                            ? Carbon::parse($run->pay_date)->endOfDay()
                            : null,
                    ]
                );

                $totalGross += $gross;
                $totalDeductions += $totalDeduction;
                $totalNet += $net;
            }

            $run->update([
                'total_gross' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_net' => round($totalNet, 2),
            ]);
        }
    }
}
