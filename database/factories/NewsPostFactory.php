<?php

namespace Database\Factories;

use App\Models\NewsPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsPost>
 */
class NewsPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'category' => 'Announcement',
            'summary' => fake()->sentence(12),
            'body' => fake()->paragraphs(2, true),
            'is_published' => true,
            'published_at' => now()->subHour(),
        ];
    }
}
