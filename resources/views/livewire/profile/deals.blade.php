<div class="mx-auto max-w-2xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">Vertrouwen</div>
    <h1 class="text-3xl font-bold tracking-display-tighter">Mijn deals</h1>
    <p class="mt-3 text-sm text-cmp-muted">Een verkoper heeft jou als koper gemarkeerd. Bevestig de deals die klopten.</p>
    @error('deal') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

    <div class="mt-8 space-y-2">
        @forelse ($pending as $tx)
            <div class="flex items-center justify-between rounded-sm border border-cmp-border bg-cmp-surface px-4 py-3">
                <div>
                    <span class="text-sm text-cmp-text">{{ $tx->listing?->title ?? 'Advertentie' }}</span>
                    <span class="ml-3 font-mono text-[11px] text-cmp-faint">€ {{ number_format($tx->amount_cents / 100, 2, ',', '.') }}</span>
                </div>
                <button wire:click="confirm({{ $tx->id }})" class="cmp-btn cmp-btn-primary">Deal bevestigen</button>
            </div>
        @empty
            <p class="text-sm text-cmp-muted">Geen openstaande deals om te bevestigen.</p>
        @endforelse
    </div>
</div>
