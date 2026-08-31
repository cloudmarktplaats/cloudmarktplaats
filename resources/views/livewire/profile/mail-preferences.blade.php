<section class="mx-auto max-w-2xl px-5 py-16 sm:px-8 sm:py-20">
    <div class="cmp-section-label mb-4">{{ __('Mail') }}</div>
    <h1 class="text-4xl font-bold tracking-display-tighter leading-[1.05]">{{ __('Mailvoorkeuren') }}</h1>

    <p class="mt-6 text-[15px] leading-[1.75] text-cmp-text/90">
        {{ __('Wat je hier aanvinkt, geldt voor je account. Zet je beide vinkjes uit, dan meld je je helemaal af.') }}
    </p>

    <form wire:submit="save" class="mt-8 space-y-6">
        {{-- Zelfde toelichting als bij Subscribe: de vinkjes dragen letterlijk
             de zin die als bewijs van toestemming wordt vastgelegd, bewust
             niet door __() heen. --}}
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
        </fieldset>

        @if ($wants_offers)
            @php $categorieLabels = \App\Livewire\Mail\Subscribe::categoryLabels(); @endphp

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

                @foreach ($errors->get('categories.*') as $categorieFouten)
                    <p class="mt-2 text-sm text-cmp-amber">{{ $categorieFouten[0] }}</p>
                @endforeach
            </fieldset>
        @endif

        <div class="flex items-center gap-3">
            <button type="submit" class="cmp-btn cmp-btn-primary">{{ __('Opslaan') }}</button>
            @if ($saved)
                <span class="font-mono text-[11px] uppercase tracking-wide text-cmp-signal">{{ __('opgeslagen') }}</span>
            @endif
        </div>
    </form>
</section>
