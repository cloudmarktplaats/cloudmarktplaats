<div class="mx-auto max-w-2xl space-y-6 rounded border bg-white p-6 shadow">
    <header>
        <h1 class="text-xl font-bold">Beveiliging</h1>
        <p class="text-sm text-gray-600">
            Beheer je login-methodes en tweefactor-authenticatie. Je moet altijd minstens
            één werkende login-methode behouden.
        </p>
    </header>

    @error('identity')
        <p class="rounded border border-red-200 bg-red-50 p-2 text-sm text-red-700">{{ $message }}</p>
    @enderror

    @if (session('status') === 'identity-linked')
        <p class="rounded border border-green-200 bg-green-50 p-2 text-sm text-green-700">
            Login-methode toegevoegd.
        </p>
    @endif

    <section>
        <h2 class="mb-2 text-lg font-semibold">Gekoppelde login-methodes</h2>
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="py-2">Provider</th>
                    <th class="py-2">Identifier</th>
                    <th class="py-2">Laatst gebruikt</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($identities as $identity)
                    <tr class="border-b last:border-0">
                        <td class="py-2 font-mono">{{ $identity->provider }}</td>
                        <td class="py-2 font-mono text-xs text-gray-600">
                            {{ \Illuminate\Support\Str::limit($identity->provider_uid, 24) }}
                        </td>
                        <td class="py-2 text-xs text-gray-600">
                            {{ $identity->last_used_at?->diffForHumans() ?? 'nooit' }}
                        </td>
                        <td class="py-2 text-right">
                            <button
                                type="button"
                                wire:click="unlink({{ $identity->id }})"
                                @if ($identities->count() <= 1) disabled @endif
                                class="rounded border border-red-200 px-3 py-1 text-xs text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                            >Ontkoppelen</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="space-y-2">
        <h2 class="text-lg font-semibold">Methode toevoegen</h2>
        <div class="flex flex-wrap gap-2">
            <a href="/oauth/github/redirect" class="rounded border px-3 py-1 text-sm hover:bg-gray-50">GitHub koppelen</a>
            <a href="/oauth/gitlab/redirect" class="rounded border px-3 py-1 text-sm hover:bg-gray-50">GitLab koppelen</a>
        </div>
        <p class="text-xs text-gray-500">
            Wallet (SIWE) koppelen kan na aanmelden via de wallet-knop op de loginpagina.
        </p>
    </section>

    <section>
        <h2 class="text-lg font-semibold">Tweefactor-authenticatie</h2>
        <p class="text-sm text-gray-600">
            Beveilig je account met een eenmalige TOTP-code uit je authenticator-app.
        </p>
        <a href="/profile/security/2fa" class="mt-2 inline-block rounded border px-3 py-1 text-sm hover:bg-gray-50">
            2FA beheren
        </a>
    </section>
</div>
