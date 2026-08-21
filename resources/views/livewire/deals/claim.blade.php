<div class="mx-auto max-w-xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">{{ __('Vertrouwen') }}</div>

    @if ($transaction === null)
        <h1 class="text-3xl font-bold tracking-display-tighter">{{ __('Deze link kennen we niet') }}</h1>
        <p class="mt-3 text-sm text-cmp-muted">
            {{ __('Misschien is er iets misgegaan bij het kopiëren. Vraag de verkoper om de link opnieuw te sturen.') }}
        </p>
    @elseif ($done === 'confirmed')
        <h1 class="text-3xl font-bold tracking-display-tighter">{{ __('Bevestigd. Bedankt.') }}</h1>
        <p class="mt-3 text-sm text-cmp-muted">
            {{ __('De verkoop staat nu op naam van de verkoper. Je vindt deze deal terug bij Mijn deals.') }}
        </p>
        <a href="{{ route('profile.deals') }}" class="cmp-btn cmp-btn-secondary mt-6">{{ __('Naar Mijn deals') }}</a>
    @elseif ($done === 'declined')
        <h1 class="text-3xl font-bold tracking-display-tighter">{{ __('Genoteerd.') }}</h1>
        <p class="mt-3 text-sm text-cmp-muted">
            {{ __('We hebben vastgelegd dat deze deal niet doorging. Er telt niets mee voor de verkoper.') }}
        </p>
    @else
        <h1 class="text-3xl font-bold tracking-display-tighter">{{ __('Deal bevestigen') }}</h1>

        <p class="mt-4 text-cmp-text">
            {{ '@'.($transaction->seller?->username ?? '?') }}
            {{ __('geeft aan dat je') }}
            <strong>{{ $transaction->listing?->title ?? __('een advertentie') }}</strong>
            {{ __('van hem hebt gekocht voor') }}
            <span class="font-mono">€ {{ number_format($transaction->amount_cents / 100, 2, ',', '.') }}</span>.
        </p>

        <p class="mt-4 text-sm text-cmp-muted">
            {{ __('Bevestigen betekent alleen dat de deal is doorgegaan. Het is geen betaling en geen verplichting. Voor jou verandert er niets, behalve dat de verkoper een bevestigde verkoop op zijn naam krijgt — dat is hoe hier vertrouwen wordt opgebouwd.') }}
        </p>

        @error('deal') <p class="mt-4 text-sm text-red-600">{{ $message }}</p> @enderror

        @guest
            <p class="mt-6 text-sm text-cmp-muted">
                {{ __('Bevestigen kan alleen met een account, anders weten we niet wie het zegt.') }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('login') }}" class="cmp-btn cmp-btn-primary">{{ __('Inloggen') }}</a>
            </div>
            <p class="mt-2 text-sm">{{ __('Nog geen account?') }} <a href="{{ route('register') }}" class="underline">{{ __('Registreer je') }}</a>.</p>
        @endguest

        @auth
            @if (! auth()->user()->hasVerifiedEmail())
                <p class="mt-6 text-sm text-cmp-muted">
                    {{ __('Bevestig eerst je e-mailadres. Daarna kun je hier terugkomen via dezelfde link.') }}
                </p>
                <a href="{{ route('verification.notice') }}" class="cmp-btn cmp-btn-primary mt-3">{{ __('E-mailadres bevestigen') }}</a>
            @else
                <div class="mt-6 flex flex-wrap gap-2">
                    <button wire:click="confirm" class="cmp-btn cmp-btn-primary">{{ __('Ja, dat klopt') }}</button>
                    <button wire:click="decline" wire:confirm="{{ __('Dit kan niet ongedaan gemaakt worden. Weet je het zeker?') }}" class="cmp-btn cmp-btn-ghost">{{ __('Nee, dit klopt niet') }}</button>
                </div>
            @endif
        @endauth
    @endif
</div>
