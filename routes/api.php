<?php

use App\Http\Controllers\HR\ApplicantController;
use App\Http\Controllers\HR\AttendanceController;
use App\Http\Controllers\HR\DepartmentController;
use App\Http\Controllers\HR\DesignationController;
use App\Http\Controllers\HR\EmployeeController;
use App\Http\Controllers\HR\ExpenseController;
use App\Http\Controllers\HR\HrSettingsController;
use App\Http\Controllers\HR\JobOpeningController;
use App\Http\Controllers\HR\LeaveRequestController;
use App\Http\Controllers\HR\LeaveTypeController;
use App\Http\Controllers\HR\OnboardingController;
use App\Http\Controllers\HR\PayrollController;
use App\Http\Controllers\HR\PayslipController;
use App\Http\Controllers\HR\ReportController;
use App\Http\Controllers\HR\RoleController;
use App\Http\Controllers\HR\ShiftController;
use App\Http\Controllers\HRDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json([
    'ok' => true,
]));

Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('hr')->group(function () {
        Route::get('/employees/options', [EmployeeController::class, 'options']);
        Route::post('/employees/bulk-action', [EmployeeController::class, 'bulkAction']);
        Route::post('/employees/import', [EmployeeController::class, 'import']);
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::get('/employees/{id}', [EmployeeController::class, 'show']);
        Route::put('/employees/{id}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
        Route::patch('/employees/{id}/status', [EmployeeController::class, 'updateStatus']);
        Route::get('/employees/{id}/attendance', [EmployeeController::class, 'attendance']);
        Route::get('/employees/{id}/leave', [EmployeeController::class, 'leave']);
        Route::get('/employees/{id}/payroll', [EmployeeController::class, 'payroll']);
        Route::get('/employees/{id}/documents', [EmployeeController::class, 'documents']);
        Route::post('/employees/{id}/documents', [EmployeeController::class, 'uploadDocument']);
        Route::delete('/employees/{id}/documents/{docId}', [EmployeeController::class, 'deleteDocument']);
        Route::get('/employees/{id}/activity-log', [EmployeeController::class, 'activityLog']);

        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::get('/departments/{id}', [DepartmentController::class, 'show']);
        Route::put('/departments/{id}', [DepartmentController::class, 'update']);
        Route::patch('/departments/{id}/status', [DepartmentController::class, 'updateStatus']);
        Route::patch('/departments/{id}/manager', [DepartmentController::class, 'assignManager']);
        Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

        Route::get('/designations', [DesignationController::class, 'index']);
        Route::post('/designations', [DesignationController::class, 'store']);
        Route::get('/designations/{id}', [DesignationController::class, 'show']);
        Route::put('/designations/{id}', [DesignationController::class, 'update']);
        Route::patch('/designations/{id}/status', [DesignationController::class, 'updateStatus']);
        Route::delete('/designations/{id}', [DesignationController::class, 'destroy']);

        Route::get('/attendance/today', [AttendanceController::class, 'todayAttendance']);
        Route::post('/attendance/bulk', [AttendanceController::class, 'bulkMark']);
        Route::get('/attendance', [AttendanceController::class, 'index']);
        Route::post('/attendance', [AttendanceController::class, 'store']);
        Route::put('/attendance/{id}', [AttendanceController::class, 'update']);
        Route::delete('/attendance/{id}', [AttendanceController::class, 'destroy']);

        Route::get('/leave-types', [LeaveTypeController::class, 'index']);
        Route::post('/leave-types', [LeaveTypeController::class, 'store']);
        Route::put('/leave-types/{id}', [LeaveTypeController::class, 'update']);
        Route::delete('/leave-types/{id}', [LeaveTypeController::class, 'destroy']);

        Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
        Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
        Route::put('/leave-requests/{id}', [LeaveRequestController::class, 'update']);
        Route::patch('/leave-requests/{id}/approve', [LeaveRequestController::class, 'approve']);
        Route::patch('/leave-requests/{id}/reject', [LeaveRequestController::class, 'reject']);
        Route::patch('/leave-requests/{id}/cancel', [LeaveRequestController::class, 'cancel']);
        Route::delete('/leave-requests/{id}', [LeaveRequestController::class, 'destroy']);
        Route::get('/leave-balances', [LeaveRequestController::class, 'balances']);

        Route::get('/shifts', [ShiftController::class, 'shifts']);
        Route::post('/shifts', [ShiftController::class, 'storeShift']);
        Route::put('/shifts/{id}', [ShiftController::class, 'updateShift']);
        Route::delete('/shifts/{id}', [ShiftController::class, 'destroyShift']);
        Route::get('/shifts/list', [ShiftController::class, 'shifts']);
        Route::post('/shifts/create', [ShiftController::class, 'storeShift']);
        Route::put('/shifts/{id}/update', [ShiftController::class, 'updateShift']);
        Route::delete('/shifts/{id}/delete', [ShiftController::class, 'destroyShift']);

        Route::get('/shift-schedules', [ShiftController::class, 'index']);
        Route::get('/shift-schedules/weekly', [ShiftController::class, 'weeklyView']);
        Route::post('/shift-schedules', [ShiftController::class, 'assignShift']);
        Route::post('/shift-schedules/assign', [ShiftController::class, 'assignShift']);
        Route::post('/shift-schedules/bulk-assign', [ShiftController::class, 'bulkAssign']);
        Route::patch('/shift-schedules/{id}/end', [ShiftController::class, 'endSchedule']);
        Route::delete('/shift-schedules/{id}', [ShiftController::class, 'destroy']);

        Route::get('/job-openings', [JobOpeningController::class, 'index']);
        Route::post('/job-openings', [JobOpeningController::class, 'store']);
        Route::get('/job-openings/{id}', [JobOpeningController::class, 'show']);
        Route::put('/job-openings/{id}', [JobOpeningController::class, 'update']);
        Route::patch('/job-openings/{id}/status', [JobOpeningController::class, 'updateStatus']);
        Route::post('/job-openings/{id}/duplicate', [JobOpeningController::class, 'duplicate']);
        Route::delete('/job-openings/{id}', [JobOpeningController::class, 'destroy']);

        Route::get('/expenses', [ExpenseController::class, 'index']);
        Route::post('/expenses', [ExpenseController::class, 'store']);
        Route::patch('/expenses/{id}/approve', [ExpenseController::class, 'approve']);
        Route::patch('/expenses/{id}/reject', [ExpenseController::class, 'reject']);
        Route::patch('/expenses/{id}/paid', [ExpenseController::class, 'markPaid']);
        Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);

        // Backward-compatible alias.
        Route::patch('/expenses/{id}/mark-paid', [ExpenseController::class, 'markPaid']);

        Route::get('/applicants/pipeline', [ApplicantController::class, 'pipeline']);
        Route::get('/applicants', [ApplicantController::class, 'index']);
        Route::post('/applicants', [ApplicantController::class, 'store']);
        Route::get('/applicants/{id}', [ApplicantController::class, 'show']);
        Route::match(['put', 'post'], '/applicants/{id}', [ApplicantController::class, 'update']);
        Route::patch('/applicants/{id}/stage', [ApplicantController::class, 'moveStage']);
        Route::patch('/applicants/{id}/status', [ApplicantController::class, 'updateStatus']);
        Route::patch('/applicants/{id}/rating', [ApplicantController::class, 'updateRating']);
        Route::patch('/applicants/{id}/note', [ApplicantController::class, 'addNote']);
        Route::post('/applicants/{id}/convert', [ApplicantController::class, 'convertToEmployee']);
        Route::delete('/applicants/{id}', [ApplicantController::class, 'destroy']);

        Route::get('/onboarding-templates', [OnboardingController::class, 'templates']);
        Route::post('/onboarding-templates', [OnboardingController::class, 'storeTemplate']);
        Route::put('/onboarding-templates/{id}', [OnboardingController::class, 'updateTemplate']);
        Route::delete('/onboarding-templates/{id}', [OnboardingController::class, 'destroyTemplate']);

        Route::get('/onboardings/board', [OnboardingController::class, 'boardView']);
        Route::get('/onboardings', [OnboardingController::class, 'index']);
        Route::post('/onboardings', [OnboardingController::class, 'store']);
        Route::get('/onboardings/{id}', [OnboardingController::class, 'show']);
        Route::delete('/onboardings/{id}', [OnboardingController::class, 'destroy']);
        Route::patch('/onboardings/{id}/buddy', [OnboardingController::class, 'assignBuddy']);
        Route::patch('/onboardings/{onboardingId}/tasks/{taskId}', [OnboardingController::class, 'updateTask']);

        // Backward-compatible aliases for legacy frontend paths.
        Route::get('/onboarding', [OnboardingController::class, 'index']);
        Route::post('/onboarding', [OnboardingController::class, 'store']);
        Route::get('/onboarding/{id}', [OnboardingController::class, 'show']);
        Route::delete('/onboarding/{id}', [OnboardingController::class, 'destroy']);
        Route::patch('/onboarding/{id}/tasks/{taskId}', [OnboardingController::class, 'updateTask']);

        Route::get('/salary-structures', [PayrollController::class, 'salaryStructures']);
        Route::post('/salary-structures', [PayrollController::class, 'storeSalaryStructure']);
        Route::put('/salary-structures/{id}', [PayrollController::class, 'updateSalaryStructure']);
        Route::delete('/salary-structures/{id}', [PayrollController::class, 'destroySalaryStructure']);

        Route::get('/payslips', [PayrollController::class, 'allPayslips']);
        Route::get('/payslips/{id}', [PayslipController::class, 'show']);
        Route::put('/payslips/{id}', [PayslipController::class, 'update']);

        Route::get('/payroll-runs', [PayrollController::class, 'index']);
        Route::post('/payroll-runs/generate', [PayrollController::class, 'generateRun']);
        Route::patch('/payroll-runs/{id}/approve', [PayrollController::class, 'approve']);
        Route::patch('/payroll-runs/{id}/paid', [PayrollController::class, 'markPaid']);
        Route::patch('/payroll-runs/{id}/cancel', [PayrollController::class, 'cancel']);
        Route::get('/payroll-runs/{id}/payslips', [PayrollController::class, 'payslips']);

        // Backward-compatible aliases for previous payroll paths.
        Route::get('/payroll', [PayrollController::class, 'index']);
        Route::post('/payroll', [PayrollController::class, 'generateRun']);
        Route::patch('/payroll/{id}/approve', [PayrollController::class, 'approve']);
        Route::patch('/payroll/{id}/mark-paid', [PayrollController::class, 'markPaid']);
        Route::patch('/payroll/{id}/cancel', [PayrollController::class, 'cancel']);
        Route::get('/payroll/{id}/payslips', [PayrollController::class, 'payslips']);

        Route::get('/settings', [HrSettingsController::class, 'index']);
        Route::post('/settings/company', [HrSettingsController::class, 'saveCompany']);
        Route::post('/settings/payroll', [HrSettingsController::class, 'savePayroll']);
        Route::post('/settings/leave', [HrSettingsController::class, 'saveLeave']);
        Route::post('/settings/attendance', [HrSettingsController::class, 'saveAttendance']);
        Route::post('/settings/recruitment', [HrSettingsController::class, 'saveRecruitment']);

        Route::get('/reports/workforce', [ReportController::class, 'workforce']);
        Route::get('/reports/attendance', [ReportController::class, 'attendance']);
        Route::get('/reports/leave', [ReportController::class, 'leave']);
        Route::get('/reports/payroll', [ReportController::class, 'payroll']);
        Route::get('/reports/recruitment', [ReportController::class, 'recruitment']);
        Route::get('/reports/expenses', [ReportController::class, 'expenses']);

        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
        Route::get('/roles/users', [RoleController::class, 'userRoles']);
        Route::post('/roles/assign', [RoleController::class, 'assignRole']);
    });
});
