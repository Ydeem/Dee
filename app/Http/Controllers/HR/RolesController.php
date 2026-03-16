<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\HR\HrPermission;
use App\Models\HR\HrRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RolesController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();

        $roles = HrRole::with('permissions')
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (HrRole $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'color' => $role->color,
                'is_system' => $this->isSystemRole($role),
                'users_count' => $role->users_count,
                'permissions' => $role->permissions
                    ->groupBy(fn (HrPermission $permission) => $permission->module)
                    ->map(fn ($permissions, $module) => [
                        'module' => $module,
                        'items' => $permissions->map(fn (HrPermission $permission) => [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'label' => $permission->label,
                        ])->values(),
                    ])
                    ->values(),
                'permission_count' => $role->permissions->count(),
            ]);

        $allPermissions = HrPermission::query()
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (HrPermission $permission) => $permission->module)
            ->map(fn ($permissions, $module) => [
                'module' => $module,
                'label' => ucfirst(str_replace('_', ' ', (string) $module)),
                'items' => $permissions->map(fn (HrPermission $permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'label' => $permission->label,
                ])->values(),
            ])
            ->values();

        return response()->json([
            'roles' => $roles,
            'all_permissions' => $allPermissions,
            'stats' => [
                'total_roles' => $roles->count(),
                'system_roles' => $roles->where('is_system', true)->count(),
                'custom_roles' => $roles->where('is_system', false)->count(),
                'total_users' => User::count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = HrRole::create([
            'name' => $request->name,
            'description' => $request->description,
            'color' => $request->color ?? 'primary',
            'is_system' => false,
            'guard_name' => 'web',
        ]);

        if ($request->filled('permission_ids')) {
            $role->permissions()->sync($request->permission_ids);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'Role "' . $role->name . '" created successfully.',
            'role' => $role->load('permissions'),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeAdmin();

        $role = HrRole::findOrFail($id);

        if (
            $this->isSystemRole($role)
            && $request->filled('name')
            && $request->name !== $role->name
        ) {
            return response()->json([
                'message' => 'System role names cannot be changed.',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:100|unique:roles,name,' . $id,
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        $role->update($request->only([
            'name',
            'description',
            'color',
        ]));

        return response()->json([
            'message' => 'Role updated.',
            'role' => $role,
        ]);
    }

    public function syncPermissions(Request $request, int $id)
    {
        $this->authorizeAdmin();

        $role = HrRole::findOrFail($id);

        $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->sync($request->permission_ids);
        $role->load('permissions');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'Permissions updated for ' . $role->name,
            'permission_count' => $role->permissions->count(),
            'permissions' => $role->permissions,
        ]);
    }

    public function destroy(int $id)
    {
        $this->authorizeAdmin();

        $role = HrRole::withCount('users')->findOrFail($id);

        if ($this->isSystemRole($role)) {
            return response()->json([
                'message' => 'System roles cannot be deleted.',
            ], 403);
        }

        if ($role->users_count > 0) {
            return response()->json([
                'message' => 'Cannot delete role with assigned users. Remove users first.',
            ], 403);
        }

        $role->permissions()->detach();
        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => '"' . $role->name . '" role deleted.',
        ]);
    }

    public function show(int $id)
    {
        $this->authorizeAdmin();

        $role = HrRole::with([
            'permissions',
            'users:id,name,email',
        ])->findOrFail($id);

        $permissions = $role->permissions
            ->groupBy(fn (HrPermission $permission) => $permission->module)
            ->map(fn ($items, $module) => [
                'module' => $module,
                'label' => ucfirst(str_replace('_', ' ', (string) $module)),
                'items' => $items->map(fn (HrPermission $permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'label' => $permission->label,
                ])->values(),
            ])
            ->values();

        $users = $role->users->map(function (User $user) {
            $employee = Employee::with([
                'designation:id,name',
                'department:id,name',
            ])
                ->where(function ($query) use ($user) {
                    $query->where('personal_email', $user->email)
                        ->orWhere('work_email', $user->email);
                })
                ->first();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'initials' => strtoupper(
                    collect(explode(' ', (string) $user->name))
                        ->filter()
                        ->map(fn ($word) => substr($word, 0, 1))
                        ->take(2)
                        ->implode('')
                ),
                'avatar_url' => $employee?->avatar_url,
                'designation' => $employee?->designation?->name ?? 'Staff',
                'department' => $employee?->department?->name ?? '-',
                'employee_id' => $employee?->id,
            ];
        })->values();

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'color' => $role->color,
                'is_system' => $this->isSystemRole($role),
                'permission_count' => $role->permissions->count(),
                'users_count' => $role->users->count(),
                'permissions' => $permissions,
                'users' => $users,
            ],
        ]);
    }

    public function userAssignments()
    {
        $this->authorizeAdmin();

        $users = User::with([
            'hrRoles:id,name,color',
        ])
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $employee = Employee::with([
                    'designation:id,name',
                    'department:id,name',
                ])
                    ->where(function ($query) use ($user) {
                        $query->where('personal_email', $user->email)
                            ->orWhere('work_email', $user->email);
                    })
                    ->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => strtoupper(
                        collect(explode(' ', (string) $user->name))
                            ->filter()
                            ->map(fn ($word) => substr($word, 0, 1))
                            ->take(2)
                            ->implode('')
                    ),
                    'avatar_url' => $employee?->avatar_url,
                    'designation' => $employee?->designation?->name ?? 'Staff',
                    'department' => $employee?->department?->name ?? '-',
                    'roles' => $user->hrRoles->map(fn (HrRole $role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'color' => $role->color,
                    ])->values(),
                    'has_role' => $user->hrRoles->isNotEmpty(),
                    'is_admin' => $user->hrRoles->where('name', 'HR Admin')->isNotEmpty(),
                ];
            });

        $allRoles = HrRole::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return response()->json([
            'users' => $users,
            'roles' => $allRoles,
            'total' => $users->count(),
            'with_roles' => $users->filter(fn ($user) => $user['has_role'])->count(),
            'without_roles' => $users->filter(fn ($user) => !$user['has_role'])->count(),
        ]);
    }

    public function assignRole(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        abort_if(
            !$request->user()->isHrAdmin(),
            403,
            'Only HR Admin can assign roles.'
        );

        DB::table('model_has_roles')->updateOrInsert(
            [
                'role_id' => $request->role_id,
                'model_type' => User::class,
                'model_id' => $request->user_id,
            ],
            [
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $targetUser = User::findOrFail($request->user_id);
        $role = HrRole::findOrFail($request->role_id);

        return response()->json([
            'message' => $targetUser->name . ' is now ' . $role->name . '.',
        ]);
    }

    public function removeRole(Request $request, int $userId)
    {
        $this->authorizeAdmin();

        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        abort_if(
            !$request->user()->isHrAdmin(),
            403,
            'Only HR Admin can remove roles.'
        );

        if ($userId === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot remove your own role.',
            ], 403);
        }

        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $userId)
            ->where('role_id', $request->role_id)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $targetUser = User::findOrFail($userId);
        $role = HrRole::findOrFail($request->role_id);

        return response()->json([
            'message' => $role->name . ' removed from ' . $targetUser->name . '.',
        ]);
    }

    private function isSystemRole(HrRole $role): bool
    {
        return $role->is_system || in_array($role->name, [
            'HR Admin',
            'HR Manager',
            'Payroll Officer',
            'Recruiter',
            'Supervisor',
            'Employee',
            'super-admin',
        ], true);
    }

    private function authorizeAdmin(): void
    {
        abort_if(! $this->isAdmin(), 403, 'Only HR Admin can manage roles and permissions.');
    }
}
