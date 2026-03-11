<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingTask extends Model
{
    use HasFactory;

    protected $table = 'onboarding_tasks';

    protected $fillable = [
        'template_id',
        'onboarding_template_id',
        'title',
        'description',
        'category',
        'due_days',
        'required',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'required' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(OnboardingTemplate::class, 'template_id');
    }
}
