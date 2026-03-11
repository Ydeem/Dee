<?php

use App\Http\Controllers\AuthController;
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
| Authenticated routes – dashboard and app pages (session-based navigation)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

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

    Route::get('/hr/dashboard', fn () => Inertia::render('HR/Dashboard/Index'))->middleware('permission:view hr dashboard')->name('hr.dashboard');
    Route::get('/hr/employees', fn () => Inertia::render('HR/Employees/Index'))->middleware('permission:view employees')->name('hr.employees.index');
    Route::get('/hr/departments', fn () => Inertia::render('HR/Departments/Index'))->middleware('permission:view departments')->name('hr.departments.index');
    Route::get('/hr/designations', fn () => Inertia::render('HR/Designations/Index'))->middleware('permission:view designations')->name('hr.designations.index');
    Route::get('/hr/attendance', fn () => Inertia::render('HR/Attendance/Index'))->middleware('permission:view attendance')->name('hr.attendance.index');
    Route::get('/hr/leave-management', fn () => Inertia::render('HR/Leave/Index'))->middleware('permission:view leave requests')->name('hr.leave.index');
    Route::get('/hr/shifts', fn () => Inertia::render('HR/Shifts/Index'))->middleware('permission:view shifts')->name('hr.shifts.index');
    Route::get('/hr/job-openings', fn () => Inertia::render('HR/Recruitment/JobOpenings/Index'))->middleware('permission:view job openings')->name('hr.job-openings.index');
    Route::get('/hr/applicants', fn () => Inertia::render('HR/Recruitment/Applicants/Index'))->middleware('permission:view applicants')->name('hr.applicants.index');
    Route::get('/hr/onboarding', fn () => Inertia::render('HR/Recruitment/Onboarding/Index'))->middleware('permission:view onboarding')->name('hr.onboarding.index');
    Route::get('/hr/payroll', fn () => Inertia::render('HR/Payroll/Index'))->middleware('permission:view payroll')->name('hr.payroll.index');
    Route::get('/hr/expenses', fn () => Inertia::render('HR/Expenses/Index'))->middleware('permission:view expenses')->name('hr.expenses.index');
    Route::get('/hr/reports', fn () => Inertia::render('HR/Reports/Index'))->middleware('permission:view reports')->name('hr.reports.index');
    Route::get('/hr/settings', fn () => Inertia::render('HR/Settings/Index'))->middleware('permission:view hr settings')->name('hr.settings.index');
    Route::get('/hr/roles-permissions', fn () => Inertia::render('HR/Settings/RolesPermissions'))->middleware('permission:manage roles')->name('hr.roles-permissions.index');
    Route::get('/hr/payroll/{id}/payslip', fn ($id) => Inertia::render('HR/Payroll/Payslip', ['id' => (int) $id]))->middleware('permission:view payslips')->name('hr.payroll.payslip');
    Route::get('/hr/employees/create', fn () => Inertia::render('HR/Employees/Create'))->middleware('permission:create employees')->name('hr.employees.create');
    Route::get('/hr/employees/{id}/edit', fn ($id) => Inertia::render('HR/Employees/Edit', ['id' => (int) $id]))->middleware('permission:edit employees')->name('hr.employees.edit');
    Route::get('/hr/employees/{id}', fn ($id) => Inertia::render('HR/Employees/Show', ['id' => (int) $id]))->middleware('permission:view employees')->name('hr.employees.show');

    Route::prefix('api/hr/dashboard')->group(function () {
        Route::get('summary', [\App\Http\Controllers\HR\HRDashboardController::class, 'summary']);
        Route::get('stats', [\App\Http\Controllers\HR\HRDashboardController::class, 'stats']);
        Route::get('attendance-chart', [\App\Http\Controllers\HR\HRDashboardController::class, 'attendanceChart']);
        Route::get('pending-actions', [\App\Http\Controllers\HR\HRDashboardController::class, 'pendingActions']);
        Route::get('recent-hires', [\App\Http\Controllers\HR\HRDashboardController::class, 'recentHires']);
        Route::get('upcoming-events', [\App\Http\Controllers\HR\HRDashboardController::class, 'upcomingEvents']);
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
