<div class="mx-auto max-w-md rounded-sm border border-cmp-border bg-cmp-surface p-6">
    <h1 class="mb-4 text-xl font-bold">Tweefactor-verificatie</h1>
    <p class="mb-3 text-sm text-cmp-muted">
        Vul de 6-cijferige code uit je authenticator-app in. Geen toegang tot je
        app? Gebruik een van je recovery-codes.
    </p>
    <form wire:submit="submit" class="space-y-3">
        <input
            type="text"
            inputmode="text"
            autocomplete="one-time-code"
            wire:model="code"
            placeholder="123456 of recovery-code"
            class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal font-mono"
            autofocus
            required
        >
        @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button class="w-full cmp-btn cmp-btn-primary">Verifiëren</button>
    </form>
</div>
