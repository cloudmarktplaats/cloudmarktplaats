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
            'version' => '1.0.0',
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
