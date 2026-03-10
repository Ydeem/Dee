<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    use HasFactory;

    protected $table = 'payslips';

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'basic_salary',
        'allowances',
        'deductions',
        'gross_salary',
        'tax_amount',
        'ssnit_employee',
        'ssnit_employer',
        'other_deductions',
        'net_salary',
        'payment_method',
        'payment_status',
        'payment_date',
        'bank_name',
        'account_number',
        'notes',
    ];

    protected $casts = [
        'allowances' => 'array',
        'deductions' => 'array',
        'basic_salary' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'ssnit_employee' => 'decimal:2',
        'ssnit_employer' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}

