<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveBalance extends Model
{
    use HasFactory;

    protected $table = 'employee_leave_balances';

    protected $fillable = ['employee_id', 'leave_type', 'used_days', 'total_days'];
}
