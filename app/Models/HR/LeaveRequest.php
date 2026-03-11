<?php

namespace App\Models\HR;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $table = 'leave_requests';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'from_date',
        'to_date',
        'days_requested',
        'reason',
        'status',
        'approved_by',
        'rejected_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'approved_at' => 'datetime',
    ];

    protected $appends = [
        'status_color',
        'can_approve',
        'can_reject',
        'can_cancel',
    ];

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Pending' => 'warning',
            'Approved' => 'success',
            'Rejected' => 'error',
            'Cancelled' => 'default',
            default => 'default',
        };
    }

    public function getCanApproveAttribute(): bool
    {
        return $this->status === 'Pending';
    }

    public function getCanRejectAttribute(): bool
    {
        return in_array($this->status, ['Pending', 'Approved'], true);
    }

    public function getCanCancelAttribute(): bool
    {
        return in_array($this->status, ['Pending', 'Approved'], true)
            && Carbon::parse($this->from_date)->isFuture();
    }

    public static function calculateDays($from, $to): int
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);
        $days = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if (! $current->isWeekend()) {
                $days++;
            }

            $current->addDay();
        }

        return max(1, $days);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\HR\Employee::class, 'employee_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\Models\HR\Employee::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(\App\Models\HR\Employee::class, 'rejected_by');
    }
}
