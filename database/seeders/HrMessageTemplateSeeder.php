<?php

namespace Database\Seeders;

use App\Models\HR\HrMessageTemplate;
use Illuminate\Database\Seeder;

class HrMessageTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Interview Invitation',
                'category' => 'recruitment',
                'subject' => 'Interview Invitation - {{position}} at {{company}}',
                'body' => "Dear {{name}},\n\nWe are pleased to invite you for an interview for the {{position}} role at {{company}}.\n\nDate: {{date}}\nTime: {{time}}\nLocation: {{location}}\n\nPlease confirm your attendance by replying to this email.\n\nBest regards,\n{{hr_name}}\n{{company}} HR Team",
                'variables' => ['name', 'position', 'company', 'date', 'time', 'location', 'hr_name'],
            ],
            [
                'name' => 'Application Acknowledgement',
                'category' => 'recruitment',
                'subject' => 'We received your application - {{position}}',
                'body' => "Dear {{name}},\n\nThank you for applying for the {{position}} position at {{company}}.\n\nWe have received your application and will review it shortly. We will be in touch if your profile matches our requirements.\n\nBest regards,\n{{company}} HR Team",
                'variables' => ['name', 'position', 'company'],
            ],
            [
                'name' => 'Job Offer Letter',
                'category' => 'recruitment',
                'subject' => 'Job Offer - {{position}} at {{company}}',
                'body' => "Dear {{name}},\n\nWe are delighted to offer you the position of {{position}} at {{company}}.\n\nStart Date:  {{start_date}}\nSalary:      {{salary}}/month\nDepartment:  {{department}}\n\nPlease sign and return this offer within 5 business days.\n\nCongratulations!\n\n{{hr_name}}\n{{company}} HR",
                'variables' => ['name', 'position', 'company', 'start_date', 'salary', 'department', 'hr_name'],
            ],
            [
                'name' => 'Application Unsuccessful',
                'category' => 'recruitment',
                'subject' => 'Your Application - {{company}}',
                'body' => "Dear {{name}},\n\nThank you for your interest in the {{position}} role at {{company}}.\n\nAfter careful consideration, we have decided to move forward with other candidates whose experience more closely matches our current needs.\n\nWe appreciate the time you invested and wish you all the best in your job search.\n\nBest regards,\n{{company}} HR Team",
                'variables' => ['name', 'position', 'company'],
            ],
            [
                'name' => 'Welcome to the Team',
                'category' => 'onboarding',
                'subject' => 'Welcome to {{company}}, {{name}}!',
                'body' => "Dear {{name}},\n\nWelcome to {{company}}! We are thrilled to have you join us as {{position}} in the {{department}} department.\n\nYour first day: {{start_date}}\nReport to:     {{location}}\nTime:          {{time}}\nYour manager:  {{manager}}\n\nWe look forward to working with you!\n\n{{hr_name}}\nHR Team",
                'variables' => ['name', 'company', 'position', 'department', 'start_date', 'location', 'time', 'manager', 'hr_name'],
            ],
            [
                'name' => 'First Day Reminder',
                'category' => 'onboarding',
                'subject' => 'Reminder: Your first day is tomorrow!',
                'body' => "Dear {{name}},\n\nJust a friendly reminder that your first day at {{company}} is tomorrow, {{start_date}}.\n\nPlease arrive at {{location}} by {{time}}.\n\nIf you have any questions, feel free to reach out.\n\nSee you tomorrow!\n\n{{hr_name}}\nHR Team",
                'variables' => ['name', 'company', 'start_date', 'location', 'time', 'hr_name'],
            ],
            [
                'name' => 'Leave Approved',
                'category' => 'leave',
                'subject' => 'Your Leave Request - Approved',
                'body' => "Dear {{name}},\n\nYour leave request has been approved.\n\nLeave Type: {{leave_type}}\nFrom:       {{from_date}}\nTo:         {{to_date}}\nDuration:   {{days}} day(s)\n\nPlease ensure your work is handed over before your leave begins.\n\nHR Team",
                'variables' => ['name', 'leave_type', 'from_date', 'to_date', 'days'],
            ],
            [
                'name' => 'Leave Rejected',
                'category' => 'leave',
                'subject' => 'Your Leave Request - Not Approved',
                'body' => "Dear {{name}},\n\nWe regret to inform you that your leave request has not been approved at this time.\n\nLeave Type: {{leave_type}}\nFrom:       {{from_date}}\nTo:         {{to_date}}\nReason:     {{reason}}\n\nPlease speak to your manager if you have questions.\n\nHR Team",
                'variables' => ['name', 'leave_type', 'from_date', 'to_date', 'reason'],
            ],
            [
                'name' => 'Payslip Ready',
                'category' => 'payroll',
                'subject' => 'Your Payslip for {{month}} is Ready',
                'body' => "Dear {{name}},\n\nYour payslip for {{month}} {{year}} is now available.\n\nGross Pay:   {{gross}}\nDeductions:  {{deductions}}\nNet Pay:     {{net}}\n\nLog in to the HR portal to view and download your payslip.\n\nHR & Payroll Team",
                'variables' => ['name', 'month', 'year', 'gross', 'deductions', 'net'],
            ],
            [
                'name' => 'General Notice',
                'category' => 'general',
                'subject' => '{{subject}}',
                'body' => "Dear {{name}},\n\n{{message}}\n\nBest regards,\n{{hr_name}}\nHR Team",
                'variables' => ['name', 'subject', 'message', 'hr_name'],
            ],
            [
                'name' => 'Performance Review Reminder',
                'category' => 'general',
                'subject' => 'Upcoming Performance Review - {{date}}',
                'body' => "Dear {{name}},\n\nThis is a reminder that your performance review is scheduled for {{date}} at {{time}}.\n\nPlease prepare a summary of your achievements and goals for the discussion.\n\nBest regards,\n{{hr_name}}\nHR Team",
                'variables' => ['name', 'date', 'time', 'hr_name'],
            ],
        ];

        foreach ($templates as $template) {
            HrMessageTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template
            );
        }
    }
}
