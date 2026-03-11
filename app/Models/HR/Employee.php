<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'employee_id',
        'first_name',
        'last_name',
        'personal_email',
        'work_email',
        'phone',
        'date_of_birth',
        'gender',
        'national_id',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'avatar',
        'department_id',
        'designation_id',
        'shift_id',
        'employment_type',
        'employment_status',
        'join_date',
        'reporting_manager_id',
        'work_location',
        'basic_salary',
        'salary_structure_id',
        'bank_name',
        'account_number',
        'account_name',
        'tin',
        'ssnit',
        'bio',
        'skills',
        'pay_frequency',
        'allowances',
        'notes',
        'last_active_at',
    ];

    protected $casts = [
        'join_date' => 'date:Y-m-d',
        'date_of_birth' => 'date:Y-m-d',
        'skills' => 'array',
        'allowances' => 'array',
        'basic_salary' => 'decimal:2',
        'last_active_at' => 'datetime',
    ];

    protected $appends = ['full_name', 'avatar_url', 'initials'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $employee) {
            if (empty($employee->employee_id)) {
                $count = static::withTrashed()->count();
                $next = $count + 1;
                $employee->employee_id = 'EMP' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            }

            if (empty($employee->avatar) && ! empty($employee->profile_photo_path)) {
                $employee->avatar = $employee->profile_photo_path;
            }
        });

        static::saving(function (self $employee) {
            if (! empty($employee->avatar)) {
                $employee->profile_photo_path = $employee->avatar;
            }
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $path = $this->avatar ?: $this->profile_photo_path;

        if (! $path) {
            return null;
        }

        $normalized = str_replace('\\', '/', trim((string) $path));

        if (Str::startsWith($normalized, ['http://', 'https://', '//'])) {
            return $normalized;
        }

        if (Str::startsWith($normalized, '/storage/')) {
            return asset(ltrim($normalized, '/'));
        }

        if (Str::startsWith($normalized, 'storage/')) {
            return asset($normalized);
        }

        if (Str::startsWith($normalized, 'public/')) {
            $normalized = Str::after($normalized, 'public/');
        }

        return asset('storage/' . ltrim($normalized, '/'));
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(
            substr($this->first_name ?? '', 0, 1)
            . substr($this->last_name ?? '', 0, 1)
        );
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function directReports()
    {
        return $this->hasMany(Employee::class, 'reporting_manager_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function salaryStructure()
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function schedules()
    {
        return $this->hasMany(ShiftSchedule::class);
    }

    public function onboarding()
    {
        return $this->hasOne(EmployeeOnboarding::class);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function scopeActive($query)
    {
        return $query->where('employment_status', 'Active');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")
                ->orWhere('personal_email', 'like', "%{$search}%")
                ->orWhere('work_email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }
}
