<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobOpening extends Model
{
    use HasFactory;

    protected $table = 'job_openings';

    protected $fillable = [
        'title',
        'department_id',
        'designation_id',
        'employment_type',
        'location',
        'vacancies',
        'salary_from',
        'salary_to',
        'salary_currency',
        'description',
        'requirements',
        'benefits',
        'experience_years',
        'education_level',
        'status',
        'deadline',
        'posted_by',
    ];

    protected $casts = [
        'deadline' => 'date',
        'salary_from' => 'decimal:2',
        'salary_to' => 'decimal:2',
    ];

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
}

