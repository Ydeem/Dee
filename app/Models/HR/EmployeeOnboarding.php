<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EmployeeOnboarding extends Model
{
    use HasFactory;

    protected $table = 'employee_onboardings';

    protected $fillable = [
        'employee_id',
        'template_id',
        'onboarding_template_id',
        'buddy_id',
        'assigned_buddy_id',
        'start_date',
        'expected_end_date',
        'completed_at',
        'completed_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expected_end_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'progress_percentage',
        'status_color',
        'is_overdue',
    ];

    public function getProgressPercentageAttribute(): int
    {
        $total = $this->taskProgress()->count();
        if ($total === 0) {
            return 0;
        }

        $done = $this->taskProgress()->where('status', 'Completed')->count();

        return (int) round(($done / $total) * 100);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Not Started' => 'default',
            'In Progress' => 'primary',
            'Completed' => 'success',
            'Overdue' => 'error',
            default => 'default',
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->expected_end_date
            && Carbon::parse($this->expected_end_date)->isPast()
            && $this->status !== 'Completed';
    }

    public function recalculateStatus(): void
    {
        $pct = $this->progress_percentage;

        if ($pct === 100) {
            $this->update([
                'status' => 'Completed',
                'completed_at' => now(),
            ]);
            return;
        }

        if ($this->is_overdue) {
            $this->update(['status' => 'Overdue']);
            return;
        }

        if ($pct > 0) {
            $this->update(['status' => 'In Progress']);
        }
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function template()
    {
        return $this->belongsTo(OnboardingTemplate::class, 'template_id');
    }

    public function buddy()
    {
        return $this->belongsTo(Employee::class, 'buddy_id');
    }

    public function taskProgress()
    {
        return $this->hasMany(OnboardingTaskProgress::class, 'onboarding_id');
    }
}
