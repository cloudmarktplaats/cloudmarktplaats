<div class="mx-auto max-w-2xl rounded-sm border border-cmp-border bg-cmp-surface p-6">
    <h1 class="mb-2 text-2xl font-bold">{{ __('Bijgewerkte voorwaarden') }}</h1>
    <p class="mb-4 text-sm text-cmp-muted">
        {{ __('Onze gebruiksvoorwaarden en/of privacyverklaring zijn bijgewerkt sinds je laatste akkoord. Lees ze door en accepteer hieronder om verder te gaan.') }}
    </p>

    @foreach($documents as $doc)
        <article class="mb-6 rounded border border-cmp-border p-4">
            <header class="mb-2 flex items-center justify-between">
                <h2 class="text-lg font-semibold">
                    @if($doc['type'] === 'tos')
                        {{ __('Gebruiksvoorwaarden') }}
                    @else
                        {{ __('Privacyverklaring') }}
                    @endif
                    <span class="ml-2 text-sm text-cmp-muted">v{{ $doc['version'] }}</span>
                </h2>
            </header>
            <div class="prose prose-sm max-h-72 overflow-y-auto rounded bg-cmp-bg p-3 text-sm">
                {!! \Illuminate\Support\Str::markdown($doc['markdown']) !!}
            </div>
        </article>
    @endforeach

    <form wire:submit="accept">
        <button
            type="submit"
            class="cmp-btn cmp-btn-primary"
        >
            {{ __('Ik accepteer') }}
        </button>
        <form action="/logout" method="POST" class="mt-2 inline-block">
            @csrf
            <button type="submit" class="text-sm text-cmp-muted hover:text-cmp-ink">{{ __('Uitloggen') }}</button>
        </form>
    </form>
</div>
