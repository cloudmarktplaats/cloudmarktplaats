<div class="mx-auto max-w-2xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">{{ __('Community') }}</div>
    <h1 class="text-3xl font-bold tracking-display-tighter">{{ __('Uitnodigingen') }}</h1>

    <div class="mt-6 grid grid-cols-2 gap-4">
        <div class="rounded-sm border border-cmp-border bg-cmp-surface p-4">
            <div class="font-mono text-[11px] uppercase tracking-wide text-cmp-muted">{{ __('Karma') }}</div>
            <div class="mt-1 font-mono text-2xl font-medium">{{ $karma }}</div>
        </div>
        <div class="rounded-sm border border-cmp-border bg-cmp-surface p-4">
            <div class="font-mono text-[11px] uppercase tracking-wide text-cmp-muted">{{ __('Codes over') }}</div>
            <div class="mt-1 font-mono text-2xl font-medium">{{ $credits }}</div>
        </div>
    </div>

    <div class="mt-6">
        <button wire:click="generate" class="cmp-btn cmp-btn-primary" @disabled($credits < 1)>{{ __('Genereer een code') }}</button>
        @error('generate') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="mt-8 space-y-2">
        @forelse ($codes as $code)
            @php $used = $code->used_at !== null; @endphp
            <div class="flex items-center justify-between rounded-sm border border-cmp-border bg-cmp-surface px-4 py-3">
                <div>
                    <span class="font-mono text-sm">{{ $code->code }}</span>
                    <span class="ml-3 cmp-label-chip">{{ $used ? __('Gebruikt') : __('Open') }}</span>
                </div>
                @unless ($used)
                    <span class="font-mono text-[11px] text-cmp-faint">{{ url('/register?invite='.$code->code) }}</span>
                @endunless
            </div>
        @empty
            <p class="text-sm text-cmp-muted">{{ __('Nog geen codes. Genereer er een en deel de link met iemand die je vertrouwt.') }}</p>
        @endforelse
    </div>
</div>
