<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public renderer for the current published legal documents (ToS,
 * privacy). The authoritative text lives in the `legal_documents` table
 * (versioned, with a re-acceptance flow); this controller renders the
 * most recent published version for the requested type and locale.
 *
 * Markdown is rendered with raw-HTML escaping: even though the content
 * is staff-authored, a compromised admin account or DB row must not be
 * able to inject <script> onto a public page.
 */
class LegalController extends Controller
{
    private const TYPES = ['tos', 'privacy'];

    private const LOCALES = ['nl', 'en'];

    public function show(Request $request, string $type): View
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $locale = $request->query('lang');
        if (! is_string($locale) || ! in_array($locale, self::LOCALES, true)) {
            $locale = 'nl';
        }

        $document = LegalDocument::current($type, $locale)
            ?? LegalDocument::current($type, 'nl');

        abort_if($document === null, 404);

        $html = Str::markdown($document->markdown_content, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $titles = [
            'tos' => $locale === 'en' ? 'Terms of Service' : 'Gebruiksvoorwaarden',
            'privacy' => $locale === 'en' ? 'Privacy Policy' : 'Privacyverklaring',
        ];

        return view('pages.legal', [
            'title' => $titles[$type].' — Cloudmarktplaats',
            'heading' => $titles[$type],
            'bodyHtml' => $html,
            'version' => $document->version,
            'updatedAt' => $document->published_at,
            'type' => $type,
            'locale' => $locale,
        ]);
    }
}
