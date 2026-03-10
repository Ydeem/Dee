<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use HasFactory;

    protected $table = 'payroll_runs';

    protected $fillable = [
        'title',
        'period_month',
        'period_year',
        'pay_date',
        'status',
        'total_gross',
        'total_deductions',
        'total_net',
        'employee_count',
        'processed_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'pay_date' => 'date',
        'approved_at' => 'datetime',
        'total_gross' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
    ];

    public function payslips()
    {
        return $this->hasMany(Payslip::class, 'payroll_run_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(Employee::class, 'processed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}

