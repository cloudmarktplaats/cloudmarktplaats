@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold">Zoeken</h1>

    <form method="GET" action="/search" class="flex gap-2">
        <input
            type="search"
            name="q"
            value="{{ $q }}"
            placeholder="Zoek naar hardware, merken, modellen…"
            class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal"
            autofocus
        >
        <button class="cmp-btn cmp-btn-primary">Zoeken</button>
    </form>

    @if ($q === '')
        <p class="text-cmp-muted">Voer een zoekterm in om resultaten te zien.</p>
    @elseif ($results->isEmpty())
        <p class="rounded border bg-white p-6 text-cmp-muted">Geen resultaten voor "<strong>{{ $q }}</strong>".</p>
    @else
        <p class="text-sm text-cmp-muted">{{ $results->total() }} resultaat(en) voor "<strong>{{ $q }}</strong>"</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($results as $listing)
                <a href="/listings/{{ $listing->ulid }}-{{ $listing->slug }}" class="block rounded border bg-white p-4 shadow-sm transition hover:shadow">
                    <h2 class="font-semibold">{{ $listing->title }}</h2>
                    <p class="text-sm text-cmp-muted">€ {{ number_format($listing->price_cents / 100, 2, ',', '.') }}</p>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $results->links() }}</div>
    @endif
</div>
@endsection
