<?php

declare(strict_types=1);

namespace App\Services\Gamification;

/**
 * Derives earned achievement badges purely from a stats array
 * (see StatsService::forUser). No storage, no award events — badges are
 * recomputed on render. Earned, never ranked (anti-toxicity).
 */
class BadgeService
{
    /**
     * @param  array<string, mixed>  $stats
     * @return list<array{key: string, label: string, description: string}>
     */
    public function earnedFor(array $stats): array
    {
        $published = (int) ($stats['listings_published'] ?? 0);
        $sold = (int) ($stats['listings_sold'] ?? 0);
        $homelab = (int) ($stats['homelab_posts'] ?? 0);
        $karma = (int) ($stats['karma'] ?? 0);
        $activated = (int) ($stats['people_activated'] ?? 0);

        $definitions = [
            ['key' => 'first_listing', 'label' => (string) __('Eerste advertentie'), 'description' => (string) __('Je plaatste je eerste advertentie.'), 'earned' => ($published + $sold) >= 1],
            ['key' => 'first_sale', 'label' => (string) __('Eerste verkoop'), 'description' => (string) __('Je eerste stuk hardware kreeg een tweede leven.'), 'earned' => $sold >= 1],
            ['key' => 'trader', 'label' => (string) __('Handelaar'), 'description' => (string) __('Tien of meer verkopen.'), 'earned' => $sold >= 10],
            ['key' => 'homelab_hero', 'label' => (string) __('Homelab-held'), 'description' => (string) __('Je liet je lab zien.'), 'earned' => $homelab >= 1],
            ['key' => 'host', 'label' => (string) __('Gastheer'), 'description' => (string) __('Iemand die je uitnodigde werd actief.'), 'earned' => $activated >= 1],
            ['key' => 'pillar', 'label' => (string) __('Community-pilaar'), 'description' => (string) __('Vijftig of meer karma.'), 'earned' => $karma >= 50],
        ];

        return array_values(array_map(
            fn (array $d): array => ['key' => $d['key'], 'label' => $d['label'], 'description' => $d['description']],
            array_filter($definitions, fn (array $d): bool => $d['earned'] === true),
        ));
    }
}
