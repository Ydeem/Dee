<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\EmployeeActivityLog;
use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeDocument;
use App\Models\HR\EmployeeOnboarding;
use App\Models\HR\Expense;
use App\Models\HR\HrRole;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\HR\Payslip;
use App\Models\HR\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class EmployeeController extends Controller
{
    public function options()
    {
        return response()->json([
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'designations' => Designation::orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::orderBy('name')->get(['id', 'name']),
            'managers' => Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_id']),
        ]);
    }

    public function index(Request $request)
    {
        $query = Employee::with(['department', 'designation'])
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('first_name', 'like', '%' . $request->search . '%')
                        ->orWhere('last_name', 'like', '%' . $request->search . '%')
                        ->orWhere('employee_id', 'like', '%' . $request->search . '%')
                        ->orWhere('personal_email', 'like', '%' . $request->search . '%');
                });
            })
            ->when($request->department, function ($q) use ($request) {
                $q->whereHas('department', fn ($departmentQuery) => $departmentQuery->where('name', $request->department));
            })
            ->when($request->designation, function ($q) use ($request) {
                $q->whereHas('designation', fn ($designationQuery) => $designationQuery->where('name', $request->designation));
            })
            ->when($request->type, function ($q) use ($request) {
                $q->where('employment_type', $request->type);
            })
            ->when($request->status, function ($q) use ($request) {
                $q->where('employment_status', $request->status);
            });

        $sortBy = in_array($request->sort_by, [
            'first_name',
            'employee_id',
            'join_date',
            'created_at',
        ], true) ? $request->sort_by : 'created_at';

        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';

        $employees = $query
            ->orderBy($sortBy, $sortDir)
            ->paginate((int) ($request->per_page ?? 10));

        $employees->through(function (Employee $emp) {
            return [
                'id' => $emp->id,
                'employee_id' => $emp->employee_id,
                'full_name' => $emp->full_name,
                'first_name' => $emp->first_name,
                'last_name' => $emp->last_name,
                'personal_email' => $emp->personal_email,
                'work_email' => $emp->work_email,
                'phone' => $emp->phone,
                'avatar_url' => $emp->avatar_url,
                'initials' => $emp->initials,
                'department' => $emp->department
                    ? [
                        'id' => $emp->department->id,
                        'name' => $emp->department->name,
                    ]
                    : null,
                'designation' => $emp->designation
                    ? [
                        'id' => $emp->designation->id,
                        'name' => $emp->designation->name,
                    ]
                    : null,
                'employment_type' => $emp->employment_type,
                'employment_status' => $emp->employment_status,
                'join_date' => optional($emp->join_date)
                    ? optional($emp->join_date)->format('M d, Y')
                    : null,
                'basic_salary' => $emp->basic_salary,
                'work_location' => $emp->work_location,
            ];
        });

        return response()->json([
            'employees' => $employees,
            'filters' => [
                'departments' => Department::orderBy('name')->pluck('name'),
                'designations' => Designation::orderBy('name')->pluck('name'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_if(! $this->can('create employees'), 403, 'Forbidden');

        $validated = $this->validateEmployee($request);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('hr/avatars', 'public');
        } elseif ($request->hasFile('profile_photo')) {
            $avatarPath = $request->file('profile_photo')->store('hr/avatars', 'public');
        }

        $employee = Employee::create([
            ...$this->payloadFromRequest($validated),
            'avatar' => $avatarPath,
        ]);

        $this->syncDocuments($request, $employee);
        $this->recordActivity($employee->id, optional($request->user())->name, 'Created', 'Employee record was created.');

        return response()->json([
            'employee' => $employee->load(['department', 'designation']),
            'message' => 'Employee created successfully.',
        ], 201);
    }

    public function show($id)
    {
        $employee = Employee::with([
            'department',
            'designation',
            'shift',
            'manager:id,first_name,last_name,avatar',
            'directReports:id,first_name,last_name,employee_id,avatar,reporting_manager_id',
            'onboarding.template',
        ])->findOrFail($id);

        $currentMonth = now()->month;
        $currentYear = now()->year;

        $daysPresent = Attendance::where('employee_id', $id)
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->whereIn('status', ['Present', 'Late'])
            ->count();

        $employee->setAttribute('has_user_account', $this->employeeHasUserAccount($employee));
        $employee->setAttribute('login_email', $this->resolveEmployeeLoginEmail($employee));

        return response()->json([
            'employee' => $employee,
            'days_present' => $daysPresent,
            'leave_remaining' => $this->getLeaveBalance($id),
        ]);
    }

    public function createUserAccount(Request $request, $id)
    {
        abort_if(
            !$request->user()?->isHrAdmin() && !$request->user()?->can('create employees'),
            403,
            'You are not allowed to create login accounts.'
        );

        $employee = Employee::findOrFail($id);

        $email = $this->resolveEmployeeLoginEmail($employee);
        if (! $email) {
            return response()->json([
                'message' => 'Employee has no email address.',
            ], 422);
        }

        if ($this->userExistsByEmail($email)) {
            return response()->json([
                'message' => 'User account already exists for this employee.',
            ], 422);
        }

        $user = User::create([
            'name' => trim($employee->full_name) ?: trim($employee->first_name . ' ' . $employee->last_name),
            'email' => $email,
            'password' => Hash::make('Password@123'),
            'email_verified_at' => now(),
        ]);

        $employeeRole = HrRole::where('name', 'Employee')->first();
        if ($employeeRole) {
            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $employeeRole->id,
                    'model_type' => User::class,
                    'model_id' => $user->id,
                ],
                [
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $emailWarning = null;
        try {
            Mail::raw(
                "Hello {$employee->full_name},\n\n"
                . "Your HR portal account has been created.\n\n"
                . "Login URL: " . rtrim((string) config('app.url'), '/') . "/login\n"
                . "Email: {$email}\n"
                . "Password: Password@123\n\n"
                . "Please change your password after first login.\n\n"
                . "HR Team",
                fn ($message) => $message
                    ->to($email)
                    ->subject('Your HR Portal Account')
            );
        } catch (\Throwable $exception) {
            $emailWarning = 'Account created, but welcome email could not be sent.';
        }

        return response()->json([
            'message' => $emailWarning
                ?: 'Account created for ' . $employee->full_name . '. Login email sent.',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        abort_if(! $this->can('edit employees'), 403, 'Forbidden');

        $employee = Employee::findOrFail($id);
        $validated = $this->validateEmployee($request, $id);

        if ($request->hasFile('avatar') || $request->hasFile('profile_photo')) {
            if ($employee->avatar) {
                Storage::disk('public')->delete($employee->avatar);
            }

            $file = $request->file('avatar') ?: $request->file('profile_photo');
            $employee->avatar = $file->store('hr/avatars', 'public');
        }

        $employee->update($this->payloadFromRequest($validated));
        $this->syncDocuments($request, $employee);
        $this->recordActivity($employee->id, optional($request->user())->name, 'Updated', 'Employee record was updated.');

        return response()->json([
            'employee' => $employee->load(['department', 'designation']),
            'message' => 'Employee updated successfully.',
        ]);
    }

    public function updateAvatar(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        $user = $request->user();
        abort_if(! $user, 401);

        $userEmail = mb_strtolower(trim((string) $user->email));
        $isOwnProfile = $userEmail !== ''
            && in_array($userEmail, [
                mb_strtolower(trim((string) $employee->work_email)),
                mb_strtolower(trim((string) $employee->personal_email)),
            ], true);
        $isAdmin = $user->isHrAdmin();

        abort_if(! $isOwnProfile && ! $isAdmin, 403, 'You can only update your own avatar.');

        $this->applyAvatarUpload($request, $employee);

        $this->recordActivity($employee->id, optional($request->user())->name, 'Avatar Updated', 'Employee profile photo was updated.');

        return response()->json([
            'avatar_url' => $employee->fresh()->avatar_url,
            'message' => 'Profile photo updated.',
        ]);
    }

    public function updateMyAvatar(Request $request)
    {
        $employee = $this->resolveEmployeeForAuthenticatedUser($request);

        if (! $employee) {
            return response()->json([
                'message' => 'No linked employee profile found for this account.',
            ], 404);
        }

        $this->applyAvatarUpload($request, $employee);
        $this->recordActivity($employee->id, optional($request->user())->name, 'Avatar Updated', 'Employee profile photo was updated.');

        return response()->json([
            'employee_id' => $employee->id,
            'avatar_url' => $employee->fresh()->avatar_url,
            'message' => 'Profile photo updated.',
        ]);
    }

    public function removeAvatar(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        $user = $request->user();
        abort_if(! $user, 401);

        $userEmail = mb_strtolower(trim((string) $user->email));
        $isOwnProfile = $userEmail !== ''
            && in_array($userEmail, [
                mb_strtolower(trim((string) $employee->work_email)),
                mb_strtolower(trim((string) $employee->personal_email)),
            ], true);
        $isAdmin = $user->isHrAdmin();

        abort_if(! $isOwnProfile && ! $isAdmin, 403, 'You can only update your own avatar.');

        if ($employee->avatar) {
            Storage::disk('public')->delete($employee->avatar);
        }

        $employee->avatar = null;

        if (Schema::hasColumn('employees', 'profile_photo_path')) {
            $employee->profile_photo_path = null;
        }

        $employee->save();

        $this->recordActivity($employee->id, optional($request->user())->name, 'Avatar Removed', 'Employee profile photo was removed.');

        return response()->json([
            'avatar_url' => null,
            'message' => 'Profile photo removed.',
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'status' => ['required', Rule::in(['Active', 'Inactive', 'On Leave', 'Probation'])],
        ]);

        $employee->update([
            'employment_status' => $request->status,
        ]);

        $this->recordActivity($employee->id, optional($request->user())->name, 'Status Changed', 'Status set to ' . $request->status . '.');

        return response()->json([
            'message' => 'Status updated to ' . $request->status,
        ]);
    }

    public function destroy($id)
    {
        abort_if(! $this->can('delete employees'), 403, 'Forbidden');

        $employee = Employee::findOrFail($id);
        $employee->delete();

        $this->recordActivity($employee->id, auth()->user()?->name, 'Deleted', 'Employee removed successfully.');

        return response()->json([
            'message' => 'Employee removed successfully.',
        ]);
    }

    public function attendance(Request $request, $id)
    {
        Employee::findOrFail($id);

        $month = (int) ($request->month ?? now()->month);
        $year = (int) ($request->year ?? now()->year);

        $records = Attendance::where('employee_id', $id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date', 'desc')
            ->get();

        $history = $records->map(fn ($record) => [
            'id' => $record->id,
            'date' => optional($record->date)->toDateString(),
            'check_in' => $record->check_in,
            'check_out' => $record->check_out,
            'hours' => $record->hours ?? $record->hours_worked,
            'status' => $record->status,
        ])->values();

        $summary = [
            'present' => $records->where('status', 'Present')->count(),
            'absent' => $records->where('status', 'Absent')->count(),
            'late' => $records->where('status', 'Late')->count(),
            'on_leave' => $records->where('status', 'On Leave')->count(),
        ];

        $calendar = $records->map(fn ($record) => [
            'date' => optional($record->date)->toDateString(),
            'status' => $record->status,
        ])->values();

        return response()->json([
            'attendance' => $records,
            'summary' => $summary,
            'history' => $history,
            'calendar' => $calendar,
        ]);
    }

    public function leave($id)
    {
        Employee::findOrFail($id);

        $requests = LeaveRequest::with('leaveType')
            ->where('employee_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $balance = $this->getLeaveBalance($id);

        return response()->json([
            'leave_requests' => $requests,
            'history' => $requests->map(fn ($request) => [
                'id' => $request->id,
                'leave_type' => $request->leaveType?->name ?? $request->leave_type ?? 'Leave',
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'days' => $request->days_requested,
                'status' => $request->status,
                'approved_by' => $request->approved_by ?? null,
            ])->values(),
            'balance' => $balance,
            'balances' => collect($balance)->map(fn ($item) => [
                'id' => $item['leave_type_id'],
                'leave_type' => $item['name'],
                'used_days' => $item['used'],
                'total_days' => $item['allowed'],
            ])->values(),
        ]);
    }

    public function payroll($id)
    {
        $employee = Employee::findOrFail($id);

        $payslips = Payslip::with('payrollRun')
            ->where('employee_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalPaid = $payslips->where('payment_status', 'Paid')->sum('net_salary');

        return response()->json([
            'payslips' => $payslips,
            'history' => $payslips->map(fn ($payslip) => [
                'id' => $payslip->id,
                'pay_month' => $payslip->payrollRun?->title ?? $payslip->created_at?->format('M Y'),
                'gross' => $payslip->gross_salary,
                'deductions' => collect($payslip->deductions ?? [])->sum('amount') + (float) $payslip->other_deductions,
                'net' => $payslip->net_salary,
                'status' => $payslip->payment_status,
            ])->values(),
            'total_paid' => $totalPaid,
            'salary' => [
                'basic_salary' => $employee->basic_salary,
                'allowances' => $employee->allowances ?? [],
                'deductions' => 0,
                'net_pay' => (float) ($employee->basic_salary ?? 0) + collect($employee->allowances ?? [])->sum('amount'),
            ],
        ]);
    }

    public function documents($id)
    {
        Employee::findOrFail($id);

        $documents = EmployeeDocument::where('employee_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($document) => [
                'id' => $document->id,
                'name' => $document->name,
                'type' => $document->type,
                'file_name' => $document->name,
                'category' => $document->type,
                'file_path' => $document->file_path,
                'file_size' => $document->file_size,
                'size' => $document->file_size,
                'mime_type' => $document->mime_type,
                'uploaded_by' => $document->uploaded_by,
                'file_url' => $document->file_url,
                'url' => $document->file_url,
                'created_at' => $document->created_at,
            ])->values();

        return response()->json(['documents' => $documents]);
    }

    public function uploadDocument(Request $request, $id)
    {
        Employee::findOrFail($id);

        $request->validate([
            'file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx',
            'name' => 'required|string',
            'type' => 'required|string',
        ]);

        $path = $request->file('file')->store('hr/documents/' . $id, 'public');

        $document = EmployeeDocument::create([
            'employee_id' => $id,
            'name' => $request->name,
            'type' => $request->type,
            'file_path' => $path,
            'file_size' => (int) round($request->file('file')->getSize() / 1024),
            'mime_type' => $request->file('file')->getMimeType(),
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json([
            'document' => $document,
        ], 201);
    }

    public function deleteDocument($id, $docId)
    {
        $document = EmployeeDocument::where('employee_id', $id)->findOrFail($docId);
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json([
            'message' => 'Document deleted.',
        ]);
    }

    public function activityLog($id)
    {
        Employee::findOrFail($id);

        $activity = EmployeeActivityLog::where('employee_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'logs' => $activity,
            'activity_log' => $activity,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');
        $headers = fgetcsv($handle);

        $imported = 0;
        $errors = [];
        $row = 2;

        while (($data = fgetcsv($handle)) !== false) {
            $record = array_combine($headers, $data);

            try {
                $department = Department::where('name', trim($record['department'] ?? ''))->first();
                $designation = Designation::where('name', trim($record['designation'] ?? ''))->first();

                Employee::firstOrCreate(
                    ['personal_email' => trim($record['personal_email'] ?? '')],
                    [
                        'first_name' => trim($record['first_name'] ?? ''),
                        'last_name' => trim($record['last_name'] ?? ''),
                        'phone' => trim($record['phone'] ?? ''),
                        'department_id' => $department?->id,
                        'designation_id' => $designation?->id,
                        'employment_type' => trim($record['employment_type'] ?? 'Full-time'),
                        'employment_status' => trim($record['employment_status'] ?? 'Active'),
                        'join_date' => ! empty($record['join_date'])
                            ? Carbon::parse($record['join_date'])->toDateString()
                            : null,
                    ]
                );

                $imported++;
            } catch (\Exception $exception) {
                $errors[] = 'Row ' . $row . ': ' . $exception->getMessage();
            }

            $row++;
        }

        fclose($handle);

        return response()->json([
            'message' => 'Imported ' . $imported . ' employees.',
            'imported' => $imported,
            'errors' => $errors,
        ]);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,export',
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        if ($request->action === 'activate') {
            Employee::whereIn('id', $request->employee_ids)->update(['employment_status' => 'Active']);
            return response()->json([
                'message' => count($request->employee_ids) . ' employees activated.',
            ]);
        }

        if ($request->action === 'deactivate') {
            Employee::whereIn('id', $request->employee_ids)->update(['employment_status' => 'Inactive']);
            return response()->json([
                'message' => count($request->employee_ids) . ' employees deactivated.',
            ]);
        }

        return response()->json([
            'message' => 'Action completed.',
        ]);
    }

    private function validateEmployee(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'personal_email' => ['required', 'email', Rule::unique('employees', 'personal_email')->ignore($id)],
            'work_email' => ['nullable', 'email', Rule::unique('employees', 'work_email')->ignore($id)],
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:Male,Female,Other',
            'national_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'employment_type' => 'required|in:Full-time,Part-time,Contract,Intern',
            'employment_status' => 'required|in:Active,Inactive,On Leave,Probation',
            'join_date' => 'nullable|date',
            'reporting_manager_id' => 'nullable|exists:employees,id',
            'work_location' => 'nullable|in:Office,Remote,Hybrid',
            'basic_salary' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'account_name' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:255',
            'ssnit' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'skills' => 'nullable',
            'avatar' => 'nullable|image|max:2048',
            'profile_photo' => 'nullable|image|max:2048',
            'pay_frequency' => 'nullable|string|max:50',
            'allowances' => 'nullable|array',
            'allowances.*.type' => 'nullable|string|max:100',
            'allowances.*.amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
    }

    private function payloadFromRequest(array $validated): array
    {
        $skills = $validated['skills'] ?? null;
        if (is_string($skills)) {
            $decoded = json_decode($skills, true);
            $skills = json_last_error() === JSON_ERROR_NONE ? $decoded : array_values(array_filter(array_map('trim', explode(',', $skills))));
        }

        return [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'personal_email' => $validated['personal_email'],
            'work_email' => $validated['work_email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'national_id' => $validated['national_id'] ?? null,
            'address' => $validated['address'] ?? null,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'designation_id' => $validated['designation_id'] ?? null,
            'shift_id' => $validated['shift_id'] ?? null,
            'employment_type' => $validated['employment_type'],
            'employment_status' => $validated['employment_status'],
            'join_date' => $validated['join_date'] ?? null,
            'reporting_manager_id' => $validated['reporting_manager_id'] ?? null,
            'work_location' => $validated['work_location'] ?? null,
            'basic_salary' => $validated['basic_salary'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'account_name' => $validated['account_name'] ?? null,
            'tin' => $validated['tin'] ?? null,
            'ssnit' => $validated['ssnit'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'skills' => is_array($skills) ? array_values($skills) : [],
            'pay_frequency' => $validated['pay_frequency'] ?? null,
            'allowances' => $validated['allowances'] ?? [],
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function syncDocuments(Request $request, Employee $employee): void
    {
        if ($request->hasFile('documents')) {
            $categories = $request->input('document_categories', []);

            foreach ($request->file('documents') as $index => $file) {
                if (! $file) {
                    continue;
                }

                $path = $file->store('hr/documents/' . $employee->id, 'public');

                EmployeeDocument::create([
                    'employee_id' => $employee->id,
                    'name' => $file->getClientOriginalName(),
                    'type' => $categories[$index] ?? 'Other',
                    'file_path' => $path,
                    'file_size' => (int) round($file->getSize() / 1024),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        if ($request->hasFile('file') && $request->filled('name') && $request->filled('type')) {
            $file = $request->file('file');
            $path = $file->store('hr/documents/' . $employee->id, 'public');

            EmployeeDocument::create([
                'employee_id' => $employee->id,
                'name' => $request->name,
                'type' => $request->type,
                'file_path' => $path,
                'file_size' => (int) round($file->getSize() / 1024),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => auth()->id(),
            ]);
        }
    }

    private function getLeaveBalance($employeeId): array
    {
        if (! Schema::hasTable('leave_types')) {
            return [];
        }

        $leaveTypes = LeaveType::where('status', 'Active')->get();
        $year = now()->year;

        return $leaveTypes->map(function ($type) use ($employeeId, $year) {
            $used = LeaveRequest::where('employee_id', $employeeId)
                ->where('leave_type_id', $type->id)
                ->where('status', 'Approved')
                ->whereYear('from_date', $year)
                ->sum('days_requested');

            return [
                'leave_type_id' => $type->id,
                'name' => $type->name,
                'color' => $type->color,
                'allowed' => $type->days_allowed,
                'used' => $used,
                'remaining' => max(0, $type->days_allowed - $used),
            ];
        })->toArray();
    }

    private function recordActivity(int $employeeId, ?string $actor, string $action, string $description): void
    {
        EmployeeActivityLog::create([
            'employee_id' => $employeeId,
            'actor_name' => $actor,
            'action' => $action,
            'description' => $description,
        ]);
    }

    private function applyAvatarUpload(Request $request, Employee $employee): void
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($employee->avatar) {
            Storage::disk('public')->delete($employee->avatar);
        }

        $path = $request->file('avatar')->store('hr/avatars', 'public');
        $employee->avatar = $path;

        if (Schema::hasColumn('employees', 'profile_photo_path')) {
            $employee->profile_photo_path = $path;
        }

        $employee->save();
    }

    private function resolveEmployeeForAuthenticatedUser(Request $request): ?Employee
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        if ($user->email) {
            $email = mb_strtolower((string) $user->email);
            $employee = Employee::query()
                ->where(function ($query) use ($email) {
                    $query->whereRaw('LOWER(work_email) = ?', [$email])
                        ->orWhereRaw('LOWER(personal_email) = ?', [$email]);
                })
                ->first();

            if ($employee) {
                return $employee;
            }
        }

        $name = trim((string) ($user->name ?? ''));
        if ($name === '') {
            return null;
        }

        $normalized = mb_strtolower(preg_replace('/\s+/', ' ', $name) ?? $name);
        $parts = array_values(array_filter(explode(' ', $normalized)));
        $first = $parts[0] ?? null;
        $last = count($parts) > 1 ? $parts[count($parts) - 1] : null;

        return Employee::query()
            ->where(function ($query) use ($normalized, $first, $last) {
                $query->whereRaw('LOWER(first_name) = ?', [$normalized]);

                if ($first) {
                    $query->orWhereRaw('LOWER(first_name) = ?', [$first]);
                }

                if ($first && $last) {
                    $query->orWhere(function ($nested) use ($first, $last) {
                        $nested->whereRaw('LOWER(first_name) = ?', [$first])
                            ->whereRaw('LOWER(last_name) = ?', [$last]);
                    });
                }
            })
            ->orderByDesc('updated_at')
            ->first();
    }

    private function resolveEmployeeLoginEmail(Employee $employee): ?string
    {
        $email = $employee->work_email ?: $employee->personal_email;

        if (! $email) {
            return null;
        }

        return mb_strtolower(trim((string) $email));
    }

    private function userExistsByEmail(?string $email): bool
    {
        if (! $email) {
            return false;
        }

        return User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->exists();
    }

    private function employeeHasUserAccount(Employee $employee): bool
    {
        return $this->userExistsByEmail($this->resolveEmployeeLoginEmail($employee));
    }
}


