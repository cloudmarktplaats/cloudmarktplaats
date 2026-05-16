<div class="mx-auto max-w-md rounded border bg-white p-6 shadow">
    <h1 class="mb-4 text-xl font-bold">Nieuw wachtwoord instellen</h1>
    <form wire:submit="submit" class="space-y-3">
        <input type="hidden" wire:model="token">
        <input type="email" wire:model="email" placeholder="email" class="w-full rounded border bg-gray-100 p-2" readonly>
        <input type="password" wire:model="password" placeholder="nieuw wachtwoord" class="w-full rounded border p-2" required>
        @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <input type="password" wire:model="password_confirmation" placeholder="herhaal wachtwoord" class="w-full rounded border p-2" required>
        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button class="w-full rounded bg-blue-600 px-4 py-2 text-white">Wachtwoord opslaan</button>
    </form>
</div>
