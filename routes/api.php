<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HRDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('hr')->group(function () {
    // Dashboard
    Route::get('/dashboard/summary', [HRDashboardController::class, 'summary']);
    Route::get('/dashboard/stats', [HRDashboardController::class, 'stats']);
    Route::get('/dashboard/attendance-chart', [HRDashboardController::class, 'attendanceChart']);
    Route::get('/dashboard/pending-actions', [HRDashboardController::class, 'pendingActions']);
    Route::get('/dashboard/recent-hires', [HRDashboardController::class, 'recentHires']);
    Route::get('/dashboard/upcoming-events', [HRDashboardController::class, 'upcomingEvents']);

    // Employees
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/options', [EmployeeController::class, 'options']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::post('/employees/bulk-action', [EmployeeController::class, 'bulkAction']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::patch('/employees/{id}/status', [EmployeeController::class, 'setStatus']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
    Route::get('/employees/{id}/attendance', [EmployeeController::class, 'attendance']);
    Route::get('/employees/{id}/leave', [EmployeeController::class, 'leave']);
    Route::get('/employees/{id}/payroll', [EmployeeController::class, 'payroll']);
    Route::get('/employees/{id}/documents', [EmployeeController::class, 'documents']);
    Route::delete('/employees/{id}/documents/{documentId}', [EmployeeController::class, 'deleteDocument']);
    Route::get('/employees/{id}/activity-log', [EmployeeController::class, 'activityLog']);
});
