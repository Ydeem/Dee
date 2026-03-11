<?php

namespace App\Models\HR;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use HasFactory;

    protected $table = 'payroll_runs';

    protected $fillable = [
        'month',
        'year',
        'pay_date',
        'status',
        'total_gross',
        'total_deductions',
        'total_net',
        'employee_count',
        'approved_by',
        'approved_at',
        'notes',
        // legacy columns
        'title',
        'period_month',
        'period_year',
        'processed_by',
    ];

    protected $casts = [
        'pay_date' => 'date',
        'approved_at' => 'datetime',
        'total_gross' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
    ];

    protected $appends = [
        'month_label',
        'status_color',
    ];

    public function getMonthLabelAttribute(): string
    {
        $year = (int) ($this->year ?: $this->period_year);
        $month = (int) ($this->month ?: $this->period_month);

        if ($year > 0 && $month > 0) {
            return Carbon::create($year, $month, 1)->format('F Y');
        }

        return (string) ($this->title ?? '');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Draft' => 'default',
            'Pending Approval' => 'warning',
            'Approved' => 'primary',
            'Paid' => 'success',
            'Cancelled' => 'error',
            default => 'default',
        };
    }

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

    public function scopeForYear($query, int|string $year)
    {
        return $query->where('year', $year);
    }
}
