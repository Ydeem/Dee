<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Payslip;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function index(Request $request)
    {
        $payslips = Payslip::with(['employee.department', 'payrollRun'])
            ->when($request->payroll_run_id, fn ($q) => $q->where('payroll_run_id', $request->payroll_run_id))
            ->when($request->employee_id, fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->payment_status, fn ($q) => $q->where('payment_status', $request->payment_status))
            ->when($request->search, fn ($q) =>
                $q->whereHas('employee', fn ($sub) =>
                    $sub->where('first_name', 'like', '%' . $request->search . '%')
                        ->orWhere('last_name', 'like', '%' . $request->search . '%')
                        ->orWhere('employee_id', 'like', '%' . $request->search . '%')
                )
            )
            ->orderBy('created_at', 'desc')
            ->paginate((int) ($request->per_page ?? 15));

        return response()->json(['payslips' => $payslips]);
    }

    public function show(int $id)
    {
        $payslip = Payslip::with([
            'employee.department',
            'employee.designation',
            'payrollRun',
        ])->findOrFail($id);

        return response()->json(['payslip' => $payslip]);
    }

    public function update(Request $request, int $id)
    {
        $payslip = Payslip::findOrFail($id);

        $validated = $request->validate([
            'basic_salary' => 'nullable|numeric|min:0',
            'allowances' => 'nullable|array',
            'other_deductions' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|in:Bank Transfer,Cash,Cheque,Mobile Money',
        ]);

        if ($request->has('basic_salary') || $request->has('allowances') || $request->has('other_deductions')) {
            $basic = (float) ($validated['basic_salary'] ?? $payslip->basic_salary);
            $allowances = $validated['allowances'] ?? ($payslip->allowances ?? []);
            $otherDeductions = (float) ($validated['other_deductions'] ?? $payslip->other_deductions ?? 0);
            $allowanceTotal = collect($allowances)->sum('amount');
            $gross = $basic + $allowanceTotal;
            $tax = $this->calculatePAYE($gross);
            $ssnit = round($gross * 0.055, 2);
            $net = round($gross - $tax - $ssnit - $otherDeductions, 2);

            $payslip->update([
                'basic_salary' => $basic,
                'allowances' => $allowances,
                'gross_salary' => round($gross, 2),
                'tax_amount' => $tax,
                'ssnit_employee' => $ssnit,
                'ssnit_employer' => round($gross * 0.13, 2),
                'other_deductions' => $otherDeductions,
                'net_salary' => $net,
                'notes' => $validated['notes'] ?? $payslip->notes,
                'payment_method' => $validated['payment_method'] ?? $payslip->payment_method,
            ]);
        } else {
            $payslip->update([
                'notes' => $validated['notes'] ?? $payslip->notes,
                'other_deductions' => $validated['other_deductions'] ?? $payslip->other_deductions,
                'payment_method' => $validated['payment_method'] ?? $payslip->payment_method,
            ]);
        }

        return response()->json(['payslip' => $payslip->fresh(['employee.department', 'payrollRun'])]);
    }

    private function calculatePAYE(float $monthlyGross): float
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
}

