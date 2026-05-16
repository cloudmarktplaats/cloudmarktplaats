<div class="mx-auto max-w-md rounded border bg-white p-6 shadow">
    <h1 class="mb-4 text-xl font-bold">Account aanmaken</h1>
    <form wire:submit="submit" class="space-y-3">
        <input type="email" wire:model="email" placeholder="email" class="w-full rounded border p-2" required>
        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <input wire:model="username" placeholder="username" class="w-full rounded border p-2" required>
        @error('username') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <input wire:model="display_name" placeholder="weergavenaam" class="w-full rounded border p-2" required>
        @error('display_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <input type="password" wire:model="password" placeholder="wachtwoord" class="w-full rounded border p-2" required>
        @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <input type="password" wire:model="password_confirmation" placeholder="herhaal wachtwoord" class="w-full rounded border p-2" required>

        <label class="flex items-start space-x-2 text-sm">
            <input type="checkbox" wire:model="accept_tos" class="mt-1">
            <span>Ik accepteer de <a href="/legal/tos" class="underline">algemene voorwaarden</a> en <a href="/legal/privacy" class="underline">privacy policy</a>.</span>
        </label>
        @error('accept_tos') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <button class="w-full rounded bg-blue-600 px-4 py-2 text-white">Account aanmaken</button>
    </form>
</div>
