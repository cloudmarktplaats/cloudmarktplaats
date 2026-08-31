<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Seeds the current Terms of Service and Privacy Policy in Dutch and English
 * from the markdown files in database/seeders/legal/.
 *
 * 1.1.0 (31-08-2026) bundelt twee wijzigingen in 1 versie, zodat leden maar 1
 * keer opnieuw hoeven te accepteren: het artikel over particulier en zakelijk
 * verkopen in de ToS, en het doel plus de grondslag van de mailinglijst in de
 * privacyverklaring. Losse bumps zouden dat scherm twee keer kort achter
 * elkaar tonen voor twee losse dingen.
 *
 * The content is idempotently refreshed: re-running the seeder updates the
 * markdown for an existing (type, locale, version) row but preserves its
 * original published_at, so it does NOT trigger the re-acceptance flow
 * (which keys on a newly published version). Publishing genuinely revised
 * terms is a version bump (1.0.0 -> 1.1.0), done via the Filament panel.
 */
class LegalDocumentSeeder extends Seeder
{
    private const VERSION = '1.1.0';

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
