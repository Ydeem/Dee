<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeOnboarding extends Model
{
    use HasFactory;

    protected $table = 'employee_onboardings';

    protected $fillable = [
        'employee_id',
        'onboarding_template_id',
        'start_date',
        'expected_end_date',
        'completed_date',
        'status',
        'notes',
        'assigned_buddy_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expected_end_date' => 'date',
        'completed_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function template()
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }

    public function buddy()
    {
        return $this->belongsTo(Employee::class, 'assigned_buddy_id');
    }

    public function taskProgress()
    {
        return $this->hasMany(OnboardingTaskProgress::class, 'employee_onboarding_id');
    }
}

