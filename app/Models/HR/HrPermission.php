<?php

namespace App\Models\HR;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class HrPermission extends Permission
{
    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'guard_name',
        'module',
        'label',
    ];

    public function getModuleAttribute($value): string
    {
        if (!empty($value)) {
            return (string) $value;
        }

        return $this->inferModule((string) $this->attributes['name']);
    }

    public function getLabelAttribute($value): string
    {
        if (!empty($value)) {
            return (string) $value;
        }

        return Str::title((string) ($this->attributes['name'] ?? ''));
    }

    private function inferModule(string $permissionName): string
    {
        return match (true) {
            str_contains($permissionName, 'dashboard') => 'dashboard',
            str_contains($permissionName, 'employees') => 'employees',
            str_contains($permissionName, 'departments') => 'departments',
            str_contains($permissionName, 'designations') => 'designations',
            str_contains($permissionName, 'attendance') => 'attendance',
            str_contains($permissionName, 'leave') => 'leave_management',
            str_contains($permissionName, 'shifts') => 'shifts',
            str_contains($permissionName, 'job openings'),
            str_contains($permissionName, 'applicants'),
            str_contains($permissionName, 'onboarding'),
            str_contains($permissionName, 'employee') => 'recruitment',
            str_contains($permissionName, 'payroll'),
            str_contains($permissionName, 'payslips'),
            str_contains($permissionName, 'salary structures') => 'payroll',
            str_contains($permissionName, 'expenses') => 'expenses',
            str_contains($permissionName, 'reports') => 'reports',
            str_contains($permissionName, 'settings'),
            str_contains($permissionName, 'roles'),
            str_contains($permissionName, 'permissions') => 'settings',
            default => 'general',
        };
    }
}
