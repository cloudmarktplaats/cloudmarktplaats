<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['nl', 'en'] as $locale) {
            LegalDocument::firstOrCreate(
                ['type' => 'tos', 'locale' => $locale, 'version' => '1.0.0'],
                [
                    'markdown_content' => "# Terms of Service ($locale)\n\nPlaceholder.",
                    'published_at' => now(),
                ],
            );
            LegalDocument::firstOrCreate(
                ['type' => 'privacy', 'locale' => $locale, 'version' => '1.0.0'],
                [
                    'markdown_content' => "# Privacy Policy ($locale)\n\nPlaceholder.",
                    'published_at' => now(),
                ],
            );
        }
    }
}
