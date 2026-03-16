<?php

namespace App\Http\Controllers;

use App\Models\HR\Employee;
use App\Models\UserSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Throwable;

class AccountSettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
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

        $employee = $this->resolveEmployeeByEmail($user->email);

        $roles = $user->hrRoles()
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        $joined = null;
        $joinDate = $employee?->join_date ?? ($employee?->hire_date ?? null);
        if ($joinDate) {
            try {
                $joined = Carbon::parse($joinDate)->format('M d, Y');
            } catch (Throwable) {
                $joined = null;
            }
        }

        $unreadCount = $user->unreadNotifications()->count();

        $notifications = $user->notifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'message' => $n->data['message'] ?? '',
                'type' => $n->data['type'] ?? 'info',
                'link' => $n->data['link'] ?? '#',
                'icon' => $n->data['icon'] ?? 'mdi-bell',
                'color' => $n->data['color'] ?? 'primary',
                'read' => ! is_null($n->read_at),
                'time' => $n->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'initials' => strtoupper(
                    collect(explode(' ', trim((string) $user->name)))
                        ->filter()
                        ->map(fn ($word) => substr($word, 0, 1))
                        ->take(2)
                        ->implode('')
                ),
                'avatar_url' => $employee?->avatar_url,
                'designation' => $employee?->designation?->name ?: ($roles[0] ?? null),
                'department' => $employee?->department?->name,
                'employee_id' => $employee?->id,
                'employee_code' => $employee?->employee_id,
                'status' => $employee?->employment_status ?? 'Active',
                'joined' => $joined,
                'phone' => $employee?->phone,
                'roles' => $roles,
            ],
            'settings' => [
                'language' => $settings->language,
                'timezone' => $settings->timezone,
                'date_format' => $settings->date_format,
                'currency' => $settings->currency,
                'email_notifications' => (bool) $settings->email_notifications,
                'sms_notifications' => (bool) $settings->sms_notifications,
                'desktop_notifications' => (bool) $settings->desktop_notifications,
                'two_factor_enabled' => (bool) $settings->two_factor_enabled,
                'show_email' => (bool) $settings->show_email_to_colleagues,
                'show_phone' => (bool) $settings->show_phone_to_colleagues,
            ],
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function updateAccount(Request $request)
    {
        $user = $request->user();
        $oldEmail = $user->email;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'language' => ['nullable', 'string', Rule::in(['en', 'fr', 'es'])],
            'timezone' => ['nullable', 'string', Rule::in(['UTC', 'Africa/Accra', 'Africa/Lagos', 'Europe/London', 'America/New_York'])],
            'currency' => ['nullable', 'string', Rule::in(['GHS', 'USD', 'EUR', 'GBP', 'NGN'])],
        ]);

        DB::transaction(function () use ($user, $validated, $oldEmail): void {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            $settings = UserSetting::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'language' => 'en',
                    'timezone' => 'UTC',
                    'date_format' => 'DD/MM/YYYY',
                    'currency' => 'GHS',
                    'email_notifications' => true,
                ]
            );

            $settings->fill([
                'language' => $validated['language'] ?? $settings->language ?? 'en',
                'timezone' => $validated['timezone'] ?? $settings->timezone ?? 'UTC',
                'currency' => $validated['currency'] ?? $settings->currency ?? 'GHS',
            ])->save();

            $employee = Employee::where(function ($query) use ($oldEmail): void {
                $query->where('personal_email', $oldEmail)
                    ->orWhere('work_email', $oldEmail);
            })->first();

            if ($employee) {
                $nameParts = preg_split('/\s+/', trim((string) $validated['name']), 2);
                $updates = [
                    'first_name' => $nameParts[0] ?? $employee->first_name,
                    'last_name' => $nameParts[1] ?? '',
                ];

                if ($employee->personal_email === $oldEmail) {
                    $updates['personal_email'] = $validated['email'];
                }

                if ($employee->work_email === $oldEmail) {
                    $updates['work_email'] = $validated['email'];
                }

                $employee->update($updates);
            }
        });

        $user->refresh();
        $settings = UserSetting::firstOrCreate(['user_id' => $user->id]);

        return response()->json([
            'message' => 'Settings saved successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'settings' => [
                'language' => $settings->language,
                'timezone' => $settings->timezone,
                'currency' => $settings->currency,
            ],
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    public function updateNotifications(Request $request)
    {
        $request->validate([
            'email_notifications' => ['boolean'],
            'sms_notifications' => ['boolean'],
            'desktop_notifications' => ['boolean'],
        ]);

        $settings = UserSetting::firstOrCreate(['user_id' => $request->user()->id]);
        $settings->update($request->only([
            'email_notifications',
            'sms_notifications',
            'desktop_notifications',
        ]));

        return response()->json([
            'message' => 'Settings saved successfully',
            'settings' => [
                'email_notifications' => (bool) $settings->email_notifications,
                'sms_notifications' => (bool) $settings->sms_notifications,
                'desktop_notifications' => (bool) $settings->desktop_notifications,
            ],
        ]);
    }

    public function updatePrivacy(Request $request)
    {
        $request->validate([
            'two_factor_enabled' => ['nullable', 'boolean'],
            'show_email' => ['nullable', 'boolean'],
            'show_phone' => ['nullable', 'boolean'],
            'show_email_to_colleagues' => ['nullable', 'boolean'],
            'show_phone_to_colleagues' => ['nullable', 'boolean'],
        ]);

        $payload = [];

        if ($request->has('show_email')) {
            $payload['show_email_to_colleagues'] = (bool) $request->boolean('show_email');
        } elseif ($request->has('show_email_to_colleagues')) {
            $payload['show_email_to_colleagues'] = (bool) $request->boolean('show_email_to_colleagues');
        }

        if ($request->has('show_phone')) {
            $payload['show_phone_to_colleagues'] = (bool) $request->boolean('show_phone');
        } elseif ($request->has('show_phone_to_colleagues')) {
            $payload['show_phone_to_colleagues'] = (bool) $request->boolean('show_phone_to_colleagues');
        }

        if ($request->has('two_factor_enabled')) {
            $payload['two_factor_enabled'] = (bool) $request->boolean('two_factor_enabled');
        }

        $settings = UserSetting::firstOrCreate(['user_id' => $request->user()->id]);
        $settings->update($payload);

        return response()->json([
            'message' => 'Settings saved successfully',
            'settings' => [
                'two_factor_enabled' => (bool) $settings->two_factor_enabled,
                'show_email' => (bool) $settings->show_email_to_colleagues,
                'show_phone' => (bool) $settings->show_phone_to_colleagues,
            ],
        ]);
    }

    public function enableTwoFactor(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'method' => ['nullable', Rule::in(['email', 'sms'])],
        ]);

        UserSetting::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'two_factor_enabled' => $validated['enabled'],
                'two_factor_method' => $validated['method'] ?? 'email',
            ]
        );

        $status = $validated['enabled'] ? 'enabled' : 'disabled';

        return response()->json([
            'message' => "Two-factor authentication {$status}.",
        ]);
    }

    private function resolveEmployeeByEmail(?string $email): ?Employee
    {
        if (! $email) {
            return null;
        }

        return Employee::with(['department:id,name', 'designation:id,name'])
            ->where(function ($query) use ($email): void {
                $query->where('personal_email', $email)
                    ->orWhere('work_email', $email);
            })
            ->first();
    }
}
