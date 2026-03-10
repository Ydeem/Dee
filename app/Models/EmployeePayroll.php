<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePayroll extends Model
{
    use HasFactory;

    protected $table = 'employee_payrolls';

    protected $fillable = ['employee_id', 'pay_month', 'gross', 'deductions', 'net', 'status', 'payslip_path'];

    protected function casts(): array
    {
        return [
            'pay_month' => 'date',
            'gross' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net' => 'decimal:2',
        ];
    }
}
