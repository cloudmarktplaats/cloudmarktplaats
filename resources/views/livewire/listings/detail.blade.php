<div class="mx-auto max-w-3xl space-y-6">
    <article class="rounded border bg-white p-6 shadow">
        <h1 class="text-2xl font-bold">{{ $listing->title }}</h1>
        <p class="mt-1 text-sm text-gray-500">
            € {{ number_format($listing->price_cents / 100, 2, ',', '.') }} ·
            staat: {{ $listing->condition }} ·
            verkoper: {{ $listing->user->display_name ?? 'onbekend' }}
        </p>

        @if ($listing->photos->isNotEmpty())
            <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                @foreach ($listing->photos as $photo)
                    <img src="{{ $photo->urlFor('card') }}" alt="" class="rounded border">
                @endforeach
            </div>
        @endif

        <div class="prose mt-6">
            {!! nl2br(e($listing->description)) !!}
        </div>

        @if ($listing->region_postcode)
            <p class="mt-4 text-sm text-gray-500">Locatie: {{ $listing->region_postcode }}</p>
        @endif

        <div class="mt-6 flex items-center gap-3">
            <button wire:click="contactSeller" class="rounded bg-blue-600 px-4 py-2 text-white">
                Neem contact op
            </button>
            <a href="/reports/listing/{{ $listing->id }}" class="text-sm text-red-600 underline">
                Rapporteer
            </a>
        </div>

        @if ($showMessagingNotice)
            <div class="mt-4 rounded bg-yellow-50 p-3 text-sm text-yellow-800">
                Berichten arriveren in v2.1. Tot dan: stuur de verkoper
                <strong>{{ $listing->user->display_name }}</strong> een mail of bericht via een platform naar keuze.
            </div>
        @endif
    </article>
</div>
