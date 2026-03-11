<?php

namespace Database\Seeders;

use App\Models\HR\HrSetting;
use Illuminate\Database\Seeder;

class HRSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'company_name', 'value' => 'Wilson Labs', 'group' => 'company'],
            ['key' => 'company_phone', 'value' => '+233 20 000 0000', 'group' => 'company'],
            ['key' => 'company_address', 'value' => 'Accra, Ghana', 'group' => 'company'],
            ['key' => 'hr_email', 'value' => 'hr@wilsonlabs.com', 'group' => 'company'],
            ['key' => 'default_currency', 'value' => 'GHS', 'group' => 'company'],
            ['key' => 'timezone', 'value' => 'Africa/Accra', 'group' => 'company'],
            ['key' => 'fiscal_year_start', 'value' => 'January', 'group' => 'company'],

            ['key' => 'pay_cycle', 'value' => 'Monthly', 'group' => 'payroll'],
            ['key' => 'pay_day', 'value' => '28', 'group' => 'payroll'],
            ['key' => 'ssnit_employee_rate', 'value' => '5.5', 'group' => 'payroll'],
            ['key' => 'ssnit_employer_rate', 'value' => '13.0', 'group' => 'payroll'],
            ['key' => 'overtime_rate', 'value' => '1.5', 'group' => 'payroll'],
            ['key' => 'payroll_approval_required', 'value' => '1', 'group' => 'payroll'],

            ['key' => 'leave_carry_forward', 'value' => '1', 'group' => 'leave'],
            ['key' => 'leave_approval_levels', 'value' => '1', 'group' => 'leave'],
            ['key' => 'max_carry_forward_days', 'value' => '5', 'group' => 'leave'],
            ['key' => 'leave_accrual', 'value' => 'Annual', 'group' => 'leave'],
            ['key' => 'leave_calendar_public', 'value' => '0', 'group' => 'leave'],
            ['key' => 'notify_on_leave_request', 'value' => '1', 'group' => 'leave'],
            ['key' => 'auto_reject_after_days', 'value' => '14', 'group' => 'leave'],

            ['key' => 'work_start_time', 'value' => '08:00', 'group' => 'attendance'],
            ['key' => 'work_end_time', 'value' => '17:00', 'group' => 'attendance'],
            ['key' => 'work_days', 'value' => 'Mon,Tue,Wed,Thu,Fri', 'group' => 'attendance'],
            ['key' => 'lateness_threshold_mins', 'value' => '15', 'group' => 'attendance'],
            ['key' => 'overtime_threshold_mins', 'value' => '30', 'group' => 'attendance'],
            ['key' => 'allow_remote_checkin', 'value' => '0', 'group' => 'attendance'],
            ['key' => 'attendance_geofencing', 'value' => '0', 'group' => 'attendance'],
            ['key' => 'working_hours_per_day', 'value' => '8', 'group' => 'attendance'],

            ['key' => 'max_resume_size_mb', 'value' => '5', 'group' => 'recruitment'],
            ['key' => 'allowed_resume_formats', 'value' => 'pdf,doc,docx', 'group' => 'recruitment'],
            ['key' => 'auto_reject_days', 'value' => '30', 'group' => 'recruitment'],
            ['key' => 'careers_page_enabled', 'value' => '1', 'group' => 'recruitment'],
            ['key' => 'notify_on_application', 'value' => '1', 'group' => 'recruitment'],
            ['key' => 'default_pipeline_stages', 'value' => 'Applied,Screening,Interview,Offer,Hired', 'group' => 'recruitment'],
        ];

        foreach ($settings as $setting) {
            HrSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'group' => $setting['group']]
            );
        }
    }
}
