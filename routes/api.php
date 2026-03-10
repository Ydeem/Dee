<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HRDashboardController;
use App\Http\Controllers\HR\AttendanceController;
use App\Http\Controllers\HR\DepartmentController;
use App\Http\Controllers\HR\DesignationController;
use App\Http\Controllers\HR\LeaveRequestController;
use App\Http\Controllers\HR\LeaveTypeController;
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

    // Departments
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::put('/departments/{id}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

    // Designations
    Route::get('/designations', [DesignationController::class, 'index']);
    Route::post('/designations', [DesignationController::class, 'store']);
    Route::put('/designations/{id}', [DesignationController::class, 'update']);
    Route::delete('/designations/{id}', [DesignationController::class, 'destroy']);

    // Attendance
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::put('/attendance/{id}', [AttendanceController::class, 'update']);
    Route::delete('/attendance/{id}', [AttendanceController::class, 'destroy']);
    Route::post('/attendance/bulk', [AttendanceController::class, 'bulkStore']);

    // Leave Types
    Route::get('/leave-types', [LeaveTypeController::class, 'index']);
    Route::post('/leave-types', [LeaveTypeController::class, 'store']);
    Route::put('/leave-types/{id}', [LeaveTypeController::class, 'update']);
    Route::delete('/leave-types/{id}', [LeaveTypeController::class, 'destroy']);

    // Leave Requests
    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::put('/leave-requests/{id}', [LeaveRequestController::class, 'update']);
    Route::patch('/leave-requests/{id}/approve', [LeaveRequestController::class, 'approve']);
    Route::patch('/leave-requests/{id}/reject', [LeaveRequestController::class, 'reject']);
    Route::patch('/leave-requests/{id}/cancel', [LeaveRequestController::class, 'cancel']);
    Route::delete('/leave-requests/{id}', [LeaveRequestController::class, 'destroy']);
    Route::get('/leave-balances', [LeaveRequestController::class, 'balances']);
});
