<div class="mx-auto max-w-2xl px-5 py-10 sm:px-8 sm:py-14">

    <div class="cmp-section-label mb-3">{{ __('Account') }}</div>
    <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">
        {{ __('Account verwijderen') }}
    </h1>

    <p class="mt-4 text-cmp-muted">
        {{ __('Je hoeft ons hier niets voor te vragen en geen reden op te geven. Je drukt zelf op de knop en het is gebeurd.') }}
    </p>

    {{-- Zeggen wát er weggaat. "Weet je het zeker?" zonder inhoud is geen
         geïnformeerde toestemming, en hier valt niets terug te draaien. --}}
    <div class="mt-8 rounded-sm border border-cmp-border bg-cmp-surface p-5">
        <div class="cmp-section-label mb-3">{{ __('Wat er weggaat') }}</div>
        <ul class="space-y-2 text-sm text-cmp-muted">
            <li>{{ __('Je account, je e-mailadres en je login-methodes.') }}</li>
            <li>{{ trans_choice('{0}Je hebt geen advertenties.|{1}Je :count advertentie, inclusief de foto\'s.|[2,*]Je :count advertenties, inclusief de foto\'s.', $listingCount, ['count' => $listingCount]) }}</li>
            <li>{{ __('Je homelab-posts en je karma.') }}</li>
        </ul>

        <div class="cmp-section-label mb-3 mt-6">{{ __('Wat er blijft staan') }}</div>
        <ul class="space-y-2 text-sm text-cmp-muted">
            <li>{{ __('Meldingen die je over andere advertenties deed, zonder jouw naam eraan — dat is een aantekening bij die advertentie, niet bij jou.') }}</li>
        </ul>
    </div>

    <div class="mt-8 rounded-sm border border-cmp-amber bg-cmp-bg2 p-5">
        <p class="text-sm text-cmp-text">
            {{ __('Dit is onomkeerbaar. We hebben geen kopie waar we je account uit terug kunnen halen.') }}
        </p>

        <form wire:submit="destroyAccount" class="mt-5">
            <label for="confirm-username" class="block text-sm text-cmp-muted">
                {{ __('Typ je gebruikersnaam om te bevestigen:') }}
                <span class="font-mono text-cmp-text">{{ auth()->user()->username }}</span>
            </label>
            <input
                id="confirm-username"
                type="text"
                wire:model="confirmUsername"
                autocomplete="off"
                class="cmp-input mt-2 w-full font-mono"
            >
            @error('confirmUsername')
                <p class="mt-2 text-sm text-cmp-amber">{{ $message }}</p>
            @enderror

            <div class="mt-5 flex flex-wrap gap-3">
                <button type="submit" class="cmp-btn cmp-btn-primary">
                    {{ __('Verwijder mijn account definitief') }}
                </button>
                <a href="{{ route('profile.security') }}" class="cmp-btn cmp-btn-ghost">
                    {{ __('Annuleren') }}
                </a>
            </div>
        </form>
    </div>

    <p class="mt-8 text-sm text-cmp-muted">
        {{ __('Wil je alleen je advertenties weg en je account houden? Dat kan per advertentie op') }}
        <a href="{{ route('listings.mine') }}" class="underline hover:text-cmp-text">{{ __('Mijn advertenties') }}</a>.
    </p>
</div>
