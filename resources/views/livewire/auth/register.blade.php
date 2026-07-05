<div class="mx-auto max-w-md rounded-sm border border-cmp-border bg-cmp-surface p-6">
    <h1 class="mb-4 text-xl font-bold">Account aanmaken</h1>
    <form wire:submit="submit" class="space-y-3">
        <input type="email" wire:model="email" placeholder="email" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <input wire:model="username" placeholder="username" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        @error('username') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <p class="text-xs text-cmp-faint -mt-1">Dit is je naam op het platform. Je kunt later in je profiel een aparte weergavenaam kiezen.</p>

        <input type="password" wire:model="password" placeholder="wachtwoord" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <input type="password" wire:model="password_confirmation" placeholder="herhaal wachtwoord" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>

        <label class="flex items-start space-x-2 text-sm">
            <input type="checkbox" wire:model="accept_tos" class="mt-1">
            <span>Ik accepteer de <a href="/legal/tos" class="underline">algemene voorwaarden</a> en <a href="/legal/privacy" class="underline">privacy policy</a>.</span>
        </label>
        @error('accept_tos') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        @if (config('cloudmarktplaats.features.invites'))
            <input wire:model="invite_code" placeholder="uitnodigingscode (optioneel)" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            @error('invite_code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @endif

        <button class="w-full cmp-btn cmp-btn-primary">Account aanmaken</button>
    </form>

    @if (config('cloudmarktplaats.features.oauth_github') || config('cloudmarktplaats.features.oauth_gitlab'))
        <div class="my-4 flex items-center gap-3 text-sm text-cmp-muted">
            <span class="h-px flex-1 bg-cmp-bg2"></span>of<span class="h-px flex-1 bg-cmp-bg2"></span>
        </div>
        <div class="space-y-2">
            @if (config('cloudmarktplaats.features.oauth_github'))
                <a href="/oauth/github/redirect" class="block w-full rounded-sm border border-cmp-ink px-4 py-2 text-center font-medium hover:bg-cmp-ink hover:text-white transition-colors">Registreren met GitHub</a>
            @endif
            @if (config('cloudmarktplaats.features.oauth_gitlab'))
                <a href="/oauth/gitlab/redirect" class="block w-full rounded-sm border border-cmp-ink px-4 py-2 text-center font-medium hover:bg-cmp-ink hover:text-white transition-colors">Registreren met GitLab</a>
            @endif
        </div>
    @endif
</div>
