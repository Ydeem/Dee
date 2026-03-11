<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\HR\PayrollRun;
use App\Models\HR\Payslip;
use App\Models\HR\SalaryStructure;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);

        $runs = PayrollRun::forYear($year)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('month', 'desc')
            ->get()
            ->map(fn (PayrollRun $run) => [
                'id' => $run->id,
                'month' => $run->month,
                'year' => $run->year,
                'month_label' => $run->month_label,
                'pay_date' => Carbon::parse($run->pay_date)->format('M d, Y'),
                'pay_date_raw' => $run->pay_date?->format('Y-m-d'),
                'status' => $run->status,
                'status_color' => $run->status_color,
                'total_gross' => number_format((float) $run->total_gross, 2),
                'total_deductions' => number_format((float) $run->total_deductions, 2),
                'total_net' => number_format((float) $run->total_net, 2),
                'employee_count' => $run->employee_count,
                'notes' => $run->notes,
                'approved_at' => $run->approved_at
                    ? Carbon::parse($run->approved_at)->format('M d, Y')
                    : null,
            ]);

        $currentMonth = now()->month;
        $currentYear = now()->year;

        $currentRun = PayrollRun::query()
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->first();

        $totalPaidYear = PayrollRun::query()
            ->where('year', $currentYear)
            ->where('status', 'Paid')
            ->sum('total_net');

        $pendingApproval = PayrollRun::query()
            ->where('status', 'Pending Approval')
            ->count();

        $employeesOnPayroll = Employee::query()
            ->where('employment_status', 'Active')
            ->count();

        return response()->json([
            'runs' => $runs,
            'stats' => [
                'total_paid_year' => 'GHS ' . number_format((float) $totalPaidYear, 2),
                'pending_approval' => $pendingApproval,
                'current_month_status' => $currentRun?->status ?? 'Not Started',
                'employees_on_payroll' => $employeesOnPayroll,
            ],
            'years' => range((int) now()->year, 2020),
        ]);
    }

    public function generateRun(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020',
            'pay_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $existing = PayrollRun::query()
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->whereNotIn('status', ['Cancelled'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A payroll run already exists for '
                    . Carbon::create($request->year, $request->month, 1)->format('F Y')
                    . '. Status: ' . $existing->status,
            ], 422);
        }

        $employees = Employee::query()
            ->where('employment_status', 'Active')
            ->with('salaryStructure')
            ->get();

        if ($employees->isEmpty()) {
            return response()->json([
                'message' => 'No active employees found to process payroll.',
            ], 422);
        }

        $run = PayrollRun::create([
            'month' => $request->month,
            'year' => $request->year,
            'pay_date' => $request->pay_date,
            'status' => 'Draft',
            'notes' => $request->notes,
            'employee_count' => $employees->count(),
        ]);

        $totalGross = 0.0;
        $totalDeductions = 0.0;
        $totalNet = 0.0;

        foreach ($employees as $emp) {
            $structure = $emp->salaryStructure;

            $basic = (float) ($emp->basic_salary ?? $structure?->basic_salary ?? 0);
            $housing = (float) ($structure?->housing_allowance ?? 0);
            $transport = (float) ($structure?->transport_allowance ?? 0);
            $meal = (float) ($structure?->meal_allowance ?? 0);
            $other = (float) ($structure?->other_allowances ?? 0);

            $gross = $basic + $housing + $transport + $meal + $other;

            $ssnitRate = (float) ($structure?->ssnit_employee ?? 5.5);
            $ssnitEmp = round($gross * ($ssnitRate / 100), 2);
            $ssnitEmpr = round($gross * ((float) ($structure?->ssnit_employer ?? 13.0) / 100), 2);

            $taxableIncome = $gross - $ssnitEmp;
            $incomeTax = (float) ($structure?->income_tax_rate ?? 0) > 0
                ? round($taxableIncome * ((float) $structure->income_tax_rate / 100), 2)
                : SalaryStructure::calculateGhanaTax($taxableIncome);

            $deductions = round($ssnitEmp + $incomeTax, 2);
            $net = round($gross - $deductions, 2);

            Payslip::create([
                'payroll_run_id' => $run->id,
                'employee_id' => $emp->id,
                'salary_structure_id' => $emp->salary_structure_id,
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
                'total_deductions' => $deductions,
                'net_salary' => $net,
                'status' => 'Draft',
            ]);

            $totalGross += $gross;
            $totalDeductions += $deductions;
            $totalNet += $net;
        }

        $run->update([
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net' => $totalNet,
            'status' => 'Pending Approval',
        ]);

        return response()->json([
            'run' => $run,
            'message' => $run->month_label . ' payroll generated for ' . $employees->count() . ' employees.',
        ], 201);
    }

    public function approve(int $id)
    {
        $run = PayrollRun::findOrFail($id);

        if ($run->status !== 'Pending Approval') {
            return response()->json([
                'message' => 'Only runs with "Pending Approval" status can be approved.',
            ], 422);
        }

        $run->update([
            'status' => 'Approved',
            'approved_by' => $this->authEmployeeId(),
            'approved_at' => now(),
        ]);

        $run->payslips()->update([
            'status' => 'Approved',
        ]);

        return response()->json([
            'message' => $run->month_label . ' payroll approved.',
        ]);
    }

    public function markPaid(int $id)
    {
        $run = PayrollRun::findOrFail($id);

        if ($run->status !== 'Approved') {
            return response()->json([
                'message' => 'Only Approved runs can be marked as Paid.',
            ], 422);
        }

        $run->update(['status' => 'Paid']);

        $run->payslips()->update([
            'status' => 'Paid',
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => $run->month_label . ' payroll marked as Paid.',
        ]);
    }

    public function cancel(int $id)
    {
        $run = PayrollRun::findOrFail($id);

        if ($run->status === 'Paid') {
            return response()->json([
                'message' => 'Cannot cancel a paid payroll run.',
            ], 422);
        }

        $run->update(['status' => 'Cancelled']);
        $run->payslips()->delete();

        return response()->json([
            'message' => 'Payroll run cancelled.',
        ]);
    }

    public function payslips(Request $request, int $runId)
    {
        $run = PayrollRun::findOrFail($runId);

        $payslips = Payslip::with([
            'employee:id,first_name,last_name,employee_id,avatar,department_id',
            'employee.department:id,name',
        ])
            ->where('payroll_run_id', $runId)
            ->when($request->search, fn ($q) =>
                $q->whereHas('employee', fn ($sq) =>
                    $sq->where('first_name', 'like', '%' . $request->search . '%')
                        ->orWhere('last_name', 'like', '%' . $request->search . '%')
                )
            )
            ->orderBy('created_at')
            ->paginate((int) ($request->per_page ?? 20));

        $payslips->through(fn (Payslip $p) => [
            'id' => $p->id,
            'basic_salary' => number_format((float) $p->basic_salary, 2),
            'housing' => number_format((float) $p->housing_allowance, 2),
            'transport' => number_format((float) $p->transport_allowance, 2),
            'gross_salary' => number_format((float) $p->gross_salary, 2),
            'ssnit_employee' => number_format((float) $p->ssnit_employee, 2),
            'income_tax' => number_format((float) $p->income_tax, 2),
            'total_deductions' => number_format((float) $p->total_deductions, 2),
            'net_salary' => number_format((float) $p->net_salary, 2),
            'status' => $p->status,
            'employee' => $p->employee ? [
                'id' => $p->employee->id,
                'name' => trim($p->employee->first_name . ' ' . $p->employee->last_name),
                'emp_id' => $p->employee->employee_id,
                'initials' => strtoupper(
                    substr($p->employee->first_name ?? '', 0, 1)
                    . substr($p->employee->last_name ?? '', 0, 1)
                ),
                'department' => $p->employee->department?->name ?? '-',
            ] : null,
        ]);

        return response()->json([
            'run' => [
                'id' => $run->id,
                'month_label' => $run->month_label,
                'status' => $run->status,
                'pay_date' => Carbon::parse($run->pay_date)->format('M d, Y'),
            ],
            'payslips' => $payslips,
        ]);
    }

    public function allPayslips(Request $request)
    {
        $payslips = Payslip::with([
            'employee:id,first_name,last_name,employee_id,department_id',
            'employee.department:id,name',
            'payrollRun:id,month,year,status',
        ])
            ->when($request->search, fn ($q) =>
                $q->whereHas('employee', fn ($sq) =>
                    $sq->where('first_name', 'like', '%' . $request->search . '%')
                        ->orWhere('last_name', 'like', '%' . $request->search . '%')
                )
            )
            ->when($request->month, fn ($q) =>
                $q->whereHas('payrollRun', fn ($sq) => $sq->where('month', $request->month))
            )
            ->when($request->year, fn ($q) =>
                $q->whereHas('payrollRun', fn ($sq) => $sq->where('year', $request->year))
            )
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc')
            ->paginate((int) ($request->per_page ?? 15));

        $payslips->through(fn (Payslip $p) => [
            'id' => $p->id,
            'period' => $p->payrollRun
                ? Carbon::create($p->payrollRun->year, $p->payrollRun->month, 1)->format('F Y')
                : '-',
            'gross_salary' => number_format((float) $p->gross_salary, 2),
            'total_deductions' => number_format((float) $p->total_deductions, 2),
            'net_salary' => number_format((float) $p->net_salary, 2),
            'status' => $p->status,
            'employee' => $p->employee ? [
                'id' => $p->employee->id,
                'name' => trim($p->employee->first_name . ' ' . $p->employee->last_name),
                'emp_id' => $p->employee->employee_id,
                'initials' => strtoupper(
                    substr($p->employee->first_name ?? '', 0, 1)
                    . substr($p->employee->last_name ?? '', 0, 1)
                ),
                'department' => $p->employee->department?->name ?? '-',
            ] : null,
        ]);

        return response()->json([
            'payslips' => $payslips,
        ]);
    }

    public function salaryStructures()
    {
        $structures = SalaryStructure::withCount('employees')
            ->orderBy('name')
            ->get()
            ->map(fn (SalaryStructure $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'basic_salary' => (float) $s->basic_salary,
                'housing_allowance' => (float) $s->housing_allowance,
                'transport_allowance' => (float) $s->transport_allowance,
                'meal_allowance' => (float) $s->meal_allowance,
                'other_allowances' => (float) $s->other_allowances,
                'gross_salary' => number_format((float) $s->gross_salary, 2),
                'estimated_net' => number_format((float) $s->estimated_net, 2),
                'ssnit_employee' => (float) $s->ssnit_employee,
                'ssnit_employer' => (float) $s->ssnit_employer,
                'income_tax_rate' => (float) $s->income_tax_rate,
                'status' => $s->status,
                'employees_count' => $s->employees_count,
            ]);

        return response()->json([
            'structures' => $structures,
        ]);
    }

    public function storeSalaryStructure(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:salary_structures,name',
            'basic_salary' => 'required|numeric|min:0',
            'housing_allowance' => 'nullable|numeric|min:0',
            'transport_allowance' => 'nullable|numeric|min:0',
            'meal_allowance' => 'nullable|numeric|min:0',
            'other_allowances' => 'nullable|numeric|min:0',
            'ssnit_employee' => 'nullable|numeric|min:0|max:100',
            'ssnit_employer' => 'nullable|numeric|min:0|max:100',
            'income_tax_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:Active,Inactive',
        ]);

        $structure = SalaryStructure::create([
            ...$request->all(),
            'status' => $request->status ?? 'Active',
        ]);

        return response()->json([
            'structure' => $structure,
            'message' => 'Salary structure created.',
        ], 201);
    }

    public function updateSalaryStructure(Request $request, int $id)
    {
        $structure = SalaryStructure::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:salary_structures,name,' . $id,
            'basic_salary' => 'required|numeric|min:0',
            'housing_allowance' => 'nullable|numeric|min:0',
            'transport_allowance' => 'nullable|numeric|min:0',
            'meal_allowance' => 'nullable|numeric|min:0',
            'other_allowances' => 'nullable|numeric|min:0',
            'ssnit_employee' => 'nullable|numeric|min:0|max:100',
            'ssnit_employer' => 'nullable|numeric|min:0|max:100',
            'income_tax_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:Active,Inactive',
        ]);

        $structure->update($request->all());

        return response()->json([
            'structure' => $structure->fresh(),
            'message' => 'Salary structure updated.',
        ]);
    }

    public function destroySalaryStructure(int $id)
    {
        $structure = SalaryStructure::withCount('employees')->findOrFail($id);

        if ($structure->employees_count > 0) {
            return response()->json([
                'message' => 'Cannot delete structure assigned to ' . $structure->employees_count . ' employees.',
            ], 422);
        }

        $structure->delete();

        return response()->json([
            'message' => 'Salary structure deleted.',
        ]);
    }

    private function authEmployeeId(): ?int
    {
        $authId = auth()->id();
        if (! $authId) {
            return null;
        }

        return Employee::query()->whereKey($authId)->exists() ? (int) $authId : null;
    }
}

