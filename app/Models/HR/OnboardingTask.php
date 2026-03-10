<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingTask extends Model
{
    use HasFactory;

    protected $table = 'onboarding_tasks';

    protected $fillable = [
        'onboarding_template_id',
        'title',
        'description',
        'category',
        'due_days',
        'assigned_to_role',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }
}

