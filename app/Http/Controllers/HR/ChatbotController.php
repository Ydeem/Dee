<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Applicant;
use App\Models\HR\ChatbotAudit;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\Expense;
use App\Models\HR\HrPermission;
use App\Models\HR\JobOpening;
use App\Models\HR\LeaveRequest;
use App\Models\HR\PayrollRun;
use App\Models\HR\Payslip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ChatbotController extends Controller
{
    public function message(Request $request): JsonResponse
    {
        $startedAt = microtime(true);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['nullable', 'string', 'in:user,assistant'],
            'history.*.content' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User|null $user */
        $user = $request->user();
        abort_if(! $user, 401);

        $messageText = trim((string) $validated['message']);
        $context = $this->loadUserContext($user);
        $topic = $this->detectTopic($messageText);

        $blockedMessage = $this->checkBlocked($messageText, $context);
        if ($blockedMessage !== null) {
            $this->logAudit($context, [
                'message' => $messageText,
                'topic' => $topic,
                'blocked' => true,
                'block_reason' => $blockedMessage,
                'actions_count' => 0,
                'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'message' => $blockedMessage,
                'actions' => [],
            ]);
        }

        $realData = $this->fetchRealData($messageText, $user, $context);
        $systemPrompt = $this->buildSystemPrompt($context, $realData);

        $history = collect($validated['history'] ?? [])
            ->take(-6)
            ->map(function ($item): array {
                return [
                    'role' => ($item['role'] ?? '') === 'assistant' ? 'assistant' : 'user',
                    'content' => trim((string) ($item['content'] ?? '')),
                ];
            })
            ->filter(fn (array $item) => $item['content'] !== '')
            ->values()
            ->all();

        $history[] = [
            'role' => 'user',
            'content' => $messageText,
        ];

        $response = $this->callClaude($systemPrompt, $history);
        $parsed = $this->parseResponse($response, $context);

        $this->logAudit($context, [
            'message' => $messageText,
            'topic' => $topic,
            'blocked' => false,
            'actions_count' => count($parsed['actions'] ?? []),
            'response_excerpt' => Str::limit((string) ($parsed['message'] ?? ''), 220),
            'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return response()->json([
            'message' => $parsed['message'],
            'actions' => $parsed['actions'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadUserContext(User $user): array
    {
        $roleModels = $user->hrRoles()
            ->with('permissions:id,name')
            ->get();

        $roles = $roleModels
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        $isAdmin = $user->isHrAdmin()
            || collect($roles)
                ->map(fn ($role) => mb_strtolower(trim((string) $role)))
                ->contains('super-admin');

        $permissions = $isAdmin
            ? HrPermission::query()->pluck('name')->filter()->values()->all()
            : $roleModels
                ->flatMap(fn ($role) => $role->permissions)
                ->pluck('name')
                ->filter()
                ->unique()
                ->values()
                ->all();

        $permissionSet = collect($permissions)
            ->map(fn ($permission) => mb_strtolower(trim((string) $permission)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $employee = Employee::with([
            'designation:id,name',
            'department:id,name',
        ])
            ->where(function ($query) use ($user): void {
                $query->where('work_email', $user->email)
                    ->orWhere('personal_email', $user->email);
            })
            ->first();

        $access = $this->buildAccessProfile($roles, $permissionSet, $isAdmin);
        $firstName = trim(explode(' ', trim((string) $user->name))[0] ?? 'there');

        return [
            'user' => $user,
            'roles' => $roles,
            'permissions' => $permissions,
            'permission_set' => $permissionSet,
            'is_admin' => $isAdmin,
            'employee' => $employee,
            'access' => $access,
            'name' => (string) $user->name,
            'first_name' => $firstName !== '' ? $firstName : 'there',
            'role_label' => $roles[0] ?? 'Employee',
        ];
    }

    /**
     * @param array<int, string> $roles
     * @param array<int, string> $permissionSet
     * @return array<string, bool>
     */
    private function buildAccessProfile(array $roles, array $permissionSet, bool $isAdmin): array
    {
        $roleSet = collect($roles)
            ->map(fn ($role) => mb_strtolower(trim((string) $role)))
            ->filter()
            ->values();

        $permissions = collect($permissionSet)
            ->map(fn ($permission) => mb_strtolower(trim((string) $permission)))
            ->filter()
            ->values();

        $has = function (string ...$items) use ($permissions): bool {
            foreach ($items as $item) {
                if ($permissions->contains(mb_strtolower(trim($item)))) {
                    return true;
                }
            }

            return false;
        };

        $isEmployeeRole = $roleSet->contains('employee')
            && ! $roleSet->contains('hr admin')
            && ! $roleSet->contains('hr manager')
            && ! $roleSet->contains('payroll officer')
            && ! $roleSet->contains('recruiter')
            && ! $roleSet->contains('supervisor');

        return [
            'is_admin' => $isAdmin,
            'self_only' => ! $isAdmin && $isEmployeeRole,
            'employees' => $isAdmin || $has('view employees'),
            'departments' => $isAdmin || $has('view departments'),
            'designations' => $isAdmin || $has('view designations'),
            'attendance' => $isAdmin || $has('view attendance'),
            'leave' => $isAdmin || $has('view leave', 'view leave requests'),
            'approve_leave' => $isAdmin || $has('approve leave', 'approve leave requests'),
            'shifts' => $isAdmin || $has('view shifts'),
            'payroll' => $isAdmin || $has('view payroll', 'view payslips'),
            'expenses' => $isAdmin || $has('view expenses'),
            'approve_expenses' => $isAdmin || $has('approve expenses'),
            'recruitment' => $isAdmin || $has('view recruitment', 'view job openings', 'view applicants', 'view onboarding'),
            'reports' => $isAdmin || $has('view reports'),
            'settings' => $isAdmin || $has('view hr settings', 'manage roles', 'manage permissions', 'assign roles'),
            'messages' => $isAdmin || $has('view messages', 'send messages'),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function checkBlocked(string $message, array $context): ?string
    {
        $text = mb_strtolower(trim($message));
        if ($text === '') {
            return 'Please type a message and I will help.';
        }

        /** @var array<string, bool> $access */
        $access = $context['access'];
        $firstName = (string) ($context['first_name'] ?? 'there');

        $hrKeywords = [
            'hr', 'employee', 'leave', 'attendance', 'payroll', 'salary', 'payslip', 'expense',
            'recruit', 'applicant', 'job', 'onboarding', 'department', 'designation', 'shift',
            'report', 'analytics', 'settings', 'role', 'permission', 'profile', 'dashboard',
            'apply', 'approve', 'pending', 'navigate', 'open', 'go to',
        ];

        $isHrQuestion = collect($hrKeywords)
            ->contains(fn ($keyword) => str_contains($text, $keyword));

        if (! $isHrQuestion) {
            return 'I can only help with HR questions like leave, attendance, payroll, recruitment, and navigation in this HR system.';
        }

        $rules = [
            [
                'keywords' => ['salary', 'salaries', 'payroll', 'payslip', 'net pay', 'gross pay', 'deduction', 'process payroll'],
                'capability' => 'payroll',
                'message' => "Sorry {$firstName}, you do not have access to payroll information.",
            ],
            [
                'keywords' => ['recruitment', 'applicant', 'job opening', 'candidate', 'interview', 'hiring'],
                'capability' => 'recruitment',
                'message' => "Sorry {$firstName}, you do not have access to recruitment data.",
            ],
            [
                'keywords' => ['report', 'analytics', 'statistics', 'chart', 'kpi'],
                'capability' => 'reports',
                'message' => "Sorry {$firstName}, you do not have access to reports.",
            ],
            [
                'keywords' => ['settings', 'roles', 'permissions', 'assign role', 'portal accounts'],
                'capability' => 'settings',
                'message' => 'HR Settings and Roles & Permissions are restricted to administrators.',
            ],
            [
                'keywords' => ['attendance', 'clock in', 'clock out', 'timesheet'],
                'capability' => 'attendance',
                'message' => "Sorry {$firstName}, you do not have access to attendance data.",
            ],
        ];

        foreach ($rules as $rule) {
            if (! $this->hasKeyword($text, $rule['keywords'])) {
                continue;
            }

            if (empty($access[$rule['capability']])) {
                return (string) $rule['message'];
            }
        }

        $asksAllEmployees = $this->hasKeyword($text, [
            'all employees', 'employee list', 'list employees', 'show all staff', 'everyone', 'entire department',
        ]);

        if ($asksAllEmployees && empty($access['employees'])) {
            return 'You do not have access to employee directory data.';
        }

        if (! empty($access['self_only'])) {
            if ($asksAllEmployees) {
                return "I can only help with your own data, {$firstName}.";
            }

            $otherPersonPatterns = [
                '/\bhow much does\s+[a-z]+\s+(earn|make)\b/i',
                '/\bsalary\s+of\s+[a-z]+\b/i',
                '/\bshow\s+[a-z]+\s+salary\b/i',
                '/\bwho\s+is\s+on\s+leave\b/i',
            ];

            foreach ($otherPersonPatterns as $pattern) {
                if (preg_match($pattern, $message) === 1) {
                    return 'I cannot share other employees\' private information. I can only help with your own records.';
                }
            }
        }

        if (str_contains($text, 'all employee salaries') || str_contains($text, 'everyone salary')) {
            return 'I cannot share other employees\' salary information. This data is confidential.';
        }

        return null;
    }

    private function hasKeyword(string $message, array $keywords): bool
    {
        return collect($keywords)->contains(fn ($keyword) => str_contains($message, mb_strtolower((string) $keyword)));
    }
    private function detectTopic(string $message): string
    {
        $text = mb_strtolower($message);

        $topicMap = [
            'leave' => ['leave', 'balance', 'absence'],
            'attendance' => ['attendance', 'clock', 'timesheet', 'late'],
            'payroll' => ['payroll', 'salary', 'payslip', 'deduction', 'net pay', 'gross pay'],
            'recruitment' => ['recruitment', 'applicant', 'candidate', 'job opening', 'hiring', 'interview'],
            'employees' => ['employee', 'staff', 'workforce', 'headcount'],
            'reports' => ['report', 'analytics', 'kpi', 'statistics'],
            'settings' => ['settings', 'roles', 'permissions', 'accounts'],
            'messages' => ['message', 'announcement', 'inbox'],
            'navigation' => ['navigate', 'go to', 'open page', 'take me to'],
            'profile' => ['my profile', 'my info', 'my details'],
        ];

        foreach ($topicMap as $topic => $keywords) {
            if ($this->hasKeyword($text, $keywords)) {
                return $topic;
            }
        }

        return 'general';
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $payload
     */
    private function logAudit(array $context, array $payload): void
    {
        try {
            /** @var User|null $user */
            $user = $context['user'] ?? null;
            $roles = $context['roles'] ?? [];
            $permissions = $context['permissions'] ?? [];

            ChatbotAudit::query()->create([
                'user_id' => $user?->id,
                'user_name' => (string) ($context['name'] ?? $user?->name),
                'role_label' => (string) ($context['role_label'] ?? ''),
                'roles' => is_array($roles) ? array_values($roles) : [],
                'permissions_count' => is_array($permissions) ? count($permissions) : 0,
                'message' => (string) ($payload['message'] ?? ''),
                'topic' => (string) ($payload['topic'] ?? 'general'),
                'blocked' => (bool) ($payload['blocked'] ?? false),
                'block_reason' => !empty($payload['block_reason'])
                    ? Str::limit((string) $payload['block_reason'], 255)
                    : null,
                'response_excerpt' => !empty($payload['response_excerpt'])
                    ? Str::limit((string) $payload['response_excerpt'], 1000)
                    : null,
                'actions_count' => (int) ($payload['actions_count'] ?? 0),
                'response_time_ms' => (int) ($payload['response_time_ms'] ?? 0),
                'meta' => [
                    'is_admin' => (bool) ($context['is_admin'] ?? false),
                ],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Chatbot audit log write failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private function fetchRealData(string $message, User $user, array $context): array
    {
        $text = mb_strtolower(trim($message));
        /** @var Employee|null $employee */
        $employee = $context['employee'] ?? null;
        /** @var array<string, bool> $access */
        $access = $context['access'];

        $data = [];

        try {
            if ($employee && str_contains($text, 'leave') && $this->hasKeyword($text, ['balance', 'remaining', 'days', 'how many'])) {
                if (Schema::hasTable('employee_leave_balances')) {
                    $balances = DB::table('employee_leave_balances')
                        ->where('employee_id', $employee->id)
                        ->select('leave_type', 'total_days', 'used_days')
                        ->get();

                    if ($balances->isNotEmpty()) {
                        $data['leave_balance'] = $balances
                            ->map(function ($row) {
                                $remaining = max((int) $row->total_days - (int) $row->used_days, 0);

                                return sprintf(
                                    '%s: %d days remaining (used %d of %d)',
                                    (string) $row->leave_type,
                                    $remaining,
                                    (int) $row->used_days,
                                    (int) $row->total_days
                                );
                            })
                            ->implode('; ');
                    }
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Chatbot leave balance fetch failed', ['error' => $exception->getMessage()]);
        }

        try {
            if ($employee && str_contains($text, 'leave') && $this->hasKeyword($text, ['request', 'pending', 'history', 'applied', 'status'])) {
                if (Schema::hasTable('leave_requests')) {
                    $requests = LeaveRequest::query()
                        ->with('leaveType:id,name')
                        ->where('employee_id', $employee->id)
                        ->latest('created_at')
                        ->limit(5)
                        ->get();

                    if ($requests->isNotEmpty()) {
                        $data['recent_leave_requests'] = $requests
                            ->map(function (LeaveRequest $request): string {
                                $type = $request->leaveType?->name ?? 'Leave';
                                $from = optional($request->from_date)->format('Y-m-d') ?? (string) $request->from_date;
                                $to = optional($request->to_date)->format('Y-m-d') ?? (string) $request->to_date;

                                return "{$type} ({$from} to {$to}) - {$request->status}";
                            })
                            ->implode('; ');
                    }
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Chatbot leave request fetch failed', ['error' => $exception->getMessage()]);
        }

        try {
            if ($employee && str_contains($text, 'attendance')) {
                $attendanceTable = Schema::hasTable('attendances')
                    ? 'attendances'
                    : (Schema::hasTable('employee_attendance') ? 'employee_attendance' : null);

                if ($attendanceTable !== null) {
                    $summary = DB::table($attendanceTable)
                        ->where('employee_id', $employee->id)
                        ->whereMonth('date', now()->month)
                        ->whereYear('date', now()->year)
                        ->selectRaw('COUNT(*) as total')
                        ->selectRaw("SUM(CASE WHEN status IN ('Present', 'present') THEN 1 ELSE 0 END) as present")
                        ->selectRaw("SUM(CASE WHEN status IN ('Absent', 'absent') THEN 1 ELSE 0 END) as absent")
                        ->selectRaw("SUM(CASE WHEN status IN ('Late', 'late') THEN 1 ELSE 0 END) as late")
                        ->first();

                    if ($summary !== null) {
                        $data['attendance_this_month'] = 'This month - Present: ' . (int) ($summary->present ?? 0)
                            . ', Absent: ' . (int) ($summary->absent ?? 0)
                            . ', Late: ' . (int) ($summary->late ?? 0) . '.';
                    }
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Chatbot attendance fetch failed', ['error' => $exception->getMessage()]);
        }

        try {
            if (! empty($access['payroll']) && $this->hasKeyword($text, ['payroll', 'payslip', 'salary', 'net pay', 'gross pay'])) {
                $latestPayroll = PayrollRun::query()->latest('created_at')->first();
                if ($latestPayroll) {
                    $label = $latestPayroll->month_label ?: (trim(($latestPayroll->month ?? '') . '/' . ($latestPayroll->year ?? '')) ?: 'N/A');
                    $amount = (float) ($latestPayroll->total_net ?? 0);
                    $data['latest_payroll'] = 'Latest payroll run: ' . $label
                        . ' - Status: ' . ($latestPayroll->status ?? 'N/A')
                        . ' - Total net: GHS ' . number_format($amount, 2) . '.';
                }

                if (Schema::hasTable('payslips')) {
                    $pendingPayslips = Payslip::query()
                        ->whereIn('status', ['Pending', 'Draft', 'Generated', 'Unpaid'])
                        ->count();

                    $data['pending_payslips'] = $pendingPayslips . ' payslips pending action.';

                    if ($employee && $this->hasKeyword($text, ['my payslip', 'my salary', 'my payroll', 'my net'])) {
                        $myLatest = Payslip::query()
                            ->with('payrollRun:id,month,year,pay_date')
                            ->where('employee_id', $employee->id)
                            ->latest('created_at')
                            ->first();

                        if ($myLatest) {
                            $period = $myLatest->payrollRun?->month_label
                                ?? trim(($myLatest->payrollRun?->month ?? '') . '/' . ($myLatest->payrollRun?->year ?? ''));

                            $data['my_latest_payslip'] = 'Your latest payslip'
                                . ($period !== '' ? ' (' . $period . ')' : '')
                                . ' has net salary GHS ' . number_format((float) ($myLatest->net_salary ?? 0), 2)
                                . ' and status ' . ($myLatest->status ?? 'N/A') . '.';
                        }
                    }
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Chatbot payroll fetch failed', ['error' => $exception->getMessage()]);
        }

        try {
            if (! empty($access['employees']) && $this->hasKeyword($text, ['employee', 'staff', 'headcount', 'workforce'])) {
                $stats = Employee::query()
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("SUM(CASE WHEN employment_status = 'Active' THEN 1 ELSE 0 END) as active")
                    ->selectRaw("SUM(CASE WHEN employment_status = 'Inactive' THEN 1 ELSE 0 END) as inactive")
                    ->first();

                if ($stats) {
                    $data['employee_stats'] = 'Total employees: ' . (int) ($stats->total ?? 0)
                        . ', Active: ' . (int) ($stats->active ?? 0)
                        . ', Inactive: ' . (int) ($stats->inactive ?? 0) . '.';
                }

                if (Schema::hasTable('leave_requests')) {
                    $today = now()->toDateString();
                    $onLeaveToday = LeaveRequest::query()
                        ->where('status', 'Approved')
                        ->whereDate('from_date', '<=', $today)
                        ->whereDate('to_date', '>=', $today)
                        ->count();

                    $data['on_leave_today'] = $onLeaveToday . ' employees are on leave today.';
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Chatbot employee stats fetch failed', ['error' => $exception->getMessage()]);
        }

        try {
            $canSeeApprovals = ! empty($access['is_admin']) || ! empty($access['approve_leave']) || ! empty($access['approve_expenses']);
            if ($canSeeApprovals && $this->hasKeyword($text, ['pending', 'approval', 'approve'])) {
                $pendingLeave = Schema::hasTable('leave_requests')
                    ? LeaveRequest::query()->where('status', 'Pending')->count()
                    : 0;

                $pendingExpenses = Schema::hasTable('expenses')
                    ? Expense::query()->where('status', 'Pending')->count()
                    : 0;

                $data['pending_approvals'] = 'Pending approvals - Leave requests: ' . $pendingLeave
                    . ', Expenses: ' . $pendingExpenses . '.';
            }
        } catch (Throwable $exception) {
            Log::warning('Chatbot pending approvals fetch failed', ['error' => $exception->getMessage()]);
        }

        try {
            if (! empty($access['recruitment']) && $this->hasKeyword($text, ['recruitment', 'job', 'applicant', 'hiring', 'pipeline'])) {
                $openJobs = JobOpening::query()->where('status', 'Open')->count();
                $newApplicantsThisMonth = Applicant::query()
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count();

                $data['recruitment_stats'] = 'Open job positions: ' . $openJobs
                    . '. New applicants this month: ' . $newApplicantsThisMonth . '.';
            }
        } catch (Throwable $exception) {
            Log::warning('Chatbot recruitment fetch failed', ['error' => $exception->getMessage()]);
        }

        try {
            if ($employee && $this->hasKeyword($text, ['my profile', 'my info', 'my details', 'about me'])) {
                $data['my_profile'] = 'Name: ' . $employee->full_name
                    . '. Employee Code: ' . ($employee->employee_id ?? 'N/A')
                    . '. Department: ' . ($employee->department?->name ?? 'N/A')
                    . '. Designation: ' . ($employee->designation?->name ?? 'N/A')
                    . '. Status: ' . ($employee->employment_status ?? 'N/A')
                    . '. Join Date: ' . (optional($employee->join_date)->format('Y-m-d') ?? 'N/A') . '.';
            }
        } catch (Throwable $exception) {
            Log::warning('Chatbot profile context fetch failed', ['error' => $exception->getMessage()]);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $realData
     */
    private function buildSystemPrompt(array $context, array $realData): string
    {
        $firstName = (string) ($context['first_name'] ?? 'there');
        $roleLabel = (string) ($context['role_label'] ?? 'Employee');
        /** @var Employee|null $employee */
        $employee = $context['employee'] ?? null;
        /** @var array<string, bool> $access */
        $access = $context['access'];
        /** @var array<int, string> $permissions */
        $permissions = $context['permissions'];
        /** @var array<int, string> $roles */
        $roles = $context['roles'];

        $allowedRoutes = $this->allowedRoutes($access);
        $routeLines = collect($allowedRoutes)
            ->map(fn ($label, $route) => '- ' . $label . ': ' . $route)
            ->implode("\n");

        $realDataLines = empty($realData)
            ? 'No specific real-time records matched this question.'
            : collect($realData)
                ->map(fn ($value, $key) => '- ' . Str::of($key)->replace('_', ' ')->title() . ': ' . $value)
                ->implode("\n");

        $employeeContext = $employee
            ? "- Employee Code: " . ($employee->employee_id ?? 'N/A')
                . "\n- Department: " . ($employee->department?->name ?? 'N/A')
                . "\n- Designation: " . ($employee->designation?->name ?? 'N/A')
                . "\n- Employment Status: " . ($employee->employment_status ?? 'N/A')
            : '- Employee profile not linked by email.';

        $roleSpecific = $this->roleInstruction($roles, $access, $firstName);

        return "You are Wilson Labs HR Assistant.
You are helping {$firstName} (role: {$roleLabel}).

MANDATORY RULES:
1) Keep responses under 120 words.
2) Only answer HR-related questions.
3) Use ONLY data provided in REAL DATA CONTEXT when giving numbers.
4) Never reveal restricted or private data.
5) If data is unavailable, say so clearly and guide to an allowed page.
6) Friendly, concise, task-focused.

ACCESS SUMMARY:
- Is admin: " . (! empty($access['is_admin']) ? 'yes' : 'no') . "
- Self only: " . (! empty($access['self_only']) ? 'yes' : 'no') . "
- Permissions: " . (empty($permissions) ? 'none' : implode(', ', $permissions)) . "

ALLOWED ROUTES:
{$routeLines}

REAL DATA CONTEXT:
{$realDataLines}

CURRENT USER PROFILE:
{$employeeContext}

ROLE INSTRUCTION:
{$roleSpecific}

When suggesting a page, append this exact token at the end:
[NAVIGATE:/route:Button Label:mdi-icon]
Only use routes from ALLOWED ROUTES.";
    }

    /**
     * @param array<int, string> $roles
     * @param array<string, bool> $access
     */
    private function roleInstruction(array $roles, array $access, string $firstName): string
    {
        $normalizedRoles = collect($roles)->map(fn ($role) => mb_strtolower(trim((string) $role)))->values();

        if (! empty($access['is_admin'])) {
            return 'Full system access. You may help with all HR modules and operational summaries.';
        }

        if ($normalizedRoles->contains('hr manager')) {
            return 'Help with employees, leave, attendance, recruitment, and reports. Do not provide payroll confidentials unless permission explicitly allows.';
        }

        if ($normalizedRoles->contains('payroll officer')) {
            return 'Focus on payroll, payslips, and expenses. Deny recruitment and settings requests.';
        }

        if ($normalizedRoles->contains('recruiter')) {
            return 'Focus on recruitment and onboarding. Deny payroll and confidential salary requests.';
        }

        if ($normalizedRoles->contains('supervisor')) {
            return 'Help with attendance, leave approvals, shifts, and team-level operational tasks allowed by permissions.';
        }

        return "Only help {$firstName} with their own data (leave, attendance, expenses, payslips, profile). Never share other employees' private data.";
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     */
    private function callClaude(string $systemPrompt, array $messages): string
    {
        $apiKey = (string) config('services.anthropic.key', '');
        if (trim($apiKey) === '') {
            return 'Anthropic API key is missing. Please set ANTHROPIC_API_KEY in your .env file.';
        }

        $model = (string) config('services.anthropic.model', 'claude-3-5-haiku-latest');

        try {
            $response = Http::timeout(18)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 450,
                    'system' => $systemPrompt,
                    'messages' => $messages,
                ]);

            if ($response->failed()) {
                Log::error('Anthropic API error', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return 'I am having trouble connecting right now. Please try again in a moment.';
            }

            $text = $response->json('content.0.text');

            if (is_string($text) && trim($text) !== '') {
                return $text;
            }

            return 'I could not generate a response.';
        } catch (Throwable $exception) {
            Log::error('Chatbot call failed', ['error' => $exception->getMessage()]);

            return 'Sorry, I encountered an error. Please try again.';
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array{message:string,actions:array<int,array{route:string,label:string,icon:string}>}
     */
    private function parseResponse(string $response, array $context): array
    {
        $actions = [];
        /** @var array<string, bool> $access */
        $access = $context['access'];

        preg_match_all('/\[NAVIGATE:([^:\]]+):([^:\]]+):([^\]]+)\]/', $response, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $route = trim((string) ($match[1] ?? ''));
            $label = trim((string) ($match[2] ?? 'Open'));
            $icon = trim((string) ($match[3] ?? 'mdi-arrow-right'));

            if ($route === '' || ! $this->canAccessRoute($route, $access)) {
                continue;
            }

            $actions[] = [
                'route' => $route,
                'label' => $label,
                'icon' => $icon,
            ];
        }

        $actions = collect($actions)
            ->unique('route')
            ->take(3)
            ->values()
            ->all();

        $cleanMessage = trim((string) preg_replace('/\[NAVIGATE:[^\]]+\]/', '', $response));

        if ($cleanMessage === '') {
            $cleanMessage = 'I can help with that. Please use the suggested action below.';
        }

        return [
            'message' => $cleanMessage,
            'actions' => $actions,
        ];
    }

    /**
     * @param array<string, bool> $access
     */
    private function canAccessRoute(string $route, array $access): bool
    {
        if (! empty($access['is_admin'])) {
            return true;
        }

        $map = [
            '/hr/dashboard' => true,
            '/hr/employees' => ! empty($access['employees']),
            '/hr/departments' => ! empty($access['departments']),
            '/hr/designations' => ! empty($access['designations']),
            '/hr/attendance' => ! empty($access['attendance']),
            '/hr/leave-management' => ! empty($access['leave']),
            '/hr/shifts' => ! empty($access['shifts']),
            '/hr/payroll' => ! empty($access['payroll']),
            '/hr/expenses' => ! empty($access['expenses']),
            '/hr/job-openings' => ! empty($access['recruitment']),
            '/hr/applicants' => ! empty($access['recruitment']),
            '/hr/onboarding' => ! empty($access['recruitment']),
            '/hr/reports' => ! empty($access['reports']),
            '/hr/messages' => ! empty($access['messages']),
            '/hr/announcements' => ! empty($access['messages']),
            '/hr/settings' => ! empty($access['settings']),
            '/hr/roles-permissions' => ! empty($access['settings']),
            '/hr/accounts' => ! empty($access['settings']),
        ];

        foreach ($map as $path => $allowed) {
            if (str_starts_with($route, $path)) {
                return (bool) $allowed;
            }
        }

        return false;
    }

    /**
     * @param array<string, bool> $access
     * @return array<string, string>
     */
    private function allowedRoutes(array $access): array
    {
        $routes = [
            '/hr/dashboard' => 'Dashboard',
        ];

        if (! empty($access['employees'])) {
            $routes['/hr/employees'] = 'Employees';
        }
        if (! empty($access['departments'])) {
            $routes['/hr/departments'] = 'Departments';
        }
        if (! empty($access['designations'])) {
            $routes['/hr/designations'] = 'Designations';
        }
        if (! empty($access['attendance'])) {
            $routes['/hr/attendance'] = 'Attendance';
        }
        if (! empty($access['leave'])) {
            $routes['/hr/leave-management'] = 'Leave Management';
        }
        if (! empty($access['shifts'])) {
            $routes['/hr/shifts'] = 'Shifts';
        }
        if (! empty($access['payroll'])) {
            $routes['/hr/payroll'] = 'Payroll';
        }
        if (! empty($access['expenses'])) {
            $routes['/hr/expenses'] = 'Expenses';
        }
        if (! empty($access['recruitment'])) {
            $routes['/hr/job-openings'] = 'Job Openings';
            $routes['/hr/applicants'] = 'Applicants';
            $routes['/hr/onboarding'] = 'Onboarding';
        }
        if (! empty($access['reports'])) {
            $routes['/hr/reports'] = 'Reports';
        }
        if (! empty($access['messages'])) {
            $routes['/hr/messages'] = 'Messages';
            $routes['/hr/announcements'] = 'Announcements';
        }
        if (! empty($access['settings'])) {
            $routes['/hr/settings'] = 'HR Settings';
            $routes['/hr/roles-permissions'] = 'Roles & Permissions';
            $routes['/hr/accounts'] = 'Portal Accounts';
        }

        return $routes;
    }
}




