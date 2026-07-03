@php
    // Status, not schedule. No dates, no quarters — see § Roadmap.
    $columns = [
        [
            'key'   => 'Nu live',
            'tint'  => 'text-cmp-signal',
            'dot'   => 'bg-cmp-signal',
            'lead'  => 'Werkt vandaag, voor iedereen.',
            'items' => [
                'Account aanmaken',
                'Advertentie plaatsen',
                "Foto's (EXIF gestript)",
                'Categorieën',
                'Zoeken',
                'Snuffelen',
                'Melden',
                'Contact via relay',
            ],
        ],
        [
            'key'   => 'In aanbouw',
            'tint'  => 'text-cmp-blue',
            'dot'   => 'bg-cmp-blue',
            'lead'  => 'Waar we nu aan werken.',
            'items' => [
                'Berichten (volwaardige inbox)',
                'Reputatie & reviews',
                'Sponsoring & donaties',
            ],
        ],
        [
            'key'   => 'Verkend',
            'tint'  => 'text-cmp-amber',
            'dot'   => 'bg-cmp-amber',
            'lead'  => 'Ideeën die we serieus bekijken.',
            'items' => [
                'Web3-escrow (opt-in, 1% alleen bij on-platform betaling)',
                'Forum',
                'RSS uit de NL-tech-wereld',
                'IPFS-foto-opslag',
            ],
        ],
    ];
@endphp

<x-layouts.marketing
    title="Roadmap — Cloudmarktplaats"
    description="Wat live is, waar we aan bouwen en wat we verkennen. Richting, geen belofte."
    :canonical="url('/roadmap')"
>

    <section class="mx-auto max-w-6xl px-5 py-16 sm:px-8 sm:py-20">

        <header class="max-w-2xl mb-14">
            <div class="cmp-section-label mb-4">Roadmap</div>
            <h1 class="text-4xl sm:text-5xl font-bold tracking-display-tighter leading-[1.05]">
                Wat live is,<br>
                <span class="text-cmp-muted">en wat eraan komt.</span>
            </h1>
        </header>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach ($columns as $col)
                <section
                    aria-labelledby="col-{{ $loop->index }}"
                    class="flex flex-col rounded-sm border border-cmp-border bg-cmp-surface p-6"
                >
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full {{ $col['dot'] }}" aria-hidden="true"></span>
                        <h2 id="col-{{ $loop->index }}" class="font-mono text-sm uppercase tracking-widest {{ $col['tint'] }}">
                            {{ $col['key'] }}
                        </h2>
                    </div>

                    <p class="mt-3 text-sm text-cmp-muted">{{ $col['lead'] }}</p>

                    <ul class="mt-6 space-y-2.5 border-t border-cmp-border pt-6" role="list">
                        @foreach ($col['items'] as $item)
                            <li class="flex items-start gap-2.5 text-sm text-cmp-text">
                                <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-cmp-faint" aria-hidden="true"></span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        <p class="mt-10 text-sm text-cmp-muted">
            Dit is richting, geen belofte. De code is open — wil je iets sneller,
            <a href="https://github.com/cloudmarktplaats/cloudmarktplaats" class="text-cmp-blue hover:text-cmp-blue-light" rel="noopener external">bouw mee op GitHub</a>.
        </p>

    </section>

</x-layouts.marketing>
