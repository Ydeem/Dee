<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\HR\ChatbotController;
use App\Http\Controllers\HR\DashboardController;
use App\Http\Controllers\HR\MessagingController;
use App\Http\Controllers\HR\RolesController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Guest routes (login, register, etc.)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/login1', fn () => Inertia::render('Auth/Login1'));
    Route::get('/register1', fn () => Inertia::render('Auth/Register1'));
    Route::get('/forgot-pwd1', fn () => Inertia::render('Auth/ForgotPwd1'));
});

/*
|--------------------------------------------------------------------------
| Authenticated routes - dashboard and app pages (session-based navigation)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    // Profile routes
    Route::prefix('api/profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::post('/update', [ProfileController::class, 'update']);
        Route::post('/password', [ProfileController::class, 'updatePassword']);
        Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
        Route::get('/social', [ProfileController::class, 'getSocial']);
        Route::post('/social', [ProfileController::class, 'updateSocial']);
    });

    // Account settings
    Route::prefix('api/account')->group(function () {
        Route::get('/', [AccountSettingsController::class, 'index']);
        Route::post('/update', [AccountSettingsController::class, 'updateAccount']);
        Route::post('/password', [AccountSettingsController::class, 'updatePassword']);
        Route::post('/notifications', [AccountSettingsController::class, 'updateNotifications']);
        Route::post('/privacy', [AccountSettingsController::class, 'updatePrivacy']);
        Route::post('/two-factor', [AccountSettingsController::class, 'enableTwoFactor']);
    });

    // Notifications
    Route::prefix('api/notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::patch('/{id}/read', [NotificationController::class, 'markRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/', [NotificationController::class, 'clearAll']);
    });

    Route::prefix('api/hr')->group(function () {
        Route::get('roles', [RolesController::class, 'index']);
        Route::get('roles/{id}', [RolesController::class, 'show'])->whereNumber('id');
        Route::post('roles', [RolesController::class, 'store']);
        Route::put('roles/{id}', [RolesController::class, 'update']);
        Route::post('roles/{id}/permissions', [RolesController::class, 'syncPermissions']);
        Route::delete('roles/{id}', [RolesController::class, 'destroy']);

        Route::get('role-assignments', [RolesController::class, 'userAssignments']);
        Route::post('role-assignments', [RolesController::class, 'assignRole']);
        Route::delete('role-assignments/{userId}', [RolesController::class, 'removeRole']);
        Route::post('chatbot/message', [ChatbotController::class, 'message']);
    });

    Route::prefix('api/hr/messages')->group(function () {
        Route::get('/inbox', [MessagingController::class, 'inbox']);
        Route::get('/sent', [MessagingController::class, 'sent']);
        Route::get('/recipients', [MessagingController::class, 'recipients']);
        Route::get('/templates', [MessagingController::class, 'templates']);
        Route::get('/thread/{threadId}', [MessagingController::class, 'thread']);
        Route::post('/send', [MessagingController::class, 'send']);
        Route::post('/bulk', [MessagingController::class, 'bulk']);
        Route::post('/applicant/{id}', [MessagingController::class, 'toApplicant']);
        Route::post('/thread/{threadId}/reply', [MessagingController::class, 'reply']);
        Route::patch('/{id}/read', [MessagingController::class, 'markRead']);
    });

    Route::prefix('api/hr/announcements')->group(function () {
        Route::get('/', [MessagingController::class, 'announcements']);
        Route::post('/', [MessagingController::class, 'createAnnouncement']);
    });

    // Logout (POST to destroy session)
    Route::post('/logout', [ProfileController::class, 'logout'])->name('logout');

    // Main dashboard at /dashboard (and / for convenience)
    Route::get('/dashboard', fn () => Inertia::render('Dashboard/Default'))->name('dashboard');
    Route::get('/', fn () => redirect()->route('dashboard'));
    // Legacy URL: redirect to main dashboard
    Route::get('/dashboard/default', fn () => redirect()->route('dashboard'));

    // Legacy URL(s): redirect to Employees
    Route::get('/Employee/{any?}', fn () => redirect('/hr/employees'))->where('any', '.*');
    Route::get('/employee/{any?}', fn () => redirect('/hr/employees'))->where('any', '.*');

    // Other app pages (sidebar navigation)
    Route::get('/starter', fn () => Inertia::render('Starter'))->name('starter');
    Route::get('/utils/typography', fn () => Inertia::render('Utils/Typography'))->name('utils.typography');
    Route::get('/utils/colors', fn () => Inertia::render('Utils/Colors'))->name('utils.colors');
    Route::get('/utils/shadows', fn () => Inertia::render('Utils/Shadows'))->name('utils.shadows');

    Route::get('/hr/dashboard', fn () => Inertia::render('HR/Dashboard/Index'))->middleware('permission:view hr dashboard|view dashboard')->name('hr.dashboard');
    Route::get('/hr/employees', fn () => Inertia::render('HR/Employees/Index'))->middleware('permission:view employees')->name('hr.employees.index');
    Route::get('/hr/departments', fn () => Inertia::render('HR/Departments/Index'))->middleware('permission:view departments')->name('hr.departments.index');
    Route::get('/hr/designations', fn () => Inertia::render('HR/Designations/Index'))->middleware('permission:view designations')->name('hr.designations.index');
    Route::get('/hr/attendance', fn () => Inertia::render('HR/Attendance/Index'))->middleware('permission:view attendance')->name('hr.attendance.index');
    Route::get('/hr/leave-management', fn () => Inertia::render('HR/Leave/Index'))->middleware('permission:view leave requests|view leave')->name('hr.leave.index');
    Route::get('/hr/shifts', fn () => Inertia::render('HR/Shifts/Index'))->middleware('permission:view shifts')->name('hr.shifts.index');
    Route::get('/hr/job-openings', fn () => Inertia::render('HR/Recruitment/JobOpenings/Index'))->middleware('permission:view job openings|view recruitment')->name('hr.job-openings.index');
    Route::get('/hr/applicants', fn () => Inertia::render('HR/Recruitment/Applicants/Index'))->middleware('permission:view applicants|view recruitment')->name('hr.applicants.index');
    Route::get('/hr/onboarding', fn () => Inertia::render('HR/Recruitment/Onboarding/Index'))->middleware('permission:view onboarding|view recruitment')->name('hr.onboarding.index');
    Route::get('/hr/payroll', fn () => Inertia::render('HR/Payroll/Index'))->middleware('permission:view payroll')->name('hr.payroll.index');
    Route::get('/hr/expenses', fn () => Inertia::render('HR/Expenses/Index'))->middleware('permission:view expenses')->name('hr.expenses.index');
    Route::get('/hr/messages', fn () => Inertia::render('HR/Messages/Index'))->middleware('permission:view messages|send messages')->name('hr.messages.index');
    Route::get('/hr/announcements', fn () => Inertia::render('HR/Announcements/Index'))->middleware('permission:view announcements')->name('hr.announcements.index');
    Route::get('/hr/reports', fn () => Inertia::render('HR/Reports/Index'))->middleware('permission:view reports')->name('hr.reports.index');
    Route::get('/hr/accounts', fn () => Inertia::render('HR/Accounts/Index'))->name('hr.accounts.index');
    Route::get('/hr/settings', fn () => Inertia::render('HR/Settings/Index'))->middleware('permission:view hr settings')->name('hr.settings.index');
    Route::get('/hr/roles-permissions', fn () => Inertia::render('HR/Settings/RolesPermissions'))->middleware('permission:manage roles')->name('hr.roles-permissions.index');
    Route::get('/hr/roles-permissions/{id}', fn ($id) => Inertia::render('HR/Settings/RoleDetails', ['id' => (int) $id]))
        ->whereNumber('id')
        ->middleware('permission:manage roles')
        ->name('hr.roles-permissions.show');
    Route::get('/hr/payroll/{id}/payslip', fn ($id) => Inertia::render('HR/Payroll/Payslip', ['id' => (int) $id]))->middleware('permission:view payslips')->name('hr.payroll.payslip');
    Route::get('/hr/employees/create', fn () => Inertia::render('HR/Employees/Create'))->middleware('permission:create employees')->name('hr.employees.create');
    Route::get('/hr/employees/{id}/edit', fn ($id) => Inertia::render('HR/Employees/Edit', ['id' => (int) $id]))->middleware('permission:edit employees')->name('hr.employees.edit');
    Route::get('/hr/employees/{id}', fn ($id) => Inertia::render('HR/Employees/Show', ['id' => (int) $id]))->middleware('permission:view employees')->name('hr.employees.show');
    Route::get('/profile/social', fn () => Inertia::render('Profile/SocialProfile'))->name('profile.social');
    Route::get('/profile/settings', fn () => Inertia::render('Profile/SocialProfile'))->name('profile.settings');
    Route::get('/profile/billing', fn () => Inertia::render('Profile/Billing'))->name('profile.billing');

    Route::prefix('api/hr/dashboard')->group(function () {
        Route::get('summary', [DashboardController::class, 'summary']);
        Route::get('stats', [DashboardController::class, 'stats']);
        Route::get('attendance-chart', [DashboardController::class, 'attendanceChart']);
        Route::get('pending-actions', [DashboardController::class, 'pendingActions']);
        Route::get('recent-hires', [DashboardController::class, 'recentHires']);
        Route::get('upcoming-events', [DashboardController::class, 'upcomingEvents']);
    });

});

/*
|--------------------------------------------------------------------------
| Redirect /welcome to dashboard (authenticated users) or login (guests)
|--------------------------------------------------------------------------
*/
Route::get('/welcome', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('welcome');

/*
|--------------------------------------------------------------------------
| Public (no auth)
|--------------------------------------------------------------------------
*/
Route::get('/error', fn () => Inertia::render('Error404'))->name('error.404');
