<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'national_id',
        'phone',
        'personal_email',
        'work_email',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'department_id',
        'designation_id',
        'employment_type',
        'employment_status',
        'join_date',
        'reporting_manager_id',
        'work_location',
        'shift_id',
        'profile_photo_path',
        'basic_salary',
        'pay_frequency',
        'bank_name',
        'account_number',
        'account_name',
        'tin',
        'ssnit',
        'allowances',
        'skills',
        'bio',
        'notes',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'join_date' => 'date',
            'allowances' => 'array',
            'skills' => 'array',
            'last_active_at' => 'datetime',
            'basic_salary' => 'decimal:2',
        ];
    }

    protected $appends = ['full_name', 'avatar_url'];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->profile_photo_path ? asset('storage/' . $this->profile_photo_path) : null;
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function directReports()
    {
        return $this->hasMany(Employee::class, 'reporting_manager_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }
}
