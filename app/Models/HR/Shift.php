<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $table = 'shifts';

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'break_duration',
        'working_days',
        'color',
        'status',
    ];

    protected $casts = [
        'working_days' => 'array',
    ];

    public function employees()
    {
        return $this->hasMany(\App\Models\HR\Employee::class, 'shift_id');
    }

    public function schedules()
    {
        return $this->hasMany(ShiftSchedule::class, 'shift_id');
    }
}
