<?php

namespace Database\Seeders;

use App\Models\HR\Department;
use App\Models\HR\Designation;
use App\Models\HR\JobOpening;
use Illuminate\Database\Seeder;

class JobOpeningSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::all()->keyBy('name');
        $designations = Designation::all()->keyBy('name');

        $jobs = [
            [
                'title' => 'Senior Software Engineer',
                'department' => 'Engineering',
                'designation' => 'Senior Engineer',
                'employment_type' => 'Full-time',
                'vacancies' => 2,
                'min_salary' => 8000,
                'max_salary' => 15000,
                'location' => 'Accra, Ghana',
                'deadline' => now()->addDays(30),
                'status' => 'Open',
                'description' => 'We are looking for a Senior Software Engineer to join our growing engineering team.',
                'requirements' => '5+ years experience in Laravel or similar frameworks. Strong knowledge of Vue.js.',
            ],
            [
                'title' => 'HR Officer',
                'department' => 'Human Resources',
                'designation' => 'HR Officer',
                'employment_type' => 'Full-time',
                'vacancies' => 1,
                'min_salary' => 3000,
                'max_salary' => 5000,
                'location' => 'Accra, Ghana',
                'deadline' => now()->addDays(21),
                'status' => 'Open',
                'description' => 'Seeking an HR Officer to support day-to-day HR operations.',
                'requirements' => '2+ years HR experience. Knowledge of Ghana labor law.',
            ],
            [
                'title' => 'Marketing Manager',
                'department' => 'Marketing',
                'designation' => 'Marketing Manager',
                'employment_type' => 'Full-time',
                'vacancies' => 1,
                'min_salary' => 7000,
                'max_salary' => 12000,
                'location' => 'Accra, Ghana',
                'deadline' => now()->addDays(14),
                'status' => 'Open',
                'description' => 'We need a creative Marketing Manager to lead campaigns.',
                'requirements' => '4+ years marketing experience. Digital marketing skills.',
            ],
            [
                'title' => 'Finance Analyst',
                'department' => 'Finance',
                'designation' => 'Finance Officer',
                'employment_type' => 'Contract',
                'vacancies' => 2,
                'min_salary' => null,
                'max_salary' => null,
                'location' => 'Remote',
                'deadline' => null,
                'status' => 'Draft',
                'description' => 'Looking for a Finance Analyst for a 6-month contract.',
                'requirements' => 'ACCA or ICAG qualified. Excel expertise required.',
            ],
            [
                'title' => 'Customer Support Agent',
                'department' => 'Customer Support',
                'designation' => 'Support Agent',
                'employment_type' => 'Full-time',
                'vacancies' => 3,
                'min_salary' => 2000,
                'max_salary' => 3500,
                'location' => 'Accra, Ghana',
                'deadline' => now()->subDays(5),
                'status' => 'Closed',
                'description' => 'Join our customer support team.',
                'requirements' => 'Excellent communication skills. CRM experience preferred.',
            ],
            [
                'title' => 'IT Intern',
                'department' => 'Engineering',
                'designation' => 'Junior Developer',
                'employment_type' => 'Intern',
                'vacancies' => 2,
                'min_salary' => 800,
                'max_salary' => 1500,
                'location' => 'Accra, Ghana',
                'deadline' => now()->addDays(45),
                'status' => 'Open',
                'description' => 'Internship opportunity for fresh graduates.',
                'requirements' => 'Computer Science degree. Basic programming knowledge.',
            ],
        ];

        foreach ($jobs as $jobData) {
            $department = $departments[$jobData['department']] ?? null;
            $designation = $designations[$jobData['designation']] ?? null;

            JobOpening::create([
                'title' => $jobData['title'],
                'department_id' => $department?->id,
                'designation_id' => $designation?->id,
                'employment_type' => $jobData['employment_type'],
                'vacancies' => $jobData['vacancies'],
                'min_salary' => $jobData['min_salary'],
                'max_salary' => $jobData['max_salary'],
                'location' => $jobData['location'],
                'deadline' => $jobData['deadline'],
                'status' => $jobData['status'],
                'description' => $jobData['description'],
                'requirements' => $jobData['requirements'],
            ]);
        }
    }
}
