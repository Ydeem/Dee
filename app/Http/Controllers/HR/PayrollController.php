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
        $runs = PayrollRun::withCount('payslips')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->year, fn ($q) => $q->where('period_year', $request->year))
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->paginate((int) ($request->per_page ?? 12));

        $summary = [
            'total_paid_this_year' => PayrollRun::where('status', 'Paid')
                ->where('period_year', now()->year)
                ->sum('total_net'),
            'pending_approval' => PayrollRun::where('status', 'Pending Approval')->count(),
            'current_month_status' => PayrollRun::where('period_month', now()->month)
                ->where('period_year', now()->year)
                ->value('status') ?? 'Not Run',
            'total_employees_on_payroll' => SalaryStructure::distinct('employee_id')->count('employee_id'),
        ];

        return response()->json([
            'payroll_runs' => $runs,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_month' => 'required|integer|min:1|max:12',
            'period_year' => 'required|integer|min:2020',
            'pay_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $exists = PayrollRun::where('period_month', $validated['period_month'])
            ->where('period_year', $validated['period_year'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'A payroll run already exists for this period.'], 422);
        }

        $monthName = Carbon::create($validated['period_year'], $validated['period_month'])->format('F Y');

        $employeeId = auth()->id();
        if ($employeeId && !Employee::whereKey($employeeId)->exists()) {
            $employeeId = null;
        }

        $run = PayrollRun::create([
            'title' => $monthName . ' Payroll',
            'period_month' => $validated['period_month'],
            'period_year' => $validated['period_year'],
            'pay_date' => $validated['pay_date'],
            'status' => 'Draft',
            'processed_by' => $employeeId,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(['payroll_run' => $run], 201);
    }

    public function show(int $id)
    {
        $run = PayrollRun::with([
            'payslips.employee.department',
            'processedBy',
            'approvedBy',
        ])->withCount('payslips')->findOrFail($id);

        return response()->json(['payroll_run' => $run]);
    }

    public function process(int $id)
    {
        $run = PayrollRun::findOrFail($id);

        if (!in_array($run->status, ['Draft'], true)) {
            return response()->json(['message' => 'Only Draft payroll runs can be processed.'], 422);
        }

        $run->update(['status' => 'Processing']);

        $salaries = SalaryStructure::with('employee')
            ->whereHas('employee', fn ($q) => $q->where('employment_status', 'Active'))
            ->get();

        $totalGross = 0.0;
        $totalDeductions = 0.0;
        $totalNet = 0.0;

        foreach ($salaries as $salary) {
            $allowanceTotal = collect($salary->allowances ?? [])->sum('amount');
            $grossSalary = (float) $salary->basic_salary + (float) $allowanceTotal;
            $taxAmount = $this->calculatePAYE($grossSalary);
            $ssnitEmployee = round($grossSalary * 0.055, 2);
            $ssnitEmployer = round($grossSalary * 0.13, 2);
            $otherDeductions = 0.0;
            $totalDeductionsAmount = $taxAmount + $ssnitEmployee + $otherDeductions;
            $netSalary = round($grossSalary - $totalDeductionsAmount, 2);

            Payslip::updateOrCreate(
                [
                    'payroll_run_id' => $run->id,
                    'employee_id' => $salary->employee_id,
                ],
                [
                    'basic_salary' => $salary->basic_salary,
                    'allowances' => $salary->allowances ?? [],
                    'deductions' => [],
                    'gross_salary' => round($grossSalary, 2),
                    'tax_amount' => $taxAmount,
                    'ssnit_employee' => $ssnitEmployee,
                    'ssnit_employer' => $ssnitEmployer,
                    'other_deductions' => $otherDeductions,
                    'net_salary' => $netSalary,
                    'payment_method' => 'Bank Transfer',
                    'payment_status' => 'Pending',
                    'bank_name' => $salary->employee?->bank_name,
                    'account_number' => $salary->employee?->account_number,
                ]
            );

            $totalGross += $grossSalary;
            $totalDeductions += $totalDeductionsAmount;
            $totalNet += $netSalary;
        }

        $run->update([
            'status' => 'Pending Approval',
            'total_gross' => round($totalGross, 2),
            'total_deductions' => round($totalDeductions, 2),
            'total_net' => round($totalNet, 2),
            'employee_count' => $salaries->count(),
        ]);

        return response()->json([
            'message' => 'Payroll processed for ' . $salaries->count() . ' employees.',
            'payroll_run' => $run->fresh(),
        ]);
    }

    public function calculatePAYE(float $monthlyGross): float
    {
        $annualGross = $monthlyGross * 12;
        $tax = 0.0;
        $bands = [
            ['limit' => 4380, 'rate' => 0.00],
            ['limit' => 1320, 'rate' => 0.05],
            ['limit' => 1560, 'rate' => 0.10],
            ['limit' => 38000, 'rate' => 0.175],
            ['limit' => 192000, 'rate' => 0.25],
            ['limit' => PHP_INT_MAX, 'rate' => 0.30],
        ];

        $remaining = $annualGross;
        foreach ($bands as $band) {
            if ($remaining <= 0) {
                break;
            }
            $taxable = min($remaining, $band['limit']);
            $tax += $taxable * $band['rate'];
            $remaining -= $taxable;
        }

        return round($tax / 12, 2);
    }

    public function approve(int $id)
    {
        $run = PayrollRun::findOrFail($id);
        if ($run->status !== 'Pending Approval') {
            return response()->json(['message' => 'Only runs pending approval can be approved.'], 422);
        }

        $employeeId = auth()->id();
        if ($employeeId && !Employee::whereKey($employeeId)->exists()) {
            $employeeId = null;
        }

        $run->update([
            'status' => 'Approved',
            'approved_by' => $employeeId,
            'approved_at' => now(),
        ]);

        return response()->json(['message' => 'Payroll approved.']);
    }

    public function markPaid(int $id)
    {
        $run = PayrollRun::findOrFail($id);
        if ($run->status !== 'Approved') {
            return response()->json(['message' => 'Only approved payrolls can be marked as paid.'], 422);
        }

        $run->update(['status' => 'Paid']);
        $run->payslips()->update([
            'payment_status' => 'Paid',
            'payment_date' => now()->toDateString(),
        ]);

        return response()->json(['message' => 'Payroll marked as paid.']);
    }

    public function cancel(int $id)
    {
        $run = PayrollRun::findOrFail($id);
        if ($run->status === 'Paid') {
            return response()->json(['message' => 'Paid payrolls cannot be cancelled.'], 422);
        }

        $run->update(['status' => 'Cancelled']);
        return response()->json(['message' => 'Payroll cancelled.']);
    }

    public function destroy(int $id)
    {
        $run = PayrollRun::findOrFail($id);
        if ($run->status === 'Paid') {
            return response()->json(['message' => 'Paid payrolls cannot be deleted.'], 422);
        }

        $run->delete();
        return response()->json(['message' => 'Payroll run deleted.']);
    }
}

