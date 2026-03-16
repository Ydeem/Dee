<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class LinkingController extends Controller
{
    public function linkAllAccounts(): JsonResponse
    {
        if (! app()->runningInConsole()) {
            $currentUser = request()->user();
            abort_if(! $currentUser || ! $currentUser->isHrAdmin(), 403, 'Forbidden');
        }

        $users = User::query()->get(['id', 'name', 'email']);
        $linked = 0;
        $failed = [];
        $details = [];

        foreach ($users as $user) {
            $existing = Employee::query()
                ->where('work_email', $user->email)
                ->orWhere('personal_email', $user->email)
                ->first();

            if ($existing) {
                $linked++;
                $details[] = [
                    'user' => $user->name,
                    'employee_id' => $existing->id,
                    'status' => 'already_linked',
                ];

                if (app()->runningInConsole()) {
                    echo 'Already linked: ' . $user->name . ' -> Employee ID: ' . $existing->id . PHP_EOL;
                }

                continue;
            }

            $nameParts = preg_split('/\s+/', trim((string) $user->name), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            $employeeQuery = Employee::query()
                ->where('first_name', 'like', '%' . $firstName . '%');

            if ($lastName !== '') {
                $employeeQuery->where('last_name', 'like', '%' . $lastName . '%');
            }

            $employee = $employeeQuery->first();

            if (! $employee) {
                $failed[] = $user->name;
                if (app()->runningInConsole()) {
                    echo 'Could not link: ' . $user->name . PHP_EOL;
                }
                continue;
            }

            $employee->update([
                'work_email' => $user->email,
            ]);

            $linked++;
            $details[] = [
                'user' => $user->name,
                'employee_id' => $employee->id,
                'status' => 'linked_by_name',
            ];

            if (app()->runningInConsole()) {
                echo 'Linked: ' . $user->name . ' -> Employee ID: ' . $employee->id . PHP_EOL;
            }
        }

        if (app()->runningInConsole()) {
            echo PHP_EOL . 'Linked: ' . $linked . PHP_EOL;
            echo 'Could not link: ' . count($failed) . PHP_EOL;
        }

        return response()->json([
            'linked' => $linked,
            'failed' => $failed,
            'details' => $details,
        ]);
    }
}
