<?php

namespace App\Http\Middleware;

use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\UserSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $employee = null;
        $settings = null;
        $roleNames = [];
        $permissions = [];
        $isAdmin = false;

        if ($user) {
            $employee = Employee::with([
                'designation:id,name',
                'department:id,name',
            ])
                ->where(function ($query) use ($user): void {
                    $query->where('work_email', $user->email)
                        ->orWhere('personal_email', $user->email);
                })
                ->first();

            $userRoles = $user->hrRoles()
                ->with('permissions:id,name')
                ->orderBy('name')
                ->get();

            $roleNames = $userRoles
                ->pluck('name')
                ->filter()
                ->values()
                ->all();

            $permissions = $userRoles
                ->flatMap(fn ($role) => $role->permissions)
                ->pluck('name')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $isAdmin = collect($roleNames)
                ->contains(fn ($name) => in_array((string) $name, ['HR Admin', 'super-admin'], true));

            $settings = UserSetting::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'language' => 'en',
                    'timezone' => 'UTC',
                    'date_format' => 'DD/MM/YYYY',
                    'currency' => 'GHS',
                    'email_notifications' => true,
                    'sms_notifications' => false,
                    'desktop_notifications' => false,
                    'show_email_to_colleagues' => false,
                    'show_phone_to_colleagues' => false,
                    'two_factor_enabled' => false,
                ]
            );
        }

        $initials = '';
        if ($user) {
            $initials = strtoupper(
                collect(explode(' ', trim((string) $user->name)))
                    ->filter()
                    ->map(fn ($word) => substr($word, 0, 1))
                    ->take(2)
                    ->implode('')
            );
        }

        $pendingLeave = 0;
        if ($user) {
            $pendingLeave = LeaveRequest::where('status', 'Pending')->count();
        }

        $unreadNotifications = 0;
        if ($user) {
            try {
                $unreadNotifications = $user->unreadNotifications()->count();
            } catch (\Exception $exception) {
                $unreadNotifications = 0;
            }
        }

        $primaryRole = $roleNames[0] ?? null;
        $designation = $employee?->designation?->name ?: $primaryRole;
        $department = $employee?->department?->name ?? null;
        $employeeCode = $employee?->employee_id;
        $status = $employee?->employment_status ?? 'Active';

        $joined = null;
        $joinDate = $employee?->join_date ?? ($employee?->hire_date ?? null);
        if ($joinDate) {
            try {
                $joined = Carbon::parse($joinDate)->format('M d, Y');
            } catch (Throwable) {
                $joined = null;
            }
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => $initials ?: 'U',
                    'avatar' => $employee?->avatar_url,
                    'avatar_url' => $employee?->avatar_url,
                    'roles' => $roleNames,
                    'permissions' => $permissions,
                    'is_admin' => $isAdmin,
                    'designation' => $designation,
                    'department' => $department,
                    'employee_id' => $employee?->id,
                    'employee_code' => $employeeCode,
                    'phone' => $employee?->phone,
                    'status' => $status,
                    'joined' => $joined,
                    'bio' => $employee?->bio,
                    'has_socials' => $employee
                        ? collect($employee->socials ?? [])->contains(fn ($value) => (bool) $value)
                        : false,
                    'settings' => $settings ? [
                        'language' => $settings->language,
                        'timezone' => $settings->timezone,
                        'date_format' => $settings->date_format,
                        'currency' => $settings->currency,
                        'email_notifications' => (bool) $settings->email_notifications,
                        'sms_notifications' => (bool) ($settings->sms_notifications ?? false),
                        'desktop_notifications' => (bool) ($settings->desktop_notifications ?? false),
                        'two_factor_enabled' => (bool) ($settings->two_factor_enabled ?? false),
                        'show_email' => (bool) ($settings->show_email_to_colleagues ?? false),
                        'show_phone' => (bool) ($settings->show_phone_to_colleagues ?? false),
                        'show_email_to_colleagues' => (bool) ($settings->show_email_to_colleagues ?? false),
                        'show_phone_to_colleagues' => (bool) ($settings->show_phone_to_colleagues ?? false),
                    ] : null,
                ] : null,
                'role' => $roleNames[0] ?? '',
                'permissions' => $permissions,
                'pending_leave' => $pendingLeave,
                'unread_notifications' => $unreadNotifications,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }
}
