<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrSetting;
use Illuminate\Http\Request;

class HrSettingsController extends Controller
{
    private array $defaults = [
        'company_name' => 'My Company',
        'company_phone' => '',
        'company_address' => '',
        'hr_email' => '',
        'default_currency' => 'GHS',
        'timezone' => 'Africa/Accra',
        'fiscal_year_start' => 'January',
        'company_logo' => null,

        'pay_cycle' => 'Monthly',
        'pay_day' => '28',
        'ssnit_employee_rate' => '5.5',
        'ssnit_employer_rate' => '13.0',
        'overtime_rate' => '1.5',
        'payslip_template' => 'Standard',
        'payroll_approval_required' => '1',

        'leave_approval_levels' => '1',
        'leave_carry_forward' => '1',
        'max_carry_forward_days' => '5',
        'leave_accrual' => 'Annual',
        'leave_calendar_public' => '0',
        'notify_on_leave_request' => '1',
        'auto_reject_after_days' => '14',

        'work_start_time' => '08:00',
        'work_end_time' => '17:00',
        'work_days' => 'Mon,Tue,Wed,Thu,Fri',
        'lateness_threshold_mins' => '15',
        'overtime_threshold_mins' => '30',
        'allow_remote_checkin' => '0',
        'attendance_geofencing' => '0',
        'working_hours_per_day' => '8',

        'max_resume_size_mb' => '5',
        'allowed_resume_formats' => 'pdf,doc,docx',
        'auto_reject_days' => '30',
        'careers_page_enabled' => '1',
        'notify_on_application' => '1',
        'default_pipeline_stages' => 'Applied,Screening,Interview,Offer,Hired',
    ];

    public function index()
    {
        $this->authorizeAdmin();

        $stored = HrSetting::getAllSettings();
        $merged = array_merge($this->defaults, $stored);

        return response()->json([
            'settings' => [
                'company' => [
                    'company_name' => $merged['company_name'],
                    'company_phone' => $merged['company_phone'],
                    'company_address' => $merged['company_address'],
                    'hr_email' => $merged['hr_email'],
                    'default_currency' => $merged['default_currency'],
                    'timezone' => $merged['timezone'],
                    'fiscal_year_start' => $merged['fiscal_year_start'],
                    'company_logo' => $merged['company_logo'],
                ],
                'payroll' => [
                    'pay_cycle' => $merged['pay_cycle'],
                    'pay_day' => $merged['pay_day'],
                    'ssnit_employee_rate' => $merged['ssnit_employee_rate'],
                    'ssnit_employer_rate' => $merged['ssnit_employer_rate'],
                    'overtime_rate' => $merged['overtime_rate'],
                    'payslip_template' => $merged['payslip_template'],
                    'payroll_approval_required' => (bool) $merged['payroll_approval_required'],
                ],
                'leave' => [
                    'leave_approval_levels' => $merged['leave_approval_levels'],
                    'leave_carry_forward' => (bool) $merged['leave_carry_forward'],
                    'max_carry_forward_days' => $merged['max_carry_forward_days'],
                    'leave_accrual' => $merged['leave_accrual'],
                    'leave_calendar_public' => (bool) $merged['leave_calendar_public'],
                    'notify_on_leave_request' => (bool) $merged['notify_on_leave_request'],
                    'auto_reject_after_days' => $merged['auto_reject_after_days'],
                ],
                'attendance' => [
                    'work_start_time' => $merged['work_start_time'],
                    'work_end_time' => $merged['work_end_time'],
                    'work_days' => explode(',', $merged['work_days']),
                    'lateness_threshold_mins' => $merged['lateness_threshold_mins'],
                    'overtime_threshold_mins' => $merged['overtime_threshold_mins'],
                    'allow_remote_checkin' => (bool) $merged['allow_remote_checkin'],
                    'attendance_geofencing' => (bool) $merged['attendance_geofencing'],
                    'working_hours_per_day' => $merged['working_hours_per_day'],
                ],
                'recruitment' => [
                    'max_resume_size_mb' => $merged['max_resume_size_mb'],
                    'allowed_resume_formats' => $merged['allowed_resume_formats'],
                    'auto_reject_days' => $merged['auto_reject_days'],
                    'careers_page_enabled' => (bool) $merged['careers_page_enabled'],
                    'notify_on_application' => (bool) $merged['notify_on_application'],
                    'default_pipeline_stages' => $merged['default_pipeline_stages'],
                ],
            ],
        ]);
    }

    public function saveCompany(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'company_name' => 'required|string|max:200',
            'hr_email' => 'nullable|email',
            'company_phone' => 'nullable|string',
            'company_address' => 'nullable|string',
            'default_currency' => 'required|in:GHS,USD,EUR,GBP,NGN',
            'timezone' => 'required|string',
            'fiscal_year_start' => 'required|in:January,February,March,April,May,June,July,August,September,October,November,December',
        ]);

        HrSetting::setMany([
            'company_name' => $request->company_name,
            'hr_email' => $request->hr_email,
            'company_phone' => $request->company_phone,
            'company_address' => $request->company_address,
            'default_currency' => $request->default_currency,
            'timezone' => $request->timezone,
            'fiscal_year_start' => $request->fiscal_year_start,
        ], 'company');

        return response()->json([
            'message' => 'Company settings saved.',
        ]);
    }

    public function savePayroll(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'pay_cycle' => 'required|in:Weekly,Bi-weekly,Monthly',
            'pay_day' => 'required|integer|between:1,31',
            'ssnit_employee_rate' => 'required|numeric|between:0,100',
            'ssnit_employer_rate' => 'required|numeric|between:0,100',
            'overtime_rate' => 'required|numeric|min:1',
        ]);

        HrSetting::setMany([
            'pay_cycle' => $request->pay_cycle,
            'pay_day' => $request->pay_day,
            'ssnit_employee_rate' => $request->ssnit_employee_rate,
            'ssnit_employer_rate' => $request->ssnit_employer_rate,
            'overtime_rate' => $request->overtime_rate,
            'payslip_template' => $request->payslip_template ?? 'Standard',
            'payroll_approval_required' => $request->payroll_approval_required ? '1' : '0',
        ], 'payroll');

        return response()->json([
            'message' => 'Payroll settings saved.',
        ]);
    }

    public function saveLeave(Request $request)
    {
        $this->authorizeAdmin();

        HrSetting::setMany([
            'leave_approval_levels' => $request->leave_approval_levels ?? '1',
            'leave_carry_forward' => $request->leave_carry_forward ? '1' : '0',
            'max_carry_forward_days' => $request->max_carry_forward_days ?? '5',
            'leave_accrual' => $request->leave_accrual ?? 'Annual',
            'leave_calendar_public' => $request->leave_calendar_public ? '1' : '0',
            'notify_on_leave_request' => $request->notify_on_leave_request ? '1' : '0',
            'auto_reject_after_days' => $request->auto_reject_after_days ?? '14',
        ], 'leave');

        return response()->json([
            'message' => 'Leave policy settings saved.',
        ]);
    }

    public function saveAttendance(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'work_start_time' => 'required|date_format:H:i',
            'work_end_time' => 'required|date_format:H:i',
            'work_days' => 'required|array|min:1',
        ]);

        HrSetting::setMany([
            'work_start_time' => $request->work_start_time,
            'work_end_time' => $request->work_end_time,
            'work_days' => implode(',', $request->work_days),
            'lateness_threshold_mins' => $request->lateness_threshold_mins ?? '15',
            'overtime_threshold_mins' => $request->overtime_threshold_mins ?? '30',
            'allow_remote_checkin' => $request->allow_remote_checkin ? '1' : '0',
            'attendance_geofencing' => $request->attendance_geofencing ? '1' : '0',
            'working_hours_per_day' => $request->working_hours_per_day ?? '8',
        ], 'attendance');

        return response()->json([
            'message' => 'Attendance rules saved.',
        ]);
    }

    public function saveRecruitment(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'max_resume_size_mb' => 'required|integer|min:1|max:20',
        ]);

        HrSetting::setMany([
            'max_resume_size_mb' => $request->max_resume_size_mb,
            'allowed_resume_formats' => $request->allowed_resume_formats ?? 'pdf,doc,docx',
            'auto_reject_days' => $request->auto_reject_days ?? '30',
            'careers_page_enabled' => $request->careers_page_enabled ? '1' : '0',
            'notify_on_application' => $request->notify_on_application ? '1' : '0',
            'default_pipeline_stages' => $request->default_pipeline_stages ?? 'Applied,Screening,Interview,Offer,Hired',
        ], 'recruitment');

        return response()->json([
            'message' => 'Recruitment settings saved.',
        ]);
    }

    /**
     * Backward-compatible save endpoint for legacy UI that submits:
     * { settings: { key: value, ... } }
     */
    public function saveLegacy(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($request->input('settings', []) as $key => $value) {
            $existing = HrSetting::query()->where('key', $key)->first();
            $group = $existing?->group ?? $this->inferGroupFromKey((string) $key);

            HrSetting::updateOrCreate(
                ['key' => (string) $key],
                [
                    'value' => is_null($value) ? null : (string) $value,
                    'group' => $group,
                ]
            );
        }

        return response()->json([
            'message' => 'Settings saved successfully.',
        ]);
    }

    private function inferGroupFromKey(string $key): string
    {
        if (str_starts_with($key, 'company_') || in_array($key, [
            'hr_email', 'default_currency', 'timezone', 'fiscal_year_start',
        ], true)) {
            return 'company';
        }

        if (str_starts_with($key, 'pay_') || str_starts_with($key, 'ssnit_') || in_array($key, [
            'overtime_rate', 'payslip_template', 'payroll_approval_required', 'payroll_day', 'enable_overtime',
            'payslip_footer',
        ], true)) {
            return 'payroll';
        }

        if (str_starts_with($key, 'leave_') || in_array($key, [
            'auto_reject_after_days', 'allow_leave_carry_forward', 'leave_year_start', 'max_consecutive_leave',
            'auto_approve_leave',
        ], true)) {
            return 'leave';
        }

        if (str_starts_with($key, 'work_') || str_contains($key, 'attendance') || in_array($key, [
            'late_threshold_mins', 'lateness_threshold_mins', 'overtime_threshold_mins', 'enable_geolocation',
            'weekend_days', 'working_hours_per_day', 'allow_remote_checkin', 'attendance_geofencing',
        ], true)) {
            return 'attendance';
        }

        if (str_contains($key, 'resume') || str_contains($key, 'pipeline') || in_array($key, [
            'careers_page_enabled', 'notify_on_application', 'auto_reject_days', 'require_cover_letter',
        ], true)) {
            return 'recruitment';
        }

        return 'general';
    }

    private function authorizeAdmin(): void
    {
        abort_if(! $this->isAdmin(), 403, 'Only HR Admin can manage HR settings.');
    }
}
