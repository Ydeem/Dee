<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $permissions = Permission::all()
            ->groupBy(fn ($permission) => $this->permissionGroup($permission->name));

        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'role' => $role->load('permissions'),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $role = Role::findOrFail($id);

        if (in_array($role->name, ['super-admin'], true)) {
            return response()->json([
                'message' => 'System roles cannot be modified.',
            ], 422);
        }

        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions ?? []);

        return response()->json([
            'role' => $role->load('permissions'),
        ]);
    }

    public function destroy(int $id)
    {
        $role = Role::withCount('users')->findOrFail($id);

        if (in_array($role->name, ['HR Admin', 'super-admin'], true)) {
            return response()->json([
                'message' => 'System roles cannot be deleted.',
            ], 422);
        }

        if ($role->users_count > 0) {
            return response()->json([
                'message' => 'Cannot delete role assigned to users.',
            ], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    public function assignRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|exists:roles,name',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($request->filled('role')) {
            $user->syncRoles([$request->role]);

            return response()->json([
                'message' => 'Role assigned to ' . $user->name,
            ]);
        }

        $user->syncRoles([]);

        return response()->json([
            'message' => 'Role removed from ' . $user->name,
        ]);
    }

    public function userRoles(Request $request)
    {
        $users = User::with(['roles.permissions'])
            ->when($request->search, fn ($query) =>
                $query->where(function ($inner) use ($request) {
                    $inner->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('email', 'like', '%' . $request->search . '%');
                })
            )
            ->paginate((int) ($request->per_page ?? 15));

        return response()->json(['users' => $users]);
    }

    private function permissionGroup(string $permission): string
    {
        return match (true) {
            str_contains($permission, 'dashboard') => 'dashboard',
            str_contains($permission, 'employees') => 'employees',
            str_contains($permission, 'departments') => 'departments',
            str_contains($permission, 'designations') => 'designations',
            str_contains($permission, 'attendance') => 'attendance',
            str_contains($permission, 'leave') => 'leave',
            str_contains($permission, 'shifts') => 'shifts',
            str_contains($permission, 'job openings'), str_contains($permission, 'applicants'), str_contains($permission, 'onboarding'), str_contains($permission, 'employee') => 'recruitment',
            str_contains($permission, 'payroll'), str_contains($permission, 'payslips'), str_contains($permission, 'salary structures') => 'payroll',
            str_contains($permission, 'expenses') => 'expenses',
            str_contains($permission, 'reports') => 'reports',
            str_contains($permission, 'settings'), str_contains($permission, 'roles'), str_contains($permission, 'permissions') => 'settings',
            default => 'other',
        };
    }
}
