<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alignSalaryStructures();
        $this->alignPayrollRuns();
        $this->alignPayslips();
    }

    public function down(): void
    {
        // Alignment migration only; rollback intentionally non-destructive.
    }

    private function alignSalaryStructures(): void
    {
        if (! Schema::hasTable('salary_structures')) {
            return;
        }

        Schema::table('salary_structures', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_structures', 'name')) {
                $table->string('name')->nullable();
            }
            if (! Schema::hasColumn('salary_structures', 'housing_allowance')) {
                $table->decimal('housing_allowance', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('salary_structures', 'transport_allowance')) {
                $table->decimal('transport_allowance', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('salary_structures', 'meal_allowance')) {
                $table->decimal('meal_allowance', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('salary_structures', 'other_allowances')) {
                $table->decimal('other_allowances', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('salary_structures', 'ssnit_employee')) {
                $table->decimal('ssnit_employee', 5, 2)->default(5.5);
            }
            if (! Schema::hasColumn('salary_structures', 'ssnit_employer')) {
                $table->decimal('ssnit_employer', 5, 2)->default(13.0);
            }
            if (! Schema::hasColumn('salary_structures', 'income_tax_rate')) {
                $table->decimal('income_tax_rate', 5, 2)->default(0);
            }
            if (! Schema::hasColumn('salary_structures', 'status')) {
                $table->string('status')->default('Active');
            }
        });

        $rows = DB::table('salary_structures')
            ->select('id', 'employee_id', 'name', 'allowances')
            ->get();

        foreach ($rows as $row) {
            $allowances = json_decode((string) $row->allowances, true);
            if (! is_array($allowances)) {
                $allowances = [];
            }

            $housing = 0.0;
            $transport = 0.0;
            $meal = 0.0;
            $other = 0.0;

            foreach ($allowances as $item) {
                $name = strtolower((string) ($item['name'] ?? ''));
                $amount = (float) ($item['amount'] ?? 0);
                if (str_contains($name, 'housing')) {
                    $housing += $amount;
                } elseif (str_contains($name, 'transport')) {
                    $transport += $amount;
                } elseif (str_contains($name, 'meal') || str_contains($name, 'food')) {
                    $meal += $amount;
                } else {
                    $other += $amount;
                }
            }

            DB::table('salary_structures')
                ->where('id', $row->id)
                ->update([
                    'name' => $row->name ?: ('Structure ' . $row->id),
                    'housing_allowance' => $housing,
                    'transport_allowance' => $transport,
                    'meal_allowance' => $meal,
                    'other_allowances' => $other,
                    'status' => 'Active',
                ]);
        }
    }

    private function alignPayrollRuns(): void
    {
        if (! Schema::hasTable('payroll_runs')) {
            return;
        }

        Schema::table('payroll_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_runs', 'month')) {
                $table->unsignedTinyInteger('month')->nullable()->index();
            }
            if (! Schema::hasColumn('payroll_runs', 'year')) {
                $table->unsignedSmallInteger('year')->nullable()->index();
            }
        });

        if (Schema::hasColumn('payroll_runs', 'period_month')) {
            DB::table('payroll_runs')->whereNull('month')->update([
                'month' => DB::raw('period_month'),
            ]);
        }
        if (Schema::hasColumn('payroll_runs', 'period_year')) {
            DB::table('payroll_runs')->whereNull('year')->update([
                'year' => DB::raw('period_year'),
            ]);
        }

        $rows = DB::table('payroll_runs')
            ->select('id', 'status')
            ->get();

        foreach ($rows as $row) {
            $status = $row->status === 'Processing' ? 'Pending Approval' : $row->status;
            DB::table('payroll_runs')->where('id', $row->id)->update([
                'status' => $status,
            ]);
        }
    }

    private function alignPayslips(): void
    {
        if (! Schema::hasTable('payslips')) {
            return;
        }

        Schema::table('payslips', function (Blueprint $table) {
            if (! Schema::hasColumn('payslips', 'salary_structure_id')) {
                $table->unsignedBigInteger('salary_structure_id')->nullable()->index();
            }
            if (! Schema::hasColumn('payslips', 'housing_allowance')) {
                $table->decimal('housing_allowance', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('payslips', 'transport_allowance')) {
                $table->decimal('transport_allowance', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('payslips', 'meal_allowance')) {
                $table->decimal('meal_allowance', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('payslips', 'other_allowances')) {
                $table->decimal('other_allowances', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('payslips', 'income_tax')) {
                $table->decimal('income_tax', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('payslips', 'total_deductions')) {
                $table->decimal('total_deductions', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('payslips', 'status')) {
                $table->string('status')->default('Draft');
            }
            if (! Schema::hasColumn('payslips', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
        });

        $rows = DB::table('payslips')
            ->select(
                'id',
                'employee_id',
                'allowances',
                'tax_amount',
                'ssnit_employee',
                'other_deductions',
                'payment_status',
                'payment_date'
            )
            ->get();

        foreach ($rows as $row) {
            $allowances = json_decode((string) $row->allowances, true);
            if (! is_array($allowances)) {
                $allowances = [];
            }

            $housing = 0.0;
            $transport = 0.0;
            $meal = 0.0;
            $other = 0.0;

            foreach ($allowances as $item) {
                $name = strtolower((string) ($item['name'] ?? ''));
                $amount = (float) ($item['amount'] ?? 0);
                if (str_contains($name, 'housing')) {
                    $housing += $amount;
                } elseif (str_contains($name, 'transport')) {
                    $transport += $amount;
                } elseif (str_contains($name, 'meal') || str_contains($name, 'food')) {
                    $meal += $amount;
                } else {
                    $other += $amount;
                }
            }

            $status = match ($row->payment_status) {
                'Paid' => 'Paid',
                'Pending' => 'Draft',
                default => 'Draft',
            };

            $incomeTax = (float) ($row->tax_amount ?? 0);
            $ssnitEmployee = (float) ($row->ssnit_employee ?? 0);
            $otherDeductions = (float) ($row->other_deductions ?? 0);
            $totalDeductions = round($incomeTax + $ssnitEmployee + $otherDeductions, 2);

            $salaryStructureId = null;
            if (Schema::hasColumn('employees', 'salary_structure_id')) {
                $salaryStructureId = DB::table('employees')
                    ->where('id', $row->employee_id)
                    ->value('salary_structure_id');
            }

            DB::table('payslips')
                ->where('id', $row->id)
                ->update([
                    'salary_structure_id' => $salaryStructureId,
                    'housing_allowance' => $housing,
                    'transport_allowance' => $transport,
                    'meal_allowance' => $meal,
                    'other_allowances' => $other,
                    'income_tax' => $incomeTax,
                    'total_deductions' => $totalDeductions,
                    'status' => $status,
                    'paid_at' => $row->payment_date,
                ]);
        }
    }
};

