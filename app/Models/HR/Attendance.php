<?php

namespace App\Models\HR;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'hours_worked',
        'status',
        'note',
        'marked_by',
    ];

    protected $casts = [
        'date' => 'date',
        'hours_worked' => 'decimal:2',
    ];

    protected $appends = ['status_color'];

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Present' => 'success',
            'Absent' => 'error',
            'Late' => 'warning',
            'Half Day' => 'info',
            'On Leave' => 'primary',
            'Holiday' => 'secondary',
            default => 'default',
        };
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function (Attendance $attendance) {
            if ($attendance->check_in && $attendance->check_out) {
                $in = Carbon::parse($attendance->check_in);
                $out = Carbon::parse($attendance->check_out);

                if ($out->gt($in)) {
                    $attendance->hours_worked = round($in->diffInMinutes($out) / 60, 2);
                }
            }
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function markedBy()
    {
        return $this->belongsTo(Employee::class, 'marked_by');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('date', now());
    }

    public function scopeForMonth($query, $month, $year)
    {
        return $query->whereMonth('date', $month)
            ->whereYear('date', $year);
    }
}
