<?php

declare(strict_types=1);

namespace App\Services\Ops;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Open GitHub-issues en pull requests waar de beurt bij de beheerder ligt.
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
 * Op 10-09-2026 bleek de check twee keer te ruim gefilterd, en allebei de keren
 * op precies het geval waarvoor hij gebouwd is:
 *
 * - **Pull requests vlogen er in hun geheel uit** om niet elke week op
 *   Dependabot af te gaan. Gevolg: de Laravel 13-PR van arjankapteijn, 58
 *   bestanden uit een fork van iemand die we niet kennen, stond 7 dagen zonder
 *   één reactie en de check zweeg. Nu vliegt alleen de *bot* eruit, en dat is
 *   ook wat we die dag eigenlijk bedoelden.
 * - **"De beheerder heeft ooit iets gezegd" gold als beantwoord.** Ramon
 *   Fincken beantwoordde in #36 een wedervraag en toen was het 9 dagen stil,
 *   terwijl de check hem als afgehandeld beschouwde. Nu telt alleen wie het
 *   *laatste* woord had. Wil je hem stil krijgen zonder te antwoorden: sluiten.
 *
 * De repo is publiek, dus dit werkt zonder token. Ongeauthenticeerd staat
 * GitHub 60 verzoeken per uur toe en dit draait 1 keer per dag.
 */
class UnansweredIssues
{
    private const API = 'https://api.github.com';

    /**
     * @return list<array{number: int, title: string, days: int, kind: string}>|null
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
                    'kind' => isset($issue['pull_request']) ? 'PR' : 'Issue',
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
        // Het /issues-endpoint geeft ook pull requests terug. Weren doen we op
        // de indiener en niet op de soort: Dependabot eruit, mensen erin.
        if ($this->isBot($issue['user'] ?? [])) {
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

        // Alleen ophalen als er iets op te halen valt. Bij een PR zegt dit
        // getal niets: reviews tellen niet mee als reactie.
        if ((int) ($issue['comments'] ?? 0) === 0 && ! isset($issue['pull_request'])) {
            return true;
        }

        return ! $this->maintainerHadTheLastWord($issue, $repo, $maintainer);
    }

    /** @param array<string, mixed> $user */
    private function isBot(array $user): bool
    {
        return ($user['type'] ?? null) === 'Bot'
            || str_ends_with((string) ($user['login'] ?? ''), '[bot]');
    }

    /**
     * @param  array<string, mixed>  $issue
     *
     * De beurt ligt bij ons zodra iemand anders het laatste woord had. Dat is
     * strenger dan "we hebben ooit gereageerd", en dat moet ook: een wedervraag
     * beantwoorden is precies het moment waarop een melder afhaakt.
     */
    private function maintainerHadTheLastWord(array $issue, string $repo, string $maintainer): bool
    {
        $number = (int) $issue['number'];
        $events = $this->get(self::API."/repos/{$repo}/issues/{$number}/comments", ['per_page' => 100]);

        // Niet kunnen kijken is geen bewijs dat er geantwoord is, maar hier
        // liever een keer te veel melden dan een melding missen.
        if ($events === null) {
            return false;
        }

        // Op een PR kun je antwoorden met een review en zonder los commentaar.
        if (isset($issue['pull_request'])) {
            $events = array_merge($events, $this->get(self::API."/repos/{$repo}/pulls/{$number}/reviews", ['per_page' => 100]) ?? []);
        }

        usort($events, fn (array $a, array $b): int => strcmp($this->at($a), $this->at($b)));

        return $events !== [] && (end($events)['user']['login'] ?? null) === $maintainer;
    }

    /**
     * @param  array<string, mixed>  $event
     *
     * Een reactie draagt `created_at`, een review `submitted_at`.
     */
    private function at(array $event): string
    {
        return (string) ($event['submitted_at'] ?? $event['created_at'] ?? '');
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
