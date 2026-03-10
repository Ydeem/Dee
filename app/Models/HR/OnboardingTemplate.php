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
        'designation_id',
        'status',
    ];

    public function tasks()
    {
        return $this->hasMany(OnboardingTask::class, 'onboarding_template_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }
}

