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
