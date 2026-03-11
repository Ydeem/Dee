<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingTemplate extends Model
{
    use HasFactory;

    protected $table = 'onboarding_templates';

    protected $fillable = [
        'name',
        'description',
        'department_id',
        'days_to_complete',
        'status',
    ];

    public function tasks()
    {
        return $this->hasMany(OnboardingTask::class, 'template_id')
            ->orderBy('sort_order');
    }

    public function onboardings()
    {
        return $this->hasMany(EmployeeOnboarding::class, 'template_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
