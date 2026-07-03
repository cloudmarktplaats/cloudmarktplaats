<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Seeds the initial (v1.0.0) Terms of Service and Privacy Policy in Dutch
 * and English from the markdown files in database/seeders/legal/.
 *
 * The content is idempotently refreshed: re-running the seeder updates the
 * markdown for an existing (type, locale, version) row but preserves its
 * original published_at, so it does NOT trigger the re-acceptance flow
 * (which keys on a newly published version). Publishing genuinely revised
 * terms is a version bump (1.0.0 -> 1.1.0), done via the Filament panel.
 */
class LegalDocumentSeeder extends Seeder
{
    private const VERSION = '1.0.0';

    public function run(): void
    {
        foreach (['tos', 'privacy'] as $type) {
            foreach (['nl', 'en'] as $locale) {
                $markdown = (string) file_get_contents(
                    database_path("seeders/legal/{$type}.{$locale}.md")
                );

                $document = LegalDocument::query()->firstOrNew([
                    'type' => $type,
                    'locale' => $locale,
                    'version' => self::VERSION,
                ]);

                $document->markdown_content = $markdown;
                $document->published_at ??= now();
                $document->save();
            }
        }
    }
}
