<div class="mx-auto max-w-xl space-y-6 rounded border bg-white p-6 shadow">
    <header>
        <h1 class="text-xl font-bold">Tweefactor-authenticatie</h1>
        <p class="text-sm text-gray-600">
            Beveilig je account met een eenmalige TOTP-code uit je authenticator-app
            (zoals Aegis, 1Password, Bitwarden, Google Authenticator).
        </p>
    </header>

    @if ($enabled && ! $confirmed && ! $secret)
        {{-- 2FA already enabled — offer disable + regenerate. --}}
        <section class="space-y-3 rounded border border-green-200 bg-green-50 p-3">
            <p class="text-sm text-green-800">2FA is actief op je account.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Recovery-codes opnieuw genereren</h2>
            <p class="text-xs text-gray-600">
                Geef je huidige TOTP-code op om 8 nieuwe codes te genereren. De oude
                codes worden ongeldig.
            </p>
            <form wire:submit="regenerate" class="space-y-2">
                <input type="text" inputmode="numeric" autocomplete="one-time-code"
                       wire:model="code" placeholder="123456" maxlength="6"
                       class="w-full rounded border p-2 font-mono">
                @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button class="rounded bg-blue-600 px-3 py-1 text-sm text-white">Genereer nieuwe codes</button>
            </form>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold text-red-700">2FA uitschakelen</h2>
            <form wire:submit="disable" class="space-y-2">
                <input type="text" inputmode="numeric" autocomplete="one-time-code"
                       wire:model="code" placeholder="TOTP-code" maxlength="6"
                       class="w-full rounded border p-2 font-mono">
                @if (auth()->user()->identities()->where('provider', 'password')->exists())
                    <input type="password" wire:model="password" placeholder="Wachtwoord"
                           class="w-full rounded border p-2">
                @endif
                @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button class="rounded border border-red-300 px-3 py-1 text-sm text-red-700 hover:bg-red-50">Uitschakelen</button>
            </form>
        </section>
    @elseif (! $secret && ! $enabled)
        <button type="button" wire:click="start" class="rounded bg-blue-600 px-3 py-2 text-sm text-white">
            Start 2FA-setup
        </button>
    @endif

    @if ($secret && ! $confirmed)
        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Stap 1 — scan de QR-code</h2>
            <div>{!! $this->qrSvg() !!}</div>
            <details class="text-xs text-gray-600">
                <summary class="cursor-pointer">Of voer het geheim handmatig in</summary>
                <code class="mt-1 block break-all rounded bg-gray-100 p-2 font-mono">{{ $secret }}</code>
            </details>

            <h2 class="text-lg font-semibold">Stap 2 — bevestig met een code</h2>
            <form wire:submit="confirm" class="space-y-2">
                <input type="text" inputmode="numeric" autocomplete="one-time-code"
                       wire:model="code" placeholder="123456" maxlength="6"
                       class="w-full rounded border p-2 font-mono">
                @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button class="rounded bg-blue-600 px-3 py-1 text-sm text-white">Bevestig</button>
            </form>
        </section>
    @endif

    @if ($confirmed && count($recovery))
        <section class="space-y-3 rounded border border-amber-200 bg-amber-50 p-3">
            <h2 class="text-lg font-semibold text-amber-900">Bewaar deze recovery-codes</h2>
            <p class="text-xs text-amber-900">
                Deze codes zie je maar één keer. Bewaar ze veilig (password manager).
                Elke code is eenmalig bruikbaar als je geen toegang hebt tot je
                authenticator-app.
            </p>
            <ul class="grid grid-cols-2 gap-2 font-mono text-sm">
                @foreach ($recovery as $rc)
                    <li class="rounded border bg-white p-2">{{ $rc }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <p class="text-xs">
        <a href="/profile/security" class="underline">Terug naar beveiliging</a>
    </p>
</div>
