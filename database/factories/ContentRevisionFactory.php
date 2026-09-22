<?php

namespace Database\Factories;

use App\Models\ContentPage;
use App\Models\ContentRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentRevision>
 */
class ContentRevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_page_id' => ContentPage::factory(),
            'revision' => 1,
            'title' => $this->faker->sentence(3),
            'body_markdown' => "# {$this->faker->sentence()}\n\n{$this->faker->paragraph()}",
            'metadata' => null,
            'created_by_user_id' => null,
            'published_by_user_id' => null,
            'published_at' => null,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'published_at' => now(),
        ]);
    }
}
