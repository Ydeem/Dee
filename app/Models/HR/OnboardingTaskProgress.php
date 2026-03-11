<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingTaskProgress extends Model
{
    use HasFactory;

    protected $table = 'onboarding_task_progress';

    protected $fillable = [
        'onboarding_id',
        'employee_onboarding_id',
        'task_id',
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
        return $this->belongsTo(EmployeeOnboarding::class, 'onboarding_id');
    }

    public function task()
    {
        return $this->belongsTo(OnboardingTask::class, 'task_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(Employee::class, 'completed_by');
    }
}
