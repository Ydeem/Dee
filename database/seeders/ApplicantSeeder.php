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
            return;
        }

        $statuses = ['New', 'New', 'Reviewing', 'Shortlisted', 'Interview Scheduled', 'Interviewed', 'Offer Sent', 'Hired', 'Rejected'];
        $sources = ['LinkedIn', 'Website', 'Referral', 'Job Board', 'Walk-in'];
        $names = [
            ['Kofi', 'Boateng'], ['Ama', 'Mensah'], ['Kweku', 'Asante'], ['Abena', 'Owusu'],
            ['Yaw', 'Darko'], ['Akosua', 'Frimpong'], ['Nana', 'Adjei'], ['Kwame', 'Tetteh'],
            ['Efua', 'Amponsah'], ['Kojo', 'Sarpong'], ['Adwoa', 'Osei'], ['Fiifi', 'Ankrah'],
        ];
        $cities = ['Accra', 'Kumasi', 'Takoradi', 'Tamale', 'Cape Coast'];
        $edu = ['HND', 'Bachelors', 'Masters'];
        $notices = ['Immediate', '2 weeks', '1 month'];

        foreach ($jobs as $job) {
            $count = rand(3, 6);
            for ($i = 0; $i < $count; $i++) {
                $name = $names[array_rand($names)];
                $status = $statuses[array_rand($statuses)];
                $stage = match (true) {
                    in_array($status, ['New', 'Reviewing'], true) => 1,
                    $status === 'Shortlisted' => 2,
                    in_array($status, ['Interview Scheduled', 'Interviewed'], true) => 3,
                    $status === 'Offer Sent' => 4,
                    in_array($status, ['Hired', 'Rejected', 'Withdrawn'], true) => 5,
                    default => 1,
                };

                Applicant::create([
                    'job_opening_id' => $job->id,
                    'first_name' => $name[0],
                    'last_name' => $name[1],
                    'email' => strtolower($name[0] . '.' . $name[1] . '.' . rand(1, 99) . '@email.com'),
                    'phone' => '055' . rand(1000000, 9999999),
                    'location' => $cities[array_rand($cities)],
                    'experience_years' => rand(0, 10),
                    'education_level' => $edu[array_rand($edu)],
                    'source' => $sources[array_rand($sources)],
                    'status' => $status,
                    'stage' => $stage,
                    'rating' => rand(1, 5),
                    'expected_salary' => rand(2000, 10000),
                    'notice_period' => $notices[array_rand($notices)],
                ]);
            }
        }
    }
}

