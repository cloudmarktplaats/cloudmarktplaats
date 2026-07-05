<div class="mx-auto max-w-md rounded-sm border border-cmp-border bg-cmp-surface p-6">
    @if ($registrationOpen)
    <h1 class="mb-4 text-xl font-bold">{{ __('Account aanmaken') }}</h1>
    @if (config('cloudmarktplaats.features.waitlist') && $spotsLeft > 0)
        <p class="-mt-2 mb-4 font-mono text-[11px] text-cmp-signal">{{ $spotsLeft }} {{ __('van de 100 founding-plekken vrij') }}</p>
    @endif
    <form wire:submit="submit" class="space-y-3">
        <input type="email" wire:model="email" placeholder="{{ __('email') }}" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <input wire:model="username" placeholder="{{ __('username') }}" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        @error('username') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <p class="text-xs text-cmp-faint -mt-1">{{ __('Dit is je naam op het platform. Je kunt later in je profiel een aparte weergavenaam kiezen.') }}</p>

        <input type="password" wire:model="password" placeholder="{{ __('wachtwoord') }}" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <input type="password" wire:model="password_confirmation" placeholder="{{ __('herhaal wachtwoord') }}" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>

        <label class="flex items-start space-x-2 text-sm">
            <input type="checkbox" wire:model="accept_tos" class="mt-1">
            <span>{{ __('Ik accepteer de') }} <a href="/legal/tos" class="underline">{{ __('algemene voorwaarden') }}</a> {{ __('en') }} <a href="/legal/privacy" class="underline">{{ __('privacy policy') }}</a>.</span>
        </label>
        @error('accept_tos') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        @if (config('cloudmarktplaats.features.invites'))
            <input wire:model="invite_code" placeholder="{{ __('uitnodigingscode (optioneel)') }}" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            @error('invite_code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @endif

        <button class="w-full cmp-btn cmp-btn-primary">{{ __('Account aanmaken') }}</button>
    </form>

    @if (config('cloudmarktplaats.features.oauth_github') || config('cloudmarktplaats.features.oauth_gitlab'))
        <div class="my-4 flex items-center gap-3 text-sm text-cmp-muted">
            <span class="h-px flex-1 bg-cmp-bg2"></span>{{ __('of') }}<span class="h-px flex-1 bg-cmp-bg2"></span>
        </div>
        <div class="space-y-2">
            @if (config('cloudmarktplaats.features.oauth_github'))
                <a href="/oauth/github/redirect" class="block w-full rounded-sm border border-cmp-ink px-4 py-2 text-center font-medium hover:bg-cmp-ink hover:text-white transition-colors">{{ __('Registreren met GitHub') }}</a>
            @endif
            @if (config('cloudmarktplaats.features.oauth_gitlab'))
                <a href="/oauth/gitlab/redirect" class="block w-full rounded-sm border border-cmp-ink px-4 py-2 text-center font-medium hover:bg-cmp-ink hover:text-white transition-colors">{{ __('Registreren met GitLab') }}</a>
            @endif
        </div>
    @endif
    @else
        {{-- Founding cohort full: capture a waitlist email instead. --}}
        @if ($waitlisted)
            <h1 class="mb-2 text-xl font-bold">{{ __('Je staat op de wachtlijst') }}</h1>
            <p class="text-sm text-cmp-muted">{{ __('Zodra er een plek vrijkomt, krijg je als eerste een uitnodiging. Dank je wel!') }}</p>
        @else
            <div class="cmp-section-label mb-2">{{ __('Beta · de eerste 100') }}</div>
            <h1 class="mb-2 text-xl font-bold">{{ __('De beta zit vol') }}</h1>
            <p class="mb-4 text-sm text-cmp-muted">{{ __('De eerste 100 founding members zijn binnen. Laat je e-mail achter — je krijgt als eerste een uitnodiging zodra er een plek vrijkomt.') }}</p>
            <form wire:submit="joinWaitlist" class="space-y-3">
                <input type="email" wire:model="waitlist_email" placeholder="{{ __('email') }}" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
                @error('waitlist_email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button class="w-full cmp-btn cmp-btn-primary">{{ __('Zet me op de wachtlijst') }}</button>
            </form>
        @endif
    @endif
</div>
