<div class="mx-auto max-w-2xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">{{ __('Vertrouwen') }}</div>
    <h1 class="text-3xl font-bold tracking-display-tighter">{{ __('Mijn deals') }}</h1>
    <p class="mt-3 text-sm text-cmp-muted">
        {{ __('Je bevestigde deals, gekocht en verkocht. Een verkoop telt pas mee voor een verkopersprofiel als de koper hem bevestigd heeft.') }}
    </p>
    @error('deal') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

    {{-- Rijen van vóór de claim-link: die hebben al een koper en wachten nog
         op zijn bevestiging. Nieuwe deals komen hier niet meer bij. --}}
    @if ($pending->isNotEmpty())
        <h2 class="mt-8 font-display text-lg font-bold tracking-display-tight">{{ __('Wacht op jouw bevestiging') }}</h2>
        <div class="mt-3 space-y-2">
            @foreach ($pending as $tx)
                <div class="flex items-center justify-between rounded-sm border border-cmp-border bg-cmp-surface px-4 py-3">
                    <span class="text-sm text-cmp-text">{{ $tx->listing?->title ?? __('Advertentie') }}</span>
                    <button wire:click="confirm({{ $tx->id }})" class="cmp-btn cmp-btn-primary">{{ __('Deal bevestigen') }}</button>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-8 space-y-2">
        @forelse ($confirmed as $tx)
            <div class="flex items-center justify-between rounded-sm border border-cmp-border bg-cmp-surface px-4 py-3">
                <div class="min-w-0">
                    <span class="cmp-label-chip border-cmp-signal text-cmp-signal">
                        {{ $tx->seller_user_id === auth()->id() ? __('Verkocht') : __('Gekocht') }}
                    </span>
                    <span class="ml-2 text-sm text-cmp-text">{{ $tx->listing?->title ?? __('Advertentie') }}</span>
                </div>
                <div class="shrink-0 text-right font-mono text-[11px] text-cmp-faint">
                    <div>€ {{ number_format($tx->amount_cents / 100, 2, ',', '.') }}</div>
                    <div>{{ $tx->completed_at?->format('Y-m-d') }}</div>
                </div>
            </div>
        @empty
            <p class="text-sm text-cmp-muted">
                {{ __('Hier komen de deals te staan die jij of je tegenpartij bevestigd heeft.') }}
            </p>
        @endforelse
    </div>
</div>
