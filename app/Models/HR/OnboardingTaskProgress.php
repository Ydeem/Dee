<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingTaskProgress extends Model
{
    use HasFactory;

    protected $table = 'onboarding_task_progress';

    protected $fillable = [
        'employee_onboarding_id',
        'onboarding_task_id',
        'status',
        'completed_at',
        'completed_by',
        'notes',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function onboarding()
    {
        return $this->belongsTo(EmployeeOnboarding::class, 'employee_onboarding_id');
    }

    public function task()
    {
        return $this->belongsTo(OnboardingTask::class, 'onboarding_task_id');
    }

    public function completedByEmployee()
    {
        return $this->belongsTo(Employee::class, 'completed_by');
    }
}

