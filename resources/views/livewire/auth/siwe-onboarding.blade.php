<div class="mx-auto max-w-md rounded-sm border border-cmp-border bg-cmp-surface p-6">
    <h1 class="mb-2 text-xl font-bold">{{ __('Welkom op Cloudmarktplaats') }}</h1>
    <p class="mb-4 text-sm text-cmp-muted">
        {{ __('Je wallet') }} <code class="rounded bg-cmp-bg2 px-1">{{ $address }}</code> {{ __('is bekend. Maak je account af om in te loggen.') }}
    </p>

    <form wire:submit="submit" class="space-y-3">
        <input type="email" wire:model="email" placeholder="{{ __('email') }}"
               class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <input wire:model="username" placeholder="{{ __('username') }}"
               class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        @error('username') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <label class="flex items-start space-x-2 text-sm">
            <input type="checkbox" wire:model="accept_tos" class="mt-1">
            <span>{{ __('Ik accepteer de') }} <a href="/legal/tos" class="underline">{{ __('algemene voorwaarden') }}</a> {{ __('en') }} <a href="/legal/privacy" class="underline">{{ __('privacy policy') }}</a>.</span>
        </label>
        @error('accept_tos') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <button class="w-full cmp-btn cmp-btn-primary">{{ __('Account aanmaken') }}</button>
    </form>
</div>
