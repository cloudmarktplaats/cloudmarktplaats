<div class="mx-auto max-w-md rounded-sm border border-cmp-border bg-cmp-surface p-6">
    <h1 class="mb-4 text-xl font-bold">{{ __('Inloggen') }}</h1>
    <form wire:submit="submit" class="space-y-3">
        <input type="email" wire:model="email" placeholder="{{ __('email') }}" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        <input type="password" wire:model="password" placeholder="{{ __('wachtwoord') }}" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <label class="flex items-center space-x-2 text-sm"><input type="checkbox" wire:model="remember"> <span>{{ __('Onthoud mij') }}</span></label>
        <button class="w-full cmp-btn cmp-btn-primary">{{ __('Inloggen') }}</button>
        <p class="text-sm"><a href="/forgot-password" class="underline">{{ __('Wachtwoord vergeten?') }}</a></p>
    </form>

    @if (config('cloudmarktplaats.features.oauth_github') || config('cloudmarktplaats.features.oauth_gitlab'))
        <div class="my-4 flex items-center gap-3 text-sm text-cmp-muted">
            <span class="h-px flex-1 bg-cmp-bg2"></span>{{ __('of') }}<span class="h-px flex-1 bg-cmp-bg2"></span>
        </div>
        <div class="space-y-2">
            @if (config('cloudmarktplaats.features.oauth_github'))
                <a href="/oauth/github/redirect" class="block w-full rounded-sm border border-cmp-ink px-4 py-2 text-center font-medium hover:bg-cmp-ink hover:text-white transition-colors">{{ __('Inloggen met GitHub') }}</a>
            @endif
            @if (config('cloudmarktplaats.features.oauth_gitlab'))
                <a href="/oauth/gitlab/redirect" class="block w-full rounded-sm border border-cmp-ink px-4 py-2 text-center font-medium hover:bg-cmp-ink hover:text-white transition-colors">{{ __('Inloggen met GitLab') }}</a>
            @endif
        </div>
    @endif
</div>
