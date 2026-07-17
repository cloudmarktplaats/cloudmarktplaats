<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HomelabPost;
use App\Models\Listing;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Dynamische sitemap: statische pagina's + gepubliceerde advertenties en
 * homelabs. Eén uur gecachet zodat crawlers de database niet bij elke hit
 * raken. Concepten, verwijderde en afgewezen items horen er niet in — die
 * zijn niet publiek.
 */
class SitemapController extends Controller
{
    /** Statische publieke pagina's die we geïndexeerd willen hebben. */
    private const STATIC_PATHS = [
        '/', '/listings', '/homelabs', '/over-ons', '/waarden',
        '/faq', '/sponsors', '/roadmap', '/doneren', '/register',
    ];

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', 3600, fn (): string => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function build(): string
    {
        $urls = [];

        foreach (self::STATIC_PATHS as $path) {
            $urls[] = ['loc' => url($path), 'lastmod' => null];
        }

        Listing::query()->where('state', 'published')->get(['ulid', 'slug', 'updated_at'])
            ->each(function (Listing $l) use (&$urls): void {
                $urls[] = [
                    'loc' => url("/listings/{$l->ulid}-{$l->slug}"),
                    'lastmod' => $l->updated_at?->toAtomString(),
                ];
            });

        HomelabPost::query()->published()->get(['ulid', 'title', 'body', 'updated_at'])
            ->each(function (HomelabPost $p) use (&$urls): void {
                $urls[] = [
                    'loc' => url("/homelabs/{$p->ulid}-{$p->slug}"),
                    'lastmod' => $p->updated_at?->toAtomString(),
                ];
            });

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach ($urls as $u) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.htmlspecialchars($u['loc'], ENT_XML1).'</loc>';
            if ($u['lastmod'] !== null) {
                $lines[] = '    <lastmod>'.$u['lastmod'].'</lastmod>';
            }
            $lines[] = '  </url>';
        }
        $lines[] = '</urlset>';

        return implode("\n", $lines);
    }
}
