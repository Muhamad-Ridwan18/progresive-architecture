<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Support\ApplicationForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();
        $email = fake()->unique()->safeEmail();

        return [
            'job_vacancy_id' => JobVacancy::factory(),
            'applicant_name' => $name,
            'applicant_email' => $email,
            'applicant_phone' => '0812000'.fake()->numerify('####'),
            'status' => ApplicationStatus::Submitted,
            'answers' => ['full_name' => $name, 'email' => $email],
            'form_snapshot' => ApplicationForm::defaultFields(),
            'submitted_at' => now(),
        ];
    }
}
