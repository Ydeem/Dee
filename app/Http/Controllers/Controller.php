<?php

namespace App\Http\Controllers;

use App\Models\User;

abstract class Controller
{
    protected function can(string $permission): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        $permissionName = mb_strtolower(trim($permission));

        return $user->hrRoles()
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->contains($permissionName);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($permission)) {
                return true;
            }
        }

        return false;
    }

    protected function isAdmin(): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        return $user->hrRoles()
            ->whereIn('name', ['HR Admin', 'super-admin'])
            ->exists();
    }
}
