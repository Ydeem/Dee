<?php

namespace Database\Seeders;

use App\Models\HR\Department;
use App\Models\HR\JobOpening;
use Illuminate\Database\Seeder;

class JobOpeningSeeder extends Seeder
{
    public function run(): void
    {
        $jobs = [
            [
                'title' => 'Senior Software Engineer',
                'employment_type' => 'Full-time',
                'location' => 'Accra, Ghana',
                'vacancies' => 2,
                'salary_from' => 5000,
                'salary_to' => 8000,
                'salary_currency' => 'GHS',
                'experience_years' => 4,
                'education_level' => 'Bachelors',
                'status' => 'Open',
                'deadline' => now()->addDays(30)->toDateString(),
                'description' => 'We are looking for a skilled engineer...',
                'requirements' => '4+ years experience with Laravel and Vue',
            ],
            [
                'title' => 'HR Officer',
                'employment_type' => 'Full-time',
                'location' => 'Accra, Ghana',
                'vacancies' => 1,
                'salary_from' => 3000,
                'salary_to' => 4500,
                'salary_currency' => 'GHS',
                'experience_years' => 2,
                'education_level' => 'Bachelors',
                'status' => 'Open',
                'deadline' => now()->addDays(21)->toDateString(),
            ],
            [
                'title' => 'Marketing Manager',
                'employment_type' => 'Full-time',
                'location' => 'Remote',
                'vacancies' => 1,
                'salary_from' => 6000,
                'salary_to' => 9000,
                'salary_currency' => 'GHS',
                'experience_years' => 5,
                'education_level' => 'Masters',
                'status' => 'Open',
                'deadline' => now()->addDays(14)->toDateString(),
            ],
            [
                'title' => 'Finance Analyst',
                'employment_type' => 'Contract',
                'location' => 'Kumasi, Ghana',
                'vacancies' => 3,
                'status' => 'Draft',
            ],
            [
                'title' => 'Customer Support Agent',
                'employment_type' => 'Full-time',
                'location' => 'Accra, Ghana',
                'vacancies' => 5,
                'salary_from' => 2000,
                'salary_to' => 3000,
                'salary_currency' => 'GHS',
                'status' => 'Closed',
                'deadline' => now()->subDays(5)->toDateString(),
            ],
            [
                'title' => 'IT Intern',
                'employment_type' => 'Intern',
                'location' => 'Accra, Ghana',
                'vacancies' => 2,
                'salary_from' => 800,
                'salary_to' => 1200,
                'salary_currency' => 'GHS',
                'education_level' => 'HND',
                'status' => 'Open',
                'deadline' => now()->addDays(45)->toDateString(),
            ],
        ];

        $departments = Department::pluck('id', 'name');
        $defaultDepartmentId = $departments['Engineering'] ?? $departments->first();

        foreach ($jobs as $jobData) {
            JobOpening::firstOrCreate(
                ['title' => $jobData['title']],
                array_merge($jobData, [
                    'department_id' => $defaultDepartmentId,
                ])
            );
        }
    }
}

