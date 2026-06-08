<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        // The contact relay deliberately stores no message content. We
        // record only that a message was relayed for a listing and when
        // (contact_relay_logs: listing_id + created_at) for abuse and
        // rate-metrics — never the buyer's email or the message body.
        // This clause makes that promise enforceable text, matching the
        // code in App\Livewire\ContactSeller / App\Models\ContactRelayLog.
        $relayClause = [
            'nl' => "## Contact tussen kopers en verkopers\n\n"
                .'Berichten via het contactformulier worden als eenmalige e-mail '
                .'doorgestuurd naar de verkoper. **We slaan de inhoud van die berichten '
                .'niet op** en bewaren ook je e-mailadres niet. Voor misbruikbestrijding '
                .'houden we enkel bij dát er een bericht is doorgestuurd voor een advertentie '
                .'en wanneer — niet wie, niet wat.',
            'en' => "## Contact between buyers and sellers\n\n"
                .'Messages sent through the contact form are relayed to the seller as a '
                .'one-off email. **We do not store the contents of those messages** and we '
                .'do not keep your email address. For abuse prevention we record only that '
                .'a message was relayed for a listing, and when — not who, not what.',
        ];

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
                    'markdown_content' => "# Privacy Policy ($locale)\n\nPlaceholder.\n\n".$relayClause[$locale],
                    'published_at' => now(),
                ],
            );
        }
    }
}
