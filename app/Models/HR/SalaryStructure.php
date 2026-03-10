<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryStructure extends Model
{
    use HasFactory;

    protected $table = 'salary_structures';

    protected $fillable = [
        'employee_id',
        'basic_salary',
        'allowances',
        'effective_date',
        'currency',
        'pay_frequency',
    ];

    protected $casts = [
        'allowances' => 'array',
        'effective_date' => 'date',
        'basic_salary' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}

