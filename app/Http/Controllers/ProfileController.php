<?php

namespace App\Http\Controllers;

use App\Models\HR\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $employee = $this->resolveEmployeeForUser($user?->email);

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
                'created_at' => $user->created_at?->format('M d, Y'),
            ],
            'employee' => $employee ? [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'full_name' => $employee->full_name,
                'designation' => $employee->designation?->name ?? 'Staff',
                'department' => $employee->department?->name,
                'avatar_url' => $employee->avatar_url,
                'initials' => $employee->initials,
                'phone' => $employee->phone,
                'work_email' => $employee->work_email,
                'personal_email' => $employee->personal_email,
                'join_date' => $employee->join_date?->format('M d, Y'),
                'employment_type' => $employee->employment_type,
                'employment_status' => $employee->employment_status,
                'bio' => $employee->bio,
                'socials' => $employee->socials,
            ] : null,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $oldEmail = $user->email;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $employee = Employee::with(['department:id,name', 'designation:id,name'])
            ->where('personal_email', $oldEmail)
            ->orWhere('work_email', $oldEmail)
            ->first();

        if ($employee) {
            $nameParts = explode(' ', trim((string) $validated['name']), 2);

            $employee->update([
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? '',
            ]);
        }

        return response()->json([
            'message' => 'Profile updated.',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
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
                'errors' => [
                    'current_password' => ['Current password is incorrect.'],
                ],
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $employee = $this->resolveEmployeeForUser($request->user()?->email);

        if (! $employee) {
            return response()->json([
                'message' => 'No employee record found.',
            ], 404);
        }

        if ($employee->avatar && Storage::disk('public')->exists($employee->avatar)) {
            Storage::disk('public')->delete($employee->avatar);
        }

        $filename = Str::uuid() . '.' . $request->file('avatar')->getClientOriginalExtension();
        $path = $request->file('avatar')->storeAs('hr/avatars', $filename, 'public');
        $employee->update(['avatar' => $path]);

        return response()->json([
            'message' => 'Photo updated.',
            'avatar_url' => asset('storage/' . $path),
        ]);
    }

    public function updateSocial(Request $request)
    {
        $validated = $request->validate([
            'linkedin' => ['nullable', 'url', 'max:255'],
            'twitter' => ['nullable', 'url', 'max:255'],
            'github' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        $employee = $this->resolveEmployeeForUser($request->user()?->email);

        if (! $employee) {
            return response()->json([
                'message' => 'No employee found.',
            ], 404);
        }

        $socials = [
            'linkedin' => $validated['linkedin'] ?? '',
            'twitter' => $validated['twitter'] ?? '',
            'github' => $validated['github'] ?? '',
            'instagram' => $validated['instagram'] ?? '',
            'facebook' => $validated['facebook'] ?? '',
            'website' => $validated['website'] ?? '',
        ];

        $employee->update(['socials' => $socials]);

        return response()->json([
            'message' => 'Social links saved.',
            'socials' => $socials,
        ]);
    }

    public function getSocial(Request $request)
    {
        $employee = $this->resolveEmployeeForUser($request->user()?->email);

        return response()->json([
            'socials' => $employee
                ? $employee->socials
                : [
                    'linkedin' => '',
                    'twitter' => '',
                    'github' => '',
                    'instagram' => '',
                    'facebook' => '',
                    'website' => '',
                ],
            'employee_id' => $employee?->id,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'You have been logged out.');
    }

    private function resolveEmployeeForUser(?string $email): ?Employee
    {
        if (! $email) {
            return null;
        }

        return Employee::with(['department', 'designation'])
            ->where('personal_email', $email)
            ->orWhere('work_email', $email)
            ->first();
    }
}
