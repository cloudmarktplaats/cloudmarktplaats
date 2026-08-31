<?php

declare(strict_types=1);

namespace App\Services\Ops;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Open GitHub-issues waar de beheerder nog nooit op gereageerd heeft.
 *
 * Rob Turks melding bleef 29 dagen liggen; hij zegde er zijn account om op en
 * schreef dat hij redelijkerwijs mocht aannemen dat iemand meekeek. Meldingen
 * op het platform komen sinds 22-08 in de dagelijkse mail terecht, maar een
 * issue nog steeds niet, en dat is precies het gat waar hij in verdween.
 *
 * Dit is bewust een **voorraad**signaal en geen aanwas, anders dan de rest van
 * de check. De reden dat het hier wel mag: een onbeantwoord issue daalt door
 * een handeling van de beheerder zelf, niet door die van een ander. Antwoorden
 * maakt het stil. Bij `concepten_zonder_foto` lag die knop bij de verkoper en
 * dáárom ging het daar staan roepen.
 *
 * De repo is publiek, dus dit werkt zonder token. Ongeauthenticeerd staat
 * GitHub 60 verzoeken per uur toe en dit draait 1 keer per dag.
 */
class UnansweredIssues
{
    private const API = 'https://api.github.com';

    /**
     * @return list<array{number: int, title: string, days: int}>|null
     *
     * Null betekent "we konden het niet vaststellen", niet "er is niets".
     * Zelfde regel als bij {@see SecurityAdvisories}: een check die "alles in
     * orde" zegt op het moment dat hij stuk is, is erger dan geen check.
     */
    public function find(): ?array
    {
        if (! config('cloudmarktplaats.ops.issue_check')) {
            return [];
        }

        $repo = (string) config('cloudmarktplaats.ops.issue_repo');
        $maintainer = (string) config('cloudmarktplaats.ops.issue_maintainer');
        $days = (int) config('cloudmarktplaats.ops.issue_days', 3);

        try {
            $response = $this->get(self::API."/repos/{$repo}/issues", ['state' => 'open', 'per_page' => 100]);

            if ($response === null) {
                return null;
            }

            $open = [];

            foreach ($response as $issue) {
                if (! $this->needsAnswer($issue, $repo, $maintainer, $days)) {
                    continue;
                }

                $open[] = [
                    'number' => (int) $issue['number'],
                    'title' => (string) $issue['title'],
                    'days' => (int) Carbon::parse((string) $issue['created_at'])->diffInDays(now()),
                ];
            }

            return $open;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $issue */
    private function needsAnswer(array $issue, string $repo, string $maintainer, int $days): bool
    {
        // Het /issues-endpoint geeft ook pull requests terug. Zonder dit filter
        // alarmeert de check elke week op Dependabot.
        if (isset($issue['pull_request'])) {
            return false;
        }

        // Een aankondiging die de beheerder zelf opende is geen onbeantwoorde
        // melding; die zou anders elke ochtend staan te roepen.
        if (($issue['user']['login'] ?? null) === $maintainer) {
            return false;
        }

        if (Carbon::parse((string) $issue['created_at'])->isAfter(now()->subDays($days))) {
            return false;
        }

        // Alleen ophalen als er iets op te halen valt: de meeste issues hebben
        // nul reacties en dan is het antwoord al bekend.
        if ((int) ($issue['comments'] ?? 0) === 0) {
            return true;
        }

        $comments = $this->get(self::API."/repos/{$repo}/issues/{$issue['number']}/comments", ['per_page' => 100]);

        // Niet kunnen kijken is geen bewijs dat er geantwoord is, maar hier
        // liever een keer te veel melden dan een melding missen.
        if ($comments === null) {
            return true;
        }

        foreach ($comments as $comment) {
            if (($comment['user']['login'] ?? null) === $maintainer) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>|null
     */
    private function get(string $url, array $query): ?array
    {
        $response = Http::withHeaders(['User-Agent' => 'cloudmarktplaats-daily-check'])
            ->timeout(10)
            ->get($url, $query);

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();

        return is_array($body) ? array_values($body) : null;
    }
}
