<?php

declare(strict_types=1);

use App\Services\News\FeedParser;

it('parses an RSS feed and strips HTML from the summary', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0"><channel>
      <item>
        <title>Nieuwe NAS getest</title>
        <link>https://example.test/nas</link>
        <guid>https://example.test/nas?p=1</guid>
        <description><![CDATA[<p>Een <b>snelle</b> NAS.</p>]]></description>
        <pubDate>Tue, 22 Sep 2026 08:00:00 +0200</pubDate>
      </item>
    </channel></rss>
    XML;

    $items = (new FeedParser)->parse($xml);

    expect($items)->toHaveCount(1)
        ->and($items[0]['title'])->toBe('Nieuwe NAS getest')
        ->and($items[0]['url'])->toBe('https://example.test/nas')
        ->and($items[0]['guid'])->toBe('https://example.test/nas?p=1')
        ->and($items[0]['summary'])->toBe('Een snelle NAS.')
        ->and($items[0]['published_at']->toDateString())->toBe('2026-09-22');
});

it('parses an Atom feed and falls back to the link as guid', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <feed xmlns="http://www.w3.org/2005/Atom">
      <entry>
        <title>Homelab tip</title>
        <link href="https://example.test/homelab"/>
        <summary>Korte samenvatting</summary>
        <updated>2026-09-20T10:00:00Z</updated>
      </entry>
    </feed>
    XML;

    $items = (new FeedParser)->parse($xml);

    expect($items)->toHaveCount(1)
        ->and($items[0]['url'])->toBe('https://example.test/homelab')
        ->and($items[0]['guid'])->toBe('https://example.test/homelab')
        ->and($items[0]['published_at']->toDateString())->toBe('2026-09-20');
});

it('returns an empty array for unparseable XML', function () {
    expect((new FeedParser)->parse('dit is geen xml'))->toBe([]);
});

it('skips an item without a link', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0"><channel>
      <item><title>Geen link</title></item>
    </channel></rss>
    XML;

    expect((new FeedParser)->parse($xml))->toBe([]);
});
