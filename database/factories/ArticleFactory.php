<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Enums\CategoryType;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Bawaan: artikel Published, draft sama dengan versi published (tanpa perubahan).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'slug' => fn (array $attributes): string => str($attributes['title'])->slug()->toString(),
            'category_id' => fn (): int => Category::query()->firstOrCreate(
                ['type' => CategoryType::Article->value, 'slug' => 'konstruksi'],
                ['name' => 'Konstruksi'],
            )->getKey(),
            'excerpt' => fake()->sentence(),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'status' => ArticleStatus::Published,
            'published_at' => now(),
            'is_featured' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Article $article): void {
            $article->forceFill(['draft_data' => $article->publishedFields()])->saveQuietly();
        });
    }

    public function draft(): static
    {
        return $this->state(['status' => ArticleStatus::Draft, 'published_at' => null]);
    }

    public function scheduledAt(\DateTimeInterface $at): static
    {
        return $this->state(['status' => ArticleStatus::Scheduled, 'published_at' => null, 'scheduled_at' => $at]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}
