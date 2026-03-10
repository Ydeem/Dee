<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveHistory extends Model
{
    use HasFactory;

    protected $table = 'employee_leave_history';

    protected $fillable = ['employee_id', 'leave_type', 'from_date', 'to_date', 'days', 'status', 'approved_by'];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
        ];
    }
}
