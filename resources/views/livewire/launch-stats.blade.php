<section class="rounded-sm border-2 border-cmp-ink bg-cmp-surface p-6 sm:p-8" aria-label="{{ __('Beta-statistieken') }}">

    {{-- Founding-members scarcity: the FOMO anchor. --}}
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
        <div>
            <div class="cmp-section-label mb-2">{{ __('Beta · de eerste 100') }}</div>
            <p class="font-mono text-4xl font-bold tracking-tight sm:text-5xl">
                <span class="text-cmp-signal">{{ number_format($members, 0, ',', '.') }}</span><span class="text-cmp-muted"> / {{ $cohort }}</span>
            </p>
            <p class="mt-1 text-sm text-cmp-muted">
                @if ($full)
                    {{ __('De eerste 100 zijn binnen. Nieuwe leden komen op de wachtlijst.') }}
                @else
                    {{ __('founding members. De vroege leden vormen de cultuur.') }}
                @endif
            </p>
        </div>
        @unless ($full)
            <div class="text-right">
                <p class="font-mono text-2xl font-bold text-cmp-ink">{{ $spotsLeft }}</p>
                <p class="font-mono text-[11px] uppercase tracking-widest text-cmp-faint">{{ __('plekken vrij') }}</p>
            </div>
        @endunless
    </div>

    {{-- Progress bar toward the cohort of 100. --}}
    <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-cmp-bg2" role="progressbar"
         aria-valuenow="{{ $members }}" aria-valuemin="0" aria-valuemax="{{ $cohort }}">
        <div class="h-full rounded-full bg-cmp-signal transition-all" style="width: {{ max(2, $pct) }}%"></div>
    </div>

    {{-- Stats for nerds: real, live platform numbers. --}}
    <dl class="mt-8 grid grid-cols-3 gap-4 border-t border-cmp-border pt-6 text-center">
        <div>
            <dt class="font-mono text-[11px] uppercase tracking-widest text-cmp-faint">{{ __('advertenties live') }}</dt>
            <dd class="mt-1 font-mono text-2xl font-bold sm:text-3xl">{{ number_format($stats['listings_live'], 0, ',', '.') }}</dd>
        </div>
        <div>
            <dt class="font-mono text-[11px] uppercase tracking-widest text-cmp-faint">{{ __('gered van de sloop') }}</dt>
            <dd class="mt-1 font-mono text-2xl font-bold text-cmp-signal sm:text-3xl">{{ number_format($stats['rescued'], 0, ',', '.') }}</dd>
        </div>
        <div>
            <dt class="font-mono text-[11px] uppercase tracking-widest text-cmp-faint">{{ __('homelabs gedeeld') }}</dt>
            <dd class="mt-1 font-mono text-2xl font-bold sm:text-3xl">{{ number_format($stats['homelabs'], 0, ',', '.') }}</dd>
        </div>
    </dl>
</section>
