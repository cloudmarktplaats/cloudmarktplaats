<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

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
    private const IGNORED_PREFIXES = ['/storage/', '/build/', '/fonts/', '/livewire/', '/healthz', '/up', '/favicon'];

    private const BOT_MARKERS = ['bot', 'crawler', 'spider', 'curl', 'wget', 'headless', 'python-requests', 'go-http'];

    public function handle(): int
    {
        $path = storage_path('nginx/access.log');

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
            if (CarbonImmutable::parse((string) $row['t'])->lt($since)) {
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

        $appHost = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?? '');
        if ($host === $appHost) {
            return 'intern';
        }

        $host = preg_replace('/^www\./', '', $host) ?? $host;
        foreach (['linkedin', 'tweakers', 'reddit', 'google', 'maindeck'] as $known) {
            if (str_contains($host, $known)) {
                return $known;
            }
        }

        return $host;
    }
}
