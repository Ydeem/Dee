<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role;

class HrRole extends Role
{
    protected $table = 'roles';

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'color',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            HrPermission::class,
            'role_has_permissions',
            'role_id',
            'permission_id'
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'model_has_roles',
            'role_id',
            'model_id'
        )
            ->wherePivot('model_type', User::class)
            ->withPivot('model_type', 'assigned_by', 'assigned_at', 'created_at', 'updated_at');
    }

    public function getColorAttribute($value): string
    {
        if (!empty($value)) {
            return (string) $value;
        }

        return match ((string) $this->attributes['name']) {
            'HR Admin' => 'primary',
            'HR Manager' => 'cyan',
            'Payroll Officer' => 'success',
            'Recruiter' => 'purple',
            'Supervisor' => 'warning',
            'Employee' => 'secondary',
            default => 'primary',
        };
    }
}
