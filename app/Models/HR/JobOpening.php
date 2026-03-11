<?php

namespace App\Models\HR;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobOpening extends Model
{
    use HasFactory;

    protected $table = 'job_openings';

    protected $fillable = [
        'title', 'department_id', 'designation_id',
        'employment_type', 'vacancies',
        'min_salary', 'max_salary', 'location',
        'deadline', 'description', 'requirements',
        'responsibilities', 'benefits',
        'status', 'posted_by',
    ];

    protected $casts = [
        'deadline' => 'date',
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
    ];

    protected $appends = [
        'salary_range', 'status_color',
        'is_expired', 'days_until_deadline',
    ];

    public function getSalaryRangeAttribute(): string
    {
        if ($this->min_salary && $this->max_salary) {
            return 'GHS ' . number_format((float) $this->min_salary, 0)
                . ' - ' . number_format((float) $this->max_salary, 0);
        }

        if ($this->min_salary) {
            return 'From GHS ' . number_format((float) $this->min_salary, 0);
        }

        return 'Not specified';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Open' => 'success',
            'Draft' => 'warning',
            'Closed' => 'default',
            'On Hold' => 'error',
            default => 'default',
        };
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->deadline
            && Carbon::parse($this->deadline)->isPast();
    }

    public function getDaysUntilDeadlineAttribute(): ?int
    {
        if (! $this->deadline) {
            return null;
        }

        return (int) now()->diffInDays(Carbon::parse($this->deadline), false);
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function postedBy()
    {
        return $this->belongsTo(Employee::class, 'posted_by');
    }

    public function applicants()
    {
        return $this->hasMany(Applicant::class, 'job_opening_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'Open');
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%");
        });
    }
}
