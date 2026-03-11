<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    protected $table = 'leave_types';

    protected $fillable = [
        'name',
        'days_allowed',
        'description',
        'carry_forward',
        'requires_approval',
        'max_carry_forward_days',
        'applicable_gender',
        'color',
        'status',
    ];

    protected $casts = [
        'carry_forward' => 'boolean',
        'requires_approval' => 'boolean',
    ];

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'leave_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
