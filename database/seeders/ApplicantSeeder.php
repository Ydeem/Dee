<?php

namespace Database\Seeders;

use App\Models\HR\Applicant;
use App\Models\HR\JobOpening;
use Illuminate\Database\Seeder;

class ApplicantSeeder extends Seeder
{
    public function run(): void
    {
        $jobs = JobOpening::all();
        if ($jobs->isEmpty()) {
            echo 'No job openings found. Run JobOpeningSeeder first.';
            return;
        }

        $sources = ['Website', 'LinkedIn', 'Referral', 'Job Board', 'Walk-in'];
        $firstNames = ['Kofi', 'Ama', 'Kweku', 'Abena', 'Yaw', 'Akosua', 'Kwame', 'Adwoa', 'Nana', 'Fiifi', 'Efua', 'Kojo'];
        $lastNames = ['Mensah', 'Asante', 'Boateng', 'Owusu', 'Darko', 'Osei', 'Adjei', 'Frimpong', 'Acheampong', 'Amoah', 'Appiah', 'Nyarko'];

        $statusByStage = [
            1 => ['New', 'Reviewing'],
            2 => ['Reviewing', 'Shortlisted'],
            3 => ['Interview Scheduled', 'Interviewed'],
            4 => ['Offer Sent'],
            5 => ['Hired'],
        ];

        foreach ($jobs as $job) {
            $count = rand(3, 8);
            for ($i = 0; $i < $count; $i++) {
                $stage = rand(1, 5);
                $statuses = $statusByStage[$stage];
                $status = $statuses[array_rand($statuses)];

                Applicant::create([
                    'job_opening_id' => $job->id,
                    'first_name' => $firstNames[array_rand($firstNames)],
                    'last_name' => $lastNames[array_rand($lastNames)],
                    'email' => strtolower($firstNames[array_rand($firstNames)] . '.' . rand(10, 99) . '@email.com'),
                    'phone' => '05' . rand(10000000, 99999999),
                    'source' => $sources[array_rand($sources)],
                    'experience_years' => rand(0, 10),
                    'current_company' => 'Company ' . rand(1, 20),
                    'expected_salary' => rand(3, 15) * 1000,
                    'stage' => $stage,
                    'status' => $status,
                    'rating' => rand(2, 5),
                    'created_at' => now()->subDays(rand(1, 60)),
                    'updated_at' => now(),
                ]);
            }
        }

        echo 'Applicants seeded.';
    }
}
