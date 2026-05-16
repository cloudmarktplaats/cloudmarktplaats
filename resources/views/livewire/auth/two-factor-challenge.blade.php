<div class="mx-auto max-w-md rounded border bg-white p-6 shadow">
    <h1 class="mb-4 text-xl font-bold">Tweefactor-verificatie</h1>
    <p class="mb-3 text-sm text-gray-600">
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
            class="w-full rounded border p-2 font-mono"
            autofocus
            required
        >
        @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button class="w-full rounded bg-blue-600 px-4 py-2 text-white">Verifiëren</button>
    </form>
</div>
