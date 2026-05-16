<?php

namespace Database\Factories;

use App\Models\LegalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalDocument>
 */
class LegalDocumentFactory extends Factory
{
    protected $model = LegalDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'tos',
            // Pseudo-random version keeps factory inserts from
            // colliding on the (type, locale, version) unique index.
            'version' => sprintf(
                '%d.%d.%d',
                fake()->numberBetween(1, 9),
                fake()->numberBetween(0, 99),
                fake()->unique()->numberBetween(0, 999),
            ),
            'locale' => 'nl',
            'markdown_content' => '# ToS placeholder',
            'published_at' => null,
        ];
    }

    public function tos(): static
    {
        return $this->state(fn () => ['type' => 'tos']);
    }

    public function privacy(): static
    {
        return $this->state(fn () => ['type' => 'privacy']);
    }
}
