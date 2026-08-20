<section class="mx-auto max-w-2xl px-5 py-16 sm:px-8 sm:py-20">
    <div class="cmp-section-label mb-4">{{ __('Verkopen') }}</div>
    <h1 class="text-4xl font-bold tracking-display-tighter leading-[1.05]">
        {{ __('Verkoop je als') }}<br><span class="text-cmp-muted">{{ __('particulier of als bedrijf?') }}</span>
    </h1>

    <p class="mt-6 text-[15px] leading-[1.75] text-cmp-text/90">
        {{ __('Dit verandert niets aan wat je mag plaatsen. Het bepaalt wat een koper over je te zien krijgt, en welke regels er gelden als hij een consument is.') }}
    </p>

    <form wire:submit="save" class="mt-8 space-y-4">
        <label class="flex items-start gap-3 rounded-sm border border-cmp-border bg-cmp-surface p-4 text-sm">
            <input type="checkbox" wire:model.live="isBusiness" class="mt-1">
            <span>
                <span class="font-medium">{{ __('Ik verkoop namens een bedrijf') }}</span>
                <span class="mt-1 block text-cmp-muted">{{ __('Ook als het om uitgefaseerde eigen apparatuur gaat.') }}</span>
            </span>
        </label>

        @if ($isBusiness)
            {{-- Eén keer, in gewone taal. Niet als vinkje bij de algemene
                 voorwaarden, want dan leest niemand het. --}}
            <div class="rounded-sm border-2 border-cmp-ink bg-cmp-surface p-5 text-sm leading-relaxed">
                <div class="cmp-section-label mb-3">{{ __('Wat dat betekent') }}</div>
                <p class="text-cmp-muted">{{ __('Als bedrijf verkoop je onder andere regels dan een particulier. De koper mag verwachten wat hij redelijkerwijs mag verwachten — bij tweedehands is dat minder dan bij nieuw, maar niet niets, en je kunt het richting een consument niet uitsluiten. Verstuur je in plaats van laten ophalen, dan heeft een consument veertien dagen bedenktijd; ophalen en ter plekke afrekenen valt daar niet onder.') }}</p>
                <p class="mt-3 text-cmp-muted">{{ __('Wij controleren je opgave niet en zijn geen partij bij de koop. Je bent zelf verantwoordelijk dat wat hier staat klopt.') }}</p>
            </div>

            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('Bedrijfsnaam') }}</span>
                <input wire:model="businessName" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            </label>
            @error('businessName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('KvK-nummer') }}</span>
                <input wire:model="businessRegistration" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            </label>
            @error('businessRegistration') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('Btw-nummer (optioneel)') }}</span>
                <input wire:model="businessVat" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            </label>
            @error('businessVat') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @endif

        <div class="flex items-center gap-3">
            <button type="submit" class="cmp-btn cmp-btn-primary">{{ __('Opslaan') }}</button>
            @if ($saved)
                <span class="font-mono text-[11px] uppercase tracking-wide text-cmp-signal">{{ __('opgeslagen') }}</span>
            @endif
        </div>
    </form>
</section>
