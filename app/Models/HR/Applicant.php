<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use HasFactory;

    protected $table = 'applicants';

    protected $fillable = [
        'job_opening_id', 'first_name', 'last_name',
        'email', 'phone', 'resume', 'cover_letter',
        'source', 'experience_years',
        'current_company', 'current_position',
        'expected_salary', 'stage', 'status',
        'rating', 'notes', 'rejected_reason',
        'interviewed_at', 'hired_at',
        'converted_employee_id',
    ];

    protected $casts = [
        'interviewed_at' => 'datetime',
        'hired_at' => 'datetime',
        'expected_salary' => 'decimal:2',
    ];

    protected $appends = [
        'full_name', 'initials',
        'stage_label', 'status_color',
        'resume_url',
    ];

    public static array $stages = [
        1 => 'Applied',
        2 => 'Screening',
        3 => 'Interview',
        4 => 'Offer',
        5 => 'Hired',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(
            substr($this->first_name ?? '', 0, 1)
            . substr($this->last_name ?? '', 0, 1)
        );
    }

    public function getStageLabelAttribute(): string
    {
        return self::$stages[$this->stage] ?? 'Applied';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'New' => 'default',
            'Reviewing' => 'info',
            'Shortlisted' => 'primary',
            'Interview Scheduled' => 'warning',
            'Interviewed' => 'purple',
            'Offer Sent' => 'teal',
            'Hired' => 'success',
            'Rejected' => 'error',
            'Withdrawn' => 'grey',
            default => 'default',
        };
    }

    public function getResumeUrlAttribute(): ?string
    {
        return $this->resume
            ? asset('storage/' . $this->resume)
            : null;
    }

    public function jobOpening()
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function convertedEmployee()
    {
        return $this->belongsTo(Employee::class, 'converted_employee_id');
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }
}
