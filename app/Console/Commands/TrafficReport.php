<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Reads nginx's access log and answers three questions: where does traffic come
 * from, what do seller-shares bring in, and which pages get visited.
 *
 * No tracker, no cookie, no JS: the log already carries the referrer and the
 * querystring with our utm_* params. The IP is not logged at all (see
 * docker/nginx/default.conf), which is why this reports *visits*, not unique
 * visitors — that is the deliberate trade.
 */
class TrafficReport extends Command
{
    protected $signature = 'traffic:report {--days=7 : Aantal dagen terugkijken}';

    protected $description = 'Verkeer per referrer, UTM-bron en pagina (uit de nginx-log)';

    /** Paths that are not page views. */
    private const IGNORED_PREFIXES = ['/storage/', '/build/', '/fonts/', '/livewire/', '/healthz', '/favicon'];

    private const BOT_MARKERS = ['bot', 'crawler', 'spider', 'curl', 'wget', 'headless', 'python-requests', 'go-http'];

    /**
     * Known sources, matched on domain boundary — never as a substring:
     * "notlinkedin.example.com" contains "linkedin" but is not LinkedIn, and
     * misattributing traffic defeats the point of this report.
     *
     * @var array<string, list<string>>
     */
    private const KNOWN_SOURCES = [
        'linkedin' => ['linkedin.com', 'lnkd.in'],
        'tweakers' => ['tweakers.net'],
        'reddit' => ['reddit.com', 'redd.it'],
        'google' => ['google.com', 'google.nl'],
        'maindeck' => ['maindeck.eu'],
    ];

    public function handle(): int
    {
        $path = (string) config('cloudmarktplaats.traffic.access_log');

        if (! is_readable($path)) {
            $this->warn("Geen logbestand op {$path} — draait nginx met het cmp_privacy log_format?");

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $since = CarbonImmutable::now()->subDays($days);

        $referrers = [];
        $utms = [];
        $pages = [];
        $visits = 0;

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Kan {$path} niet openen.");

            return self::FAILURE;
        }

        while (($line = fgets($handle)) !== false) {
            $row = json_decode(trim($line), true);
            if (! is_array($row) || ! isset($row['u'], $row['t'])) {
                continue;
            }
            try {
                $when = CarbonImmutable::parse((string) $row['t']);
            } catch (Throwable) {
                // A truncated or malformed line must not take the whole report
                // down — skip it like we skip non-JSON above.
                continue;
            }
            if ($when->lt($since)) {
                continue;
            }
            if ($this->isBot((string) ($row['ua'] ?? ''))) {
                continue;
            }

            $uri = (string) $row['u'];
            $path_only = (string) parse_url($uri, PHP_URL_PATH);
            if ($this->isIgnored($path_only)) {
                continue;
            }

            $visits++;
            $pages[$path_only] = ($pages[$path_only] ?? 0) + 1;

            $origin = $this->referrerOrigin((string) ($row['ref'] ?? ''));
            $referrers[$origin] = ($referrers[$origin] ?? 0) + 1;

            $query = (string) (parse_url($uri, PHP_URL_QUERY) ?? '');
            parse_str($query, $params);
            $source = isset($params['utm_source']) && is_string($params['utm_source']) ? $params['utm_source'] : null;
            if ($source !== null) {
                $campaign = isset($params['utm_campaign']) && is_string($params['utm_campaign']) ? $params['utm_campaign'] : '—';
                $key = $source.' / '.$campaign;
                $utms[$key] = ($utms[$key] ?? 0) + 1;
            }
        }
        fclose($handle);

        $this->newLine();
        $this->info("{$visits} paginabezoeken in de laatste {$days} dag(en)");
        $this->line('<comment>Bezoeken, geen unieke bezoekers: we loggen geen IP.</comment>');
        $this->newLine();

        $this->table(['Referrer', 'Bezoeken'], $this->rows($referrers));
        $this->newLine();

        if ($utms === []) {
            $this->line('Geen UTM-getagd verkeer in deze periode.');
        } else {
            $this->table(['utm_source / campaign', 'Bezoeken'], $this->rows($utms));
        }
        $this->newLine();

        $this->table(['Pagina', 'Bezoeken'], $this->rows($pages, 10));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{0: string, 1: int}>
     */
    private function rows(array $counts, int $limit = 15): array
    {
        arsort($counts);

        $rows = [];
        foreach (array_slice($counts, 0, $limit, true) as $key => $n) {
            $rows[] = [$key, $n];
        }

        return $rows;
    }

    private function isBot(string $ua): bool
    {
        $ua = strtolower($ua);
        foreach (self::BOT_MARKERS as $marker) {
            if (str_contains($ua, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function isIgnored(string $path): bool
    {
        foreach (self::IGNORED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collapse a referrer to its origin: the LinkedIn app and the LinkedIn web
     * feed are one source, not two. Our own host is reported separately so an
     * internal click-through doesn't read as an inbound visit.
     */
    private function referrerOrigin(string $referrer): string
    {
        if ($referrer === '' || $referrer === '-') {
            return 'direct';
        }

        $host = (string) (parse_url($referrer, PHP_URL_HOST) ?? '');

        // android-app://com.linkedin.android/ has no host, only a path.
        if ($host === '' && str_contains($referrer, 'linkedin')) {
            return 'linkedin';
        }
        if ($host === '') {
            return 'overig';
        }

        $host = preg_replace('/^www\./', '', $host) ?? $host;

        $appHost = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?? '');
        $appHost = preg_replace('/^www\./', '', $appHost) ?? $appHost;

        // Our own host is reported separately: an internal click-through is not
        // an inbound visit.
        if ($host !== '' && $host === $appHost) {
            return 'intern';
        }

        foreach (self::KNOWN_SOURCES as $label => $domains) {
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return $label;
                }
            }
        }

        return $host;
    }
}
