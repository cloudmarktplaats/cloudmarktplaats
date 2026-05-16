<div class="mx-auto max-w-md rounded border bg-white p-6 shadow">
    <h1 class="mb-4 text-xl font-bold">Inloggen</h1>
    <form wire:submit="submit" class="space-y-3">
        <input type="email" wire:model="email" placeholder="email" class="w-full rounded border p-2" required>
        <input type="password" wire:model="password" placeholder="wachtwoord" class="w-full rounded border p-2" required>
        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <label class="flex items-center space-x-2 text-sm"><input type="checkbox" wire:model="remember"> <span>Onthoud mij</span></label>
        <button class="w-full rounded bg-blue-600 px-4 py-2 text-white">Inloggen</button>
        <p class="text-sm"><a href="/forgot-password" class="underline">Wachtwoord vergeten?</a></p>
    </form>
</div>
