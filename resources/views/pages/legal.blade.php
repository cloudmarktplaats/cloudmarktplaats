<x-layouts.marketing :title="$title" :canonical="url('/legal/'.$type)">
    <article class="mx-auto max-w-3xl px-5 py-12 sm:px-8 sm:py-16">
        <div class="cmp-section-label mb-3">{{ __('Juridisch') }}</div>
        <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">{{ $heading }}</h1>

        <p class="mt-3 font-mono text-[11px] text-cmp-faint">
            {{ __('Versie') }} {{ $version }}
            @if ($updatedAt)
                · {{ __('laatst bijgewerkt') }} {{ $updatedAt->format('Y-m-d') }}
            @endif
            @if ($locale === 'nl')
                · <a href="{{ url('/legal/'.$type.'?lang=en') }}" class="text-cmp-blue underline hover:text-cmp-blue-dark">{{ __('English') }}</a>
            @else
                · <a href="{{ url('/legal/'.$type) }}" class="text-cmp-blue underline hover:text-cmp-blue-dark">{{ __('Nederlands') }}</a>
            @endif
        </p>

        <div class="legal-prose mt-8">
            {!! $bodyHtml !!}
        </div>
    </article>
</x-layouts.marketing>
