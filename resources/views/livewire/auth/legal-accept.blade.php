<div class="mx-auto max-w-2xl rounded border bg-white p-6 shadow">
    <h1 class="mb-2 text-2xl font-bold">Bijgewerkte voorwaarden</h1>
    <p class="mb-4 text-sm text-gray-700">
        Onze gebruiksvoorwaarden en/of privacyverklaring zijn bijgewerkt
        sinds je laatste akkoord. Lees ze door en accepteer hieronder om
        verder te gaan.
    </p>

    @foreach($documents as $doc)
        <article class="mb-6 rounded border border-gray-200 p-4">
            <header class="mb-2 flex items-center justify-between">
                <h2 class="text-lg font-semibold">
                    @if($doc['type'] === 'tos')
                        Gebruiksvoorwaarden
                    @else
                        Privacyverklaring
                    @endif
                    <span class="ml-2 text-sm text-gray-500">v{{ $doc['version'] }}</span>
                </h2>
            </header>
            <div class="prose prose-sm max-h-72 overflow-y-auto rounded bg-gray-50 p-3 text-sm">
                {!! \Illuminate\Support\Str::markdown($doc['markdown']) !!}
            </div>
        </article>
    @endforeach

    <form wire:submit="accept">
        <button
            type="submit"
            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
        >
            Ik accepteer
        </button>
        <form action="/logout" method="POST" class="mt-2 inline-block">
            @csrf
            <button type="submit" class="text-sm text-gray-500 hover:text-gray-800">Uitloggen</button>
        </form>
    </form>
</div>
