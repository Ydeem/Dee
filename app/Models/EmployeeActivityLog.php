<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeActivityLog extends Model
{
    use HasFactory;

    protected $table = 'employee_activity_logs';

    protected $fillable = ['employee_id', 'actor_name', 'action', 'description'];
}
