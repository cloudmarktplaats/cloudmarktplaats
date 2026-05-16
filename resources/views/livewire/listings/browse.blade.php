<div class="space-y-4">
    <h1 class="text-2xl font-bold">
        @if ($categoryPath)
            Categorie: {{ $categoryPath }}
        @else
            Alle advertenties
        @endif
    </h1>

    @if ($listings->isEmpty())
        <p class="rounded border bg-white p-6 text-gray-500">Geen advertenties gevonden.</p>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($listings as $listing)
                <a href="/listings/{{ $listing->ulid }}-{{ $listing->slug }}" class="block rounded border bg-white p-4 shadow-sm transition hover:shadow">
                    <h2 class="font-semibold">{{ $listing->title }}</h2>
                    <p class="text-sm text-gray-500">€ {{ number_format($listing->price_cents / 100, 2, ',', '.') }}</p>
                    @if ($listing->region_postcode)
                        <p class="text-xs text-gray-400">{{ $listing->region_postcode }}</p>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $listings->links() }}</div>
    @endif
</div>
