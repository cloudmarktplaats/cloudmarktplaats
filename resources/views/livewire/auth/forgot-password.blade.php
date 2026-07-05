<div class="mx-auto max-w-md rounded-sm border border-cmp-border bg-cmp-surface p-6">
    <h1 class="mb-4 text-xl font-bold">{{ __('Wachtwoord vergeten') }}</h1>
    <form wire:submit="submit" class="space-y-3">
        <input type="email" wire:model="email" placeholder="{{ __('email') }}" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button class="w-full cmp-btn cmp-btn-primary">{{ __('Verstuur reset-link') }}</button>
    </form>
    @if($status) <p class="mt-3 text-sm text-green-600">{{ $status }}</p> @endif
</div>
