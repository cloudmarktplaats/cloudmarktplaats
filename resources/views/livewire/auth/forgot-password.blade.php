<div class="mx-auto max-w-md rounded border bg-white p-6 shadow">
    <h1 class="mb-4 text-xl font-bold">Wachtwoord vergeten</h1>
    <form wire:submit="submit" class="space-y-3">
        <input type="email" wire:model="email" placeholder="email" class="w-full rounded border p-2" required>
        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button class="w-full rounded bg-blue-600 px-4 py-2 text-white">Verstuur reset-link</button>
    </form>
    @if($status) <p class="mt-3 text-sm text-green-600">{{ $status }}</p> @endif
</div>
