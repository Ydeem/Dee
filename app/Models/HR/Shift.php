<?php

namespace App\Models\HR;

use Carbon\Carbon;
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
        'color',
        'working_days',
        'break_duration',
        'description',
        'status',
    ];

    protected $casts = [
        'working_days' => 'array',
    ];

    protected $appends = [
        'schedule_label',
        'duration_hours',
    ];

    public function getScheduleLabelAttribute(): string
    {
        if (! $this->start_time || ! $this->end_time) {
            return 'N/A';
        }

        return Carbon::parse($this->start_time)->format('H:i')
            . ' - '
            . Carbon::parse($this->end_time)->format('H:i');
    }

    public function getDurationHoursAttribute(): float
    {
        if (! $this->start_time || ! $this->end_time) {
            return 0.0;
        }

        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        if ($end->lt($start)) {
            $end->addDay();
        }

        $totalMinutes = $start->diffInMinutes($end);
        $breakDuration = (int) ($this->break_duration ?? 60);

        return round(max(0, $totalMinutes - $breakDuration) / 60, 1);
    }

    public function employees()
    {
        return $this->hasMany(\App\Models\HR\Employee::class, 'shift_id');
    }

    public function schedules()
    {
        return $this->hasMany(ShiftSchedule::class, 'shift_id');
    }

    public function activeSchedules()
    {
        return $this->hasMany(ShiftSchedule::class, 'shift_id')
            ->where('status', 'Active');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
