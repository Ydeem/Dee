<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryStructure extends Model
{
    use HasFactory;

    protected $table = 'salary_structures';

    protected $fillable = [
        'name',
        'basic_salary',
        'housing_allowance',
        'transport_allowance',
        'meal_allowance',
        'other_allowances',
        'ssnit_employee',
        'ssnit_employer',
        'income_tax_rate',
        'status',
        // legacy columns retained for compatibility
        'employee_id',
        'allowances',
        'effective_date',
        'currency',
        'pay_frequency',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'housing_allowance' => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'meal_allowance' => 'decimal:2',
        'other_allowances' => 'decimal:2',
        'ssnit_employee' => 'decimal:2',
        'ssnit_employer' => 'decimal:2',
        'income_tax_rate' => 'decimal:2',
        // legacy casts
        'allowances' => 'array',
        'effective_date' => 'date',
    ];

    protected $appends = [
        'gross_salary',
        'estimated_net',
    ];

    public function getGrossSalaryAttribute(): float
    {
        return (float) $this->basic_salary
            + (float) $this->housing_allowance
            + (float) $this->transport_allowance
            + (float) $this->meal_allowance
            + (float) $this->other_allowances;
    }

    public function getEstimatedNetAttribute(): float
    {
        $gross = $this->gross_salary;
        $ssnit = $gross * ((float) $this->ssnit_employee / 100);
        $taxableIncome = $gross - $ssnit;
        $tax = self::calculateGhanaTax($taxableIncome);

        return round($gross - $ssnit - $tax, 2);
    }

    public static function calculateGhanaTax(float $monthly): float
    {
        $annual = $monthly * 12;
        $tax = 0.0;
        $brackets = [
            [0, 4380, 0.00],
            [4380, 1320, 0.05],
            [5700, 1560, 0.10],
            [7260, 36000, 0.175],
            [43260, 156000, 0.25],
            [199260, PHP_INT_MAX, 0.30],
        ];

        foreach ($brackets as [$from, $range, $rate]) {
            if ($annual <= $from) {
                break;
            }

            $taxable = min($annual - $from, $range);
            $tax += $taxable * $rate;
        }

        return round($tax / 12, 2);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'salary_structure_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
