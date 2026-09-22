<section class="mx-auto max-w-4xl px-5 sm:px-8 py-16 sm:py-20">
    <header class="mb-10">
        <div class="cmp-section-label mb-4">{{ __('Nieuws') }}</div>
        <h1 class="text-4xl sm:text-5xl font-bold tracking-display-tighter leading-[1.05]">
            {{ __('NL-tech nieuws,') }}<br>
            <span class="text-cmp-muted">{{ __('zonder trackers.') }}</span>
        </h1>
        <p class="mt-6 max-w-2xl text-cmp-muted leading-relaxed">
            {{ __('Wij halen de feeds zelf op en tonen alleen titel, samenvatting en link. Je browser praat nooit met de bron, dus er lekt geen IP en er staat geen tracker in beeld.') }}
        </p>
        @guest
            <p class="mt-4 text-sm text-cmp-muted">
                {{ __('Met een account kies je zelf je bronnen en houdt de reader bij wat je al las.') }}
                <a href="{{ route('register') }}" class="text-cmp-blue hover:underline">{{ __('Account aanmaken') }}</a>
            </p>
        @endguest
    </header>

    @auth
        <div class="mb-8 flex flex-wrap items-center gap-2">
            @foreach ($sources as $source)
                @php($on = in_array($source->id, $selectedSourceIds, true))
                <button type="button" wire:click="toggleSource({{ $source->id }})"
                    @class([
                        'rounded-sm border px-3 py-1.5 text-sm transition',
                        'border-cmp-blue bg-cmp-blue/10 text-cmp-text' => $on,
                        'border-cmp-border text-cmp-muted hover:text-cmp-text' => ! $on,
                    ])>
                    {{ $source->name }}
                    @if (($unreadCounts[$source->id] ?? 0) > 0)
                        <span class="ml-1 font-mono text-[11px] text-cmp-blue">{{ $unreadCounts[$source->id] }}</span>
                    @endif
                </button>
            @endforeach
            <button type="button" wire:click="markAllRead"
                class="ml-auto font-mono text-[11px] text-cmp-muted hover:text-cmp-text">
                {{ __('markeer alles gelezen') }}
            </button>
        </div>
    @endauth

    <ul class="border-t border-cmp-border" role="list">
        @forelse ($items as $item)
            <li class="border-b border-cmp-border py-5">
                <div class="flex items-baseline justify-between gap-4">
                    <span class="cmp-section-label">{{ $item->source->name }}</span>
                    @if ($item->published_at)
                        <time class="font-mono text-[11px] text-cmp-muted" datetime="{{ $item->published_at->toIso8601String() }}">
                            {{ $item->published_at->diffForHumans() }}
                        </time>
                    @endif
                </div>
                <h2 class="mt-1 text-base font-bold tracking-display-tight">
                    <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer external" class="hover:text-cmp-blue">
                        {{ $item->title }}
                    </a>
                </h2>
                @if ($item->summary)
                    <p class="mt-1 text-sm text-cmp-muted leading-relaxed">{{ $item->summary }}</p>
                @endif
            </li>
        @empty
            <li class="py-10 text-sm text-cmp-muted">{{ __('Nog geen nieuws opgehaald. Kom zo terug.') }}</li>
        @endforelse
    </ul>
</section>
