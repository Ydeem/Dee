<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use HasFactory;

    protected $table = 'applicants';

    protected $appends = ['full_name'];

    protected $fillable = [
        'job_opening_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'location',
        'experience_years',
        'education_level',
        'current_employer',
        'current_role',
        'expected_salary',
        'notice_period',
        'cover_letter',
        'resume_path',
        'source',
        'status',
        'rating',
        'stage',
        'notes',
        'reviewed_by',
        'interview_date',
        'offer_date',
        'rejection_reason',
    ];

    protected $casts = [
        'interview_date' => 'datetime',
        'offer_date' => 'date',
        'rating' => 'integer',
        'expected_salary' => 'decimal:2',
    ];

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function jobOpening()
    {
        return $this->belongsTo(JobOpening::class, 'job_opening_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class, 'applicant_id');
    }
}
