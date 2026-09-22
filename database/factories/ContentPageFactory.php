<?php

namespace Database\Factories;

use App\Enums\ContentKind;
use App\Models\ContentPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentPage>
 */
class ContentPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'kind' => ContentKind::Marketing->value,
            'key' => 'marketing.'.Str::slug($title, '_'),
            'slug' => Str::slug($title),
            'title' => $title,
            'published_revision_id' => null,
        ];
    }

    public function legal(): self
    {
        return $this->state(fn (): array => [
            'kind' => ContentKind::Legal->value,
        ]);
    }
}
