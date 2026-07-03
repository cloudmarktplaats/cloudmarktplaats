<div class="mx-auto max-w-2xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">Jouw cijfers</div>
    <h1 class="text-3xl font-bold tracking-display-tighter">Statistieken</h1>
    <p class="mt-3 text-sm text-cmp-muted">Alleen jij ziet deze pagina. Geen ranglijst, geen vergelijking — gewoon jouw activiteit.</p>

    <dl class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3">
        @php
            $tiles = [
                ['Lid sinds', $stats['member_since']->translatedFormat('M Y')],
                ['Advertenties live', $stats['listings_published']],
                ['Verkocht', $stats['listings_sold']],
                ['Homelab-posts', $stats['homelab_posts']],
                ['Karma', $stats['karma']],
                ['Mensen geactiveerd', $stats['people_activated']],
            ];
        @endphp
        @foreach ($tiles as [$label, $value])
            <div class="rounded-sm border border-cmp-border bg-cmp-surface p-4">
                <div class="font-mono text-[11px] uppercase tracking-wide text-cmp-muted">{{ $label }}</div>
                <div class="mt-1 font-mono text-2xl font-medium">{{ $value }}</div>
            </div>
        @endforeach
    </dl>

    <div class="cmp-section-label mb-3 mt-10">Badges</div>
    @if (count($badges) > 0)
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($badges as $badge)
                <div class="flex items-start gap-3 rounded-sm border border-cmp-border bg-cmp-surface p-4">
                    <span class="cmp-label-chip">{{ $badge['label'] }}</span>
                    <p class="text-sm text-cmp-muted">{{ $badge['description'] }}</p>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-cmp-muted">Nog geen badges. Plaats een advertentie of laat je homelab zien om er een te verdienen.</p>
    @endif
</div>
