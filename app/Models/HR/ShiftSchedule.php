<?php

namespace App\Models\HR;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model
{
    use HasFactory;

    protected $table = 'shift_schedules';

    protected $fillable = [
        'employee_id',
        'shift_id',
        'effective_from',
        'effective_to',
        'status',
        'assigned_by',
        'note',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    protected $appends = [
        'effective_to_label',
    ];

    public function getEffectiveToLabelAttribute(): string
    {
        return $this->effective_to
            ? Carbon::parse($this->effective_to)->format('M d, Y')
            : 'Ongoing';
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\HR\Employee::class, 'employee_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(\App\Models\HR\Employee::class, 'assigned_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeCurrent($query)
    {
        return $query->where('status', 'Active')
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', now()->toDateString());
            });
    }
}
