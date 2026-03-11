<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    use HasFactory;

    protected $table = 'designations';

    protected $fillable = [
        'name',
        'department_id',
        'level',
        'description',
        'status',
        'min_salary',
        'max_salary',
    ];

    protected $casts = [
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
    ];

    protected $appends = ['employee_count', 'initials', 'level_color'];

    public function getEmployeeCountAttribute(): int
    {
        return $this->employees()->count();
    }

    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }

        return strtoupper(substr((string) $this->name, 0, 2));
    }

    public function getLevelColorAttribute(): string
    {
        return match ($this->level) {
            'Junior' => 'success',
            'Mid-level' => 'primary',
            'Senior' => 'info',
            'Lead' => 'warning',
            'Manager' => 'orange',
            'Director' => 'deep-purple',
            'C-Level' => 'error',
            default => 'default',
        };
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'designation_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }
}
