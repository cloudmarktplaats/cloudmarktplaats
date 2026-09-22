<?php

declare(strict_types=1);

namespace App\Services\News;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use SimpleXMLElement;

/**
 * Zet een RSS- of Atom-string om in genormaliseerde items. Geen netwerk, geen
 * state: puur XML in, array uit. De fetcher voedt hem, de tests ook.
 */
class FeedParser
{
    /**
     * @return list<array{guid: string, title: string, url: string, summary: ?string, published_at: ?Carbon}>
     */
    public function parse(string $xml): array
    {
        $root = @simplexml_load_string($xml);

        if ($root === false) {
            return [];
        }

        $nodes = isset($root->channel) ? $root->channel->item : $root->entry;
        $items = [];

        foreach ($nodes ?? [] as $node) {
            $item = $this->item($node);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return array{guid: string, title: string, url: string, summary: ?string, published_at: ?Carbon}|null
     */
    private function item(SimpleXMLElement $node): ?array
    {
        $url = $this->link($node);

        if ($url === '') {
            return null;
        }

        $guid = trim((string) ($node->guid ?? $node->id ?? '')) ?: $url;

        return [
            'guid' => $guid,
            'title' => trim((string) $node->title) ?: '(geen titel)',
            'url' => $url,
            'summary' => $this->summary($node),
            'published_at' => $this->date($node),
        ];
    }

    private function link(SimpleXMLElement $node): string
    {
        // RSS: <link>url</link>. Atom: <link href="url"/>.
        $rss = trim((string) $node->link);
        $candidate = $rss !== '' ? $rss : trim((string) ($node->link['href'] ?? ''));

        return $this->isHttpUrl($candidate) ? $candidate : '';
    }

    /**
     * Alleen http(s)-links zijn toegestaan; feeds zijn extern en niet
     * vertrouwd, dus een javascript:/data:/relatieve link wordt geweerd.
     */
    private function isHttpUrl(string $url): bool
    {
        return Str::startsWith(Str::lower($url), ['http://', 'https://']);
    }

    private function summary(SimpleXMLElement $node): ?string
    {
        $raw = (string) ($node->description ?? $node->summary ?? '');
        $clean = trim(Str::squish(strip_tags($raw)));

        return $clean === '' ? null : Str::limit($clean, 300);
    }

    private function date(SimpleXMLElement $node): ?Carbon
    {
        $raw = trim((string) ($node->pubDate ?? $node->updated ?? $node->published ?? ''));

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Exception) {
            return null;
        }
    }
}
