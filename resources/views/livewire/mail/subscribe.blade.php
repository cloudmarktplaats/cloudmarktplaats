<div class="mx-auto max-w-xl px-5 py-16 sm:px-8 sm:py-20">
    <div class="cmp-section-label mb-4">{{ __('Nieuwsbrief') }}</div>

    @if ($done)
        <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">{{ __('Kijk in je mail.') }}</h1>
        <p class="mt-4 text-cmp-text/90 text-[15px] leading-[1.75]">
            {{ __('Er staat een link klaar. Zolang je daar niet op klikt, sturen we niets.') }}
        </p>
    @else
        <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">{{ __('Mail als er iets voor je bij zit.') }}</h1>
        <p class="mt-4 text-cmp-text/90 text-[15px] leading-[1.75]">
            {{ __('Geen account nodig. Je krijgt alleen waar je hieronder ja op zegt, en afmelden kan met de link onderaan elk bericht.') }}
        </p>

        <form wire:submit="save" class="mt-8 space-y-6">
            <div>
                <label for="ml-email" class="block text-sm font-medium text-cmp-text">{{ __('Je e-mailadres') }}</label>
                <input
                    type="email"
                    id="ml-email"
                    wire:model="email"
                    required
                    autocomplete="email"
                    class="mt-1 block w-full rounded-md border-cmp-border bg-cmp-bg2 text-cmp-text placeholder:text-cmp-faint focus:border-cmp-blue focus:ring-cmp-blue"
                    placeholder="{{ __('jij@voorbeeld.nl') }}"
                >
                @error('email') <p class="mt-1 text-sm text-cmp-amber">{{ $message }}</p> @enderror
            </div>

            {{-- De vinkjes dragen letterlijk de zin die straks als bewijs van
                 toestemming in de rij komt te staan, en die zin komt daarom uit
                 dezelfde constante als het bewijs zelf.

                 Bewust niet door __() heen: dan zou een Engelse bezoeker een
                 Engelse zin aanvinken terwijl de Nederlandse constante wordt
                 vastgelegd, en bewijst het vak iets dat niemand heeft gezien. --}}
            <fieldset class="space-y-3">
                <legend class="text-sm font-medium text-cmp-text">{{ __('Waar wil je mail over?') }}</legend>

                <label class="flex items-start gap-2 text-[15px] text-cmp-text/90">
                    <input type="checkbox" wire:model.live="wants_offers" class="mt-1 rounded-sm border-cmp-border bg-cmp-bg2 text-cmp-blue focus:ring-cmp-blue">
                    <span>{{ \App\Livewire\Mail\Subscribe::CONSENT_OFFERS }}</span>
                </label>

                <label class="flex items-start gap-2 text-[15px] text-cmp-text/90">
                    <input type="checkbox" wire:model="wants_updates" class="mt-1 rounded-sm border-cmp-border bg-cmp-bg2 text-cmp-blue focus:ring-cmp-blue">
                    <span>{{ \App\Livewire\Mail\Subscribe::CONSENT_UPDATES }}</span>
                </label>

                @error('wants_offers') <p class="text-sm text-cmp-amber">{{ $message }}</p> @enderror
            </fieldset>

            @if ($wants_offers)
                @php
                    $categorieLabels = [
                        'compute' => __('Compute'),
                        'networking' => __('Networking'),
                        'servers' => __('Server hardware'),
                        'storage' => __('Storage'),
                        'av' => __('Audio/Video pro'),
                        'power' => __('Power'),
                        'kabels' => __('Kabels & connectoren'),
                        'fabrication' => __('3D printers & CNC'),
                        'books' => __('Boeken & documentatie'),
                        'licenses' => __('Software licenties'),
                        'meet' => __('Meetapparatuur'),
                        'misc' => __('Overig'),
                    ];
                @endphp

                <fieldset>
                    <legend class="text-sm font-medium text-cmp-text">{{ __('Over welk aanbod?') }}</legend>
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach (\App\Livewire\Mail\Subscribe::CATEGORIES as $slug)
                            <label class="flex items-start gap-2 text-[15px] text-cmp-text/90">
                                <input type="checkbox" value="{{ $slug }}" wire:model="categories" class="mt-1 rounded-sm border-cmp-border bg-cmp-bg2 text-cmp-blue focus:ring-cmp-blue">
                                <span>{{ $categorieLabels[$slug] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('categories') <p class="mt-2 text-sm text-cmp-amber">{{ $message }}</p> @enderror
                </fieldset>
            @endif

            <button type="submit" class="cmp-btn cmp-btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('Meld me aan') }}</span>
                <span wire:loading wire:target="save">{{ __('Bezig…') }}</span>
            </button>
        </form>

        <p class="mt-6 text-sm text-cmp-muted">
            {!! __('We gebruiken je adres alleen hiervoor en geven het aan niemand door. Zo gaan we met je gegevens om: <a href=":url" class="underline">privacyverklaring</a>.', ['url' => route('legal.show', 'privacy')]) !!}
        </p>
    @endif
</div>
