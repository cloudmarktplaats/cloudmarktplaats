<x-layouts.marketing :title="$title" :canonical="url('/legal/'.$type)">
    <article class="mx-auto max-w-3xl px-5 py-12 sm:px-8 sm:py-16">
        <div class="cmp-section-label mb-3">Juridisch</div>
        <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">{{ $heading }}</h1>

        <p class="mt-3 font-mono text-[11px] text-cmp-faint">
            Versie {{ $version }}
            @if ($updatedAt)
                · laatst bijgewerkt {{ $updatedAt->format('Y-m-d') }}
            @endif
            @if ($locale === 'nl')
                · <a href="{{ url('/legal/'.$type.'?lang=en') }}" class="text-cmp-blue underline hover:text-cmp-blue-dark">English</a>
            @else
                · <a href="{{ url('/legal/'.$type) }}" class="text-cmp-blue underline hover:text-cmp-blue-dark">Nederlands</a>
            @endif
        </p>

        <div class="legal-prose mt-8">
            {!! $bodyHtml !!}
        </div>
    </article>
</x-layouts.marketing>
