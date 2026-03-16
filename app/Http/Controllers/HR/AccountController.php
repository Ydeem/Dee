<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Mail\HR\HrMail;
use App\Models\EmployeeActivityLog;
use App\Models\HR\Employee;
use App\Models\HR\HrRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $employees = Employee::with([
            'department:id,name',
            'designation:id,name',
        ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $usersByEmail = User::with('hrRoles:id,name,color')
            ->get()
            ->keyBy(fn (User $user) => mb_strtolower(trim((string) $user->email)));

        $rows = $employees->map(function (Employee $employee) use ($usersByEmail) {
            $user = $this->resolveLinkedUserFromCollection($employee, $usersByEmail);
            $role = $user?->hrRoles?->sortBy('name')->first();

            return [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_id,
                'employee_name' => $employee->full_name,
                'avatar_url' => $employee->avatar_url,
                'designation' => $employee->designation?->name ?? 'Staff',
                'department_id' => $employee->department?->id,
                'department' => $employee->department?->name ?? '-',
                'work_email' => $employee->work_email,
                'personal_email' => $employee->personal_email,
                'default_email' => $this->employeePrimaryEmail($employee),
                'has_account' => (bool) $user,
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_email' => $user?->email,
                'role_id' => $role?->id,
                'role' => $role?->name,
                'role_color' => $role?->color ?? 'primary',
                'created_at' => $user?->created_at?->format('M d, Y'),
                'created_at_raw' => $user?->created_at?->toDateTimeString(),
            ];
        })->values();

        $stats = $this->buildStats($rows);
        $filtered = $this->applyFilters($rows, $request);

        $roles = HrRole::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (HrRole $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'color' => $role->color,
            ])
            ->values();

        $departments = $employees
            ->map(fn (Employee $employee) => $employee->department)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($department) => [
                'id' => $department->id,
                'name' => $department->name,
            ]);

        return response()->json([
            'employees' => $filtered,
            'roles' => $roles,
            'departments' => $departments,
            'stats' => $stats,
        ]);
    }

    public function checkStatus(Request $request, int $id)
    {
        $this->authorizeAdmin();

        $employee = Employee::findOrFail($id);
        $user = $this->resolveLinkedUser($employee);
        $role = $user?->hrRoles()->orderBy('name')->first();

        return response()->json([
            'has_account' => (bool) $user,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'role' => $role?->name,
            'role_id' => $role?->id,
            'role_color' => $role?->color ?? 'primary',
            'created_at' => $user?->created_at?->format('M d, Y'),
        ]);
    }

    public function createAccount(Request $request, int $id)
    {
        $this->authorizeAdmin();

        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:255|unique:users,email',
            'username' => 'nullable|string|max:100',
            'password' => 'required|string|min:8|max:255',
            'role_id' => 'required|exists:roles,id',
            'send_email' => 'sometimes|boolean',
        ]);

        $existingLinked = $this->resolveLinkedUser($employee);
        if ($existingLinked) {
            return response()->json([
                'message' => 'This employee already has a portal account.',
            ], 422);
        }

        $email = mb_strtolower(trim((string) $validated['email']));
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json([
                'message' => 'A portal account already exists with this email.',
            ], 422);
        }

        $plainPassword = (string) $validated['password'];
        $role = HrRole::findOrFail((int) $validated['role_id']);

        $user = DB::transaction(function () use ($validated, $email, $request, $role) {
            $createdUser = User::create([
                'name' => (string) $validated['name'],
                'email' => $email,
                'password' => Hash::make((string) $validated['password']),
                'email_verified_at' => now(),
                'force_password_change' => true,
            ]);

            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $role->id,
                    'model_type' => User::class,
                    'model_id' => $createdUser->id,
                ],
                [
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return $createdUser;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $mailNotice = null;
        if ($request->boolean('send_email', true)) {
            try {
                $this->queueWelcomeEmail(
                    employeeName: $employee->full_name,
                    recipientEmail: $user->email,
                    roleName: $role->name,
                    password: $plainPassword,
                    adminName: (string) $request->user()->name
                );
            } catch (\Throwable $exception) {
                report($exception);
                $mailNotice = 'Account was created, but welcome email could not be queued.';
            }
        }

        $this->logActivity(
            $employee->id,
            (string) $request->user()->name,
            'Portal Account Created',
            'Portal account created with role ' . $role->name . ' (' . $user->email . ').'
        );

        return response()->json([
            'message' => 'Account created for ' . $employee->full_name . ' successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'color' => $role->color,
            ],
            'mail_notice' => $mailNotice,
        ], 201);
    }

    public function resetPassword(Request $request, int $id)
    {
        $this->authorizeAdmin();

        $request->validate([
            'send_email' => 'sometimes|boolean',
        ]);

        $employee = Employee::findOrFail($id);
        $user = $this->resolveLinkedUser($employee);

        if (! $user) {
            return response()->json([
                'message' => 'No portal account found for this employee.',
            ], 404);
        }

        $newPassword = $this->generateTemporaryPassword($employee);

        $user->forceFill([
            'password' => Hash::make($newPassword),
            'force_password_change' => true,
        ])->save();

        $role = $user->hrRoles()->orderBy('name')->first();

        $mailNotice = null;
        if ($request->boolean('send_email', true)) {
            try {
                $this->queueWelcomeEmail(
                    employeeName: $employee->full_name,
                    recipientEmail: $user->email,
                    roleName: $role?->name ?? 'Employee',
                    password: $newPassword,
                    adminName: (string) $request->user()->name,
                    isReset: true
                );
            } catch (\Throwable $exception) {
                report($exception);
                $mailNotice = 'Password was reset, but email notification could not be queued.';
            }
        }

        $this->logActivity(
            $employee->id,
            (string) $request->user()->name,
            'Portal Password Reset',
            'Portal password reset for ' . $user->email . '.'
        );

        return response()->json([
            'message' => 'Temporary password reset for ' . $employee->full_name . '.',
            'new_password' => $newPassword,
            'mail_notice' => $mailNotice,
        ]);
    }

    public function revokeAccess(Request $request, int $id)
    {
        $this->authorizeAdmin();

        $employee = Employee::findOrFail($id);
        $user = $this->resolveLinkedUser($employee);

        if (! $user) {
            return response()->json([
                'message' => 'No portal account found for this employee.',
            ], 404);
        }

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot revoke your own portal access.',
            ], 422);
        }

        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->delete();

        $user->forceFill([
            'password' => Hash::make(Str::random(48)),
            'remember_token' => Str::random(60),
            'force_password_change' => false,
        ])->save();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->logActivity(
            $employee->id,
            (string) $request->user()->name,
            'Portal Access Revoked',
            'Portal access revoked for ' . $user->email . '.'
        );

        return response()->json([
            'message' => 'Portal access revoked for ' . $employee->full_name . '.',
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_if(! $this->isAdmin(), 403, 'HR Admin only.');
    }

    private function employeePrimaryEmail(Employee $employee): ?string
    {
        $email = $employee->work_email ?: $employee->personal_email;
        return $email ? mb_strtolower(trim((string) $email)) : null;
    }

    private function employeeEmails(Employee $employee): Collection
    {
        return collect([$employee->work_email, $employee->personal_email])
            ->filter(fn ($email) => filled($email))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique()
            ->values();
    }

    private function resolveLinkedUser(Employee $employee): ?User
    {
        $emails = $this->employeeEmails($employee);
        if ($emails->isEmpty()) {
            return null;
        }

        return User::with('hrRoles:id,name,color')
            ->where(function ($query) use ($emails) {
                foreach ($emails as $email) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->first();
    }

    private function resolveLinkedUserFromCollection(Employee $employee, Collection $usersByEmail): ?User
    {
        foreach ($this->employeeEmails($employee) as $email) {
            $candidate = $usersByEmail->get($email);
            if ($candidate) {
                return $candidate;
            }
        }

        return null;
    }

    private function generateTemporaryPassword(Employee $employee): string
    {
        $base = trim((string) ($employee->last_name ?: $employee->first_name ?: 'Welcome'));
        $base = preg_replace('/[^A-Za-z0-9]/', '', $base) ?: 'Welcome';

        return ucfirst(Str::lower($base)) . '@' . now()->format('Y');
    }

    private function queueWelcomeEmail(
        string $employeeName,
        string $recipientEmail,
        string $roleName,
        string $password,
        string $adminName,
        bool $isReset = false
    ): void {
        $appUrl = rtrim((string) config('app.url'), '/');
        $company = (string) config('app.name');
        $subject = $isReset
            ? 'HR Portal Password Reset - Temporary Login Details'
            : 'Welcome to ' . $company . ' HR Portal - Your Login Details';

        $body = "Hello {$employeeName},\n\n"
            . ($isReset
                ? "Your HR portal password has been reset by {$adminName}.\n\n"
                : "Your HR portal account has been created by {$adminName}.\n\n")
            . "---------------------------\n"
            . "YOUR LOGIN DETAILS\n"
            . "---------------------------\n"
            . "Portal URL: {$appUrl}/login\n"
            . "Email:      {$recipientEmail}\n"
            . "Password:   {$password}\n"
            . "Your Role:  {$roleName}\n"
            . "---------------------------\n\n"
            . "IMPORTANT: Please change your password immediately after your first login.\n\n"
            . "To change password:\n"
            . "1. Log in at {$appUrl}/login\n"
            . "2. Click your profile picture (top right corner)\n"
            . "3. Go to Settings\n"
            . "4. Click Change Password\n\n"
            . "If you have any issues accessing your account please contact HR.\n\n"
            . "Welcome to the team!\n\n"
            . "{$company} HR Team";

        Mail::to($recipientEmail)->queue(new HrMail(
            emailSubject: $subject,
            emailBody: $body,
            senderName: $adminName,
            recipientName: $employeeName,
            companyName: $company,
        ));
    }

    private function logActivity(int $employeeId, string $actorName, string $action, string $description): void
    {
        EmployeeActivityLog::create([
            'employee_id' => $employeeId,
            'actor_name' => $actorName,
            'action' => $action,
            'description' => $description,
        ]);
    }

    private function applyFilters(Collection $rows, Request $request): Collection
    {
        $search = mb_strtolower(trim((string) $request->query('q', '')));
        $access = (string) $request->query('access', 'all');
        $roleId = $request->query('role_id');
        $departmentId = $request->query('department_id');

        return $rows
            ->filter(function (array $row) use ($search) {
                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower((string) $row['employee_name']), $search)
                    || str_contains(mb_strtolower((string) ($row['user_email'] ?? '')), $search)
                    || str_contains(mb_strtolower((string) ($row['default_email'] ?? '')), $search);
            })
            ->filter(function (array $row) use ($access) {
                if ($access === 'has_access') {
                    return $row['has_account'] === true;
                }

                if ($access === 'no_access') {
                    return $row['has_account'] === false;
                }

                return true;
            })
            ->filter(function (array $row) use ($roleId) {
                if (! filled($roleId)) {
                    return true;
                }

                return (int) $row['role_id'] === (int) $roleId;
            })
            ->filter(function (array $row) use ($departmentId) {
                if (! filled($departmentId)) {
                    return true;
                }

                return (int) $row['department_id'] === (int) $departmentId;
            })
            ->values();
    }

    private function buildStats(Collection $rows): array
    {
        $totalEmployees = $rows->count();
        $activeAccounts = $rows->where('has_account', true)->count();
        $noAccess = $rows->where('has_account', false)->count();

        $roleBreakdown = $rows
            ->where('has_account', true)
            ->groupBy('role')
            ->map(function (Collection $group, $roleName) {
                return [
                    'role' => (string) $roleName,
                    'count' => $group->count(),
                    'color' => (string) ($group->first()['role_color'] ?? 'primary'),
                ];
            })
            ->filter(fn (array $role) => $role['role'] !== '')
            ->values();

        return [
            'total_employees' => $totalEmployees,
            'active_accounts' => $activeAccounts,
            'no_access' => $noAccess,
            'role_breakdown' => $roleBreakdown,
        ];
    }
}
