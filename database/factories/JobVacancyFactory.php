<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Models\JobCategory;
use App\Models\JobVacancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobVacancy>
 */
class JobVacancyFactory extends Factory
{
    /**
     * Bawaan: lowongan published dengan deadline di masa depan dan formulir default kategorinya.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->jobTitle(),
            'job_category_id' => JobCategory::factory(),
            'department' => 'Engineering',
            'location' => fake()->city(),
            'employment_type' => 'Full-time',
            'description' => fake()->paragraph(),
            'qualifications' => fake()->paragraph(),
            'deadline' => today()->addMonth(),
            'status' => JobStatus::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => JobStatus::Draft]);
    }

    public function closed(): static
    {
        return $this->state(['status' => JobStatus::Closed]);
    }

    public function expired(): static
    {
        return $this->state(['deadline' => today()->subDay()]);
    }
}
