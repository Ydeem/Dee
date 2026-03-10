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

    // Other app pages (sidebar navigation)
    Route::get('/starter', fn () => Inertia::render('Starter'))->name('starter');
    Route::get('/utils/typography', fn () => Inertia::render('Utils/Typography'))->name('utils.typography');
    Route::get('/utils/colors', fn () => Inertia::render('Utils/Colors'))->name('utils.colors');
    Route::get('/utils/shadows', fn () => Inertia::render('Utils/Shadows'))->name('utils.shadows');

    Route::get('/hr/dashboard', fn () => Inertia::render('HR/Dashboard/Index'))->name('hr.dashboard');
    Route::get('/hr/employees', fn () => Inertia::render('HR/Employees/Index'))->name('hr.employees.index');
    Route::get('/hr/departments', fn () => Inertia::render('HR/Departments/Index'))->name('hr.departments.index');
    Route::get('/hr/designations', fn () => Inertia::render('HR/Designations/Index'))->name('hr.designations.index');
    Route::get('/hr/attendance', fn () => Inertia::render('HR/Attendance/Index'))->name('hr.attendance.index');
    Route::get('/hr/leave-management', fn () => Inertia::render('HR/Leave/Index'))->name('hr.leave.index');
    Route::get('/hr/employees/create', fn () => Inertia::render('HR/Employees/Create'))->name('hr.employees.create');
    Route::get('/hr/employees/{id}/edit', fn ($id) => Inertia::render('HR/Employees/Edit', ['id' => (int) $id]))->name('hr.employees.edit');
    Route::get('/hr/employees/{id}', fn ($id) => Inertia::render('HR/Employees/Show', ['id' => (int) $id]))->name('hr.employees.show');
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
