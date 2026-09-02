@props(['transaction'])

@php
    /* Geen token of geen vervaldatum telt als verlopen, niet als geldig.
       Verkopen van vóór de claim-link hebben allebei NULL, en met
       `?->isPast() ?? false` viel dat in de tak voor een geldige link. De
       dagelijkse check droeg de verkoper intussen elke dag op om een nieuwe
       link te sturen, terwijl het scherm die knop juist niet toonde.
       Gevonden op 02-09-2026. */
    $expired = $transaction->claim_token === null
        || ($transaction->claim_expires_at?->isPast() ?? true);

    /* De URL pas berekenen als er een token is. `route()` met een lege
       parameter gooit `UrlGenerationException`, en deze regel stond bóven de
       `@if`: de verkoper kreeg daardoor geen ontbrekende knop maar een 500 op
       zijn eigen advertentiepagina. */
    $url = $expired ? null : route('deals.claim', ['token' => $transaction->claim_token]);
    $copyText = $expired ? null : __('Bedankt voor de koop. Wil je hier even bevestigen dat het is doorgegaan? :url — één klik, meer is het niet.', ['url' => $url]);
@endphp

{{-- `$attributes` moet op de root staan, anders valt de `wire:key` uit de
     lus in het paneel weg en hergebruikt Livewire DOM-nodes tussen claims. --}}
<div {{ $attributes->class(['mt-3 rounded-sm border border-cmp-border bg-cmp-bg2 p-3']) }}>
    @if ($expired)
        <p class="text-sm text-cmp-muted">{{ __('Deze link is verlopen.') }}</p>
        <button wire:click="newLink({{ $transaction->id }})" class="cmp-btn cmp-btn-secondary mt-2">
            {{ __('Nieuwe link') }}
        </button>
    @else
        <p class="break-all font-mono text-xs text-cmp-text">{{ $url }}</p>

        <x-copy-button :text="$copyText" :label="__('Kopieer link + tekst')" class="mt-2" />

        <p class="mt-2 font-mono text-[11px] text-cmp-faint">
            {{ __('Nog niet bevestigd. Link verloopt :date.', ['date' => $transaction->claim_expires_at?->translatedFormat('j F') ?? '—']) }}
        </p>
    @endif
</div>
