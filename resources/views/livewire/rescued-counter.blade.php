<div>
    @if ($count > 0)
        <section class="rounded-sm border border-cmp-border bg-cmp-surface px-6 py-8 text-center">
            <div class="cmp-section-label justify-center mb-3">{{ __('Samen') }}</div>
            <p class="font-mono text-4xl font-bold text-cmp-signal sm:text-5xl">{{ number_format($count, 0, ',', '.') }}</p>
            <p class="mt-2 text-sm text-cmp-muted">{{ __('apparaten gered van de sloop — en geteld.') }}</p>
        </section>
    @endif
</div>
