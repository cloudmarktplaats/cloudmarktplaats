<div class="mx-auto max-w-md rounded-sm border border-cmp-border bg-cmp-surface p-6">
    <h1 class="mb-4 text-xl font-bold">Nieuw wachtwoord instellen</h1>
    <form wire:submit="submit" class="space-y-3">
        <input type="hidden" wire:model="token">
        <input type="email" wire:model="email" placeholder="email" class="w-full rounded border bg-cmp-bg2 p-2" readonly>
        <input type="password" wire:model="password" placeholder="nieuw wachtwoord" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <input type="password" wire:model="password_confirmation" placeholder="herhaal wachtwoord" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button class="w-full cmp-btn cmp-btn-primary">Wachtwoord opslaan</button>
    </form>
</div>
