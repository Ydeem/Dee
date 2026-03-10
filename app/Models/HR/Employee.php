<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'employees';

    protected $appends = ['full_name', 'avatar_url'];

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->profile_photo_path ? asset('storage/' . $this->profile_photo_path) : null;
    }

    public function department()
    {
        return $this->belongsTo(\App\Models\HR\Department::class, 'department_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(\App\Models\HR\LeaveRequest::class, 'employee_id');
    }
}
