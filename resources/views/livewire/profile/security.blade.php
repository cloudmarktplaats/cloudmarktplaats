<div class="mx-auto max-w-2xl space-y-6 rounded border bg-white p-6 shadow">
    <header>
        <h1 class="text-xl font-bold">{{ __('Beveiliging') }}</h1>
        <p class="text-sm text-cmp-muted">
            {{ __('Beheer je weergavenaam, login-methodes en tweefactor-authenticatie. Je moet altijd minstens één werkende login-methode behouden.') }}
        </p>
    </header>

    <section class="rounded-sm border border-cmp-border p-4">
        <h2 class="font-semibold">{{ __('Weergavenaam') }}</h2>
        <p class="mt-1 text-sm text-cmp-muted">
            {{ __('De naam die anderen bij je advertenties zien. Je gebruikersnaam (je unieke handle) blijft ongewijzigd.') }}
        </p>
        <form wire:submit="saveDisplayName" class="mt-3 flex flex-wrap items-start gap-2">
            <div class="min-w-0 flex-1">
                <input wire:model="displayName" placeholder="{{ __('weergavenaam') }}"
                       class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
                @error('displayName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="cmp-btn cmp-btn-secondary">{{ __('Opslaan') }}</button>
        </form>
        @if (session('display_name_saved'))
            <p class="mt-2 text-sm text-cmp-signal">{{ __('Weergavenaam opgeslagen.') }}</p>
        @endif
    </section>

    @error('identity')
        <p class="rounded border border-red-200 bg-red-50 p-2 text-sm text-red-700">{{ $message }}</p>
    @enderror

    @if (session('status') === 'identity-linked')
        <p class="rounded border border-green-200 bg-green-50 p-2 text-sm text-green-700">
            {{ __('Login-methode toegevoegd.') }}
        </p>
    @endif

    <section>
        <h2 class="mb-2 text-lg font-semibold">{{ __('Gekoppelde login-methodes') }}</h2>
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="py-2">{{ __('Provider') }}</th>
                    <th class="py-2">{{ __('Identifier') }}</th>
                    <th class="py-2">{{ __('Laatst gebruikt') }}</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($identities as $identity)
                    <tr class="border-b last:border-0">
                        <td class="py-2 font-mono">{{ $identity->provider }}</td>
                        <td class="py-2 font-mono text-xs text-cmp-muted">
                            {{ \Illuminate\Support\Str::limit($identity->provider_uid, 24) }}
                        </td>
                        <td class="py-2 text-xs text-cmp-muted">
                            {{ $identity->last_used_at?->diffForHumans() ?? __('nooit') }}
                        </td>
                        <td class="py-2 text-right">
                            <button
                                type="button"
                                wire:click="unlink({{ $identity->id }})"
                                @if ($identities->count() <= 1) disabled @endif
                                class="rounded border border-red-200 px-3 py-1 text-xs text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                            >{{ __('Ontkoppelen') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="space-y-2">
        <h2 class="text-lg font-semibold">{{ __('Methode toevoegen') }}</h2>
        <div class="flex flex-wrap gap-2">
            <a href="/oauth/github/redirect" class="rounded border px-3 py-1 text-sm hover:bg-cmp-bg">{{ __('GitHub koppelen') }}</a>
            <a href="/oauth/gitlab/redirect" class="rounded border px-3 py-1 text-sm hover:bg-cmp-bg">{{ __('GitLab koppelen') }}</a>
        </div>
        <p class="text-xs text-cmp-muted">
            {{ __('Wallet (SIWE) koppelen kan na aanmelden via de wallet-knop op de loginpagina.') }}
        </p>
    </section>

    <section>
        <h2 class="text-lg font-semibold">{{ __('Tweefactor-authenticatie') }}</h2>
        <p class="text-sm text-cmp-muted">
            {{ __('Beveilig je account met een eenmalige TOTP-code uit je authenticator-app.') }}
        </p>
        <a href="/profile/security/2fa" class="mt-2 inline-block rounded border px-3 py-1 text-sm hover:bg-cmp-bg">
            {{ __('2FA beheren') }}
        </a>
    </section>

    @if (config('cloudmarktplaats.features.invites'))
    <section>
        <h2 class="text-lg font-semibold">{{ __('Uitnodigingen') }}</h2>
        <p class="text-sm text-cmp-muted">
            {{ __('Bekijk je karma en genereer uitnodigingscodes voor nieuwe leden.') }}
        </p>
        <a href="{{ route('profile.invites') }}" class="mt-2 inline-block rounded border px-3 py-1 text-sm hover:bg-cmp-bg">
            {{ __('Uitnodigingen beheren') }}
        </a>
    </section>
    @endif

    @if (config('cloudmarktplaats.features.stats'))
    <section>
        <h2 class="text-lg font-semibold">{{ __('Statistieken') }}</h2>
        <p class="text-sm text-cmp-muted">
            {{ __('Bekijk je persoonlijke cijfers en verdiende badges.') }}
        </p>
        <a href="{{ route('profile.stats') }}" class="mt-2 inline-block rounded border px-3 py-1 text-sm hover:bg-cmp-bg">
            {{ __('Bekijk je statistieken') }}
        </a>
    </section>
    @endif

    @if (config('cloudmarktplaats.features.deals'))
    <section>
        <h2 class="text-lg font-semibold">{{ __('Mijn deals') }}</h2>
        <p class="text-sm text-cmp-muted">
            {{ __('Bevestig deals die een verkoper aan jou als koper heeft toegewezen.') }}
        </p>
        <a href="{{ route('profile.deals') }}" class="mt-2 inline-block rounded border px-3 py-1 text-sm hover:bg-cmp-bg">
            {{ __('Mijn deals') }}
        </a>
    </section>
    @endif
</div>
