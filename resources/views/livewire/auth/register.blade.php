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

    @if (config('cloudmarktplaats.features.oauth_github') || config('cloudmarktplaats.features.oauth_gitlab'))
        <div class="my-4 flex items-center gap-3 text-sm text-gray-500">
            <span class="h-px flex-1 bg-gray-200"></span>of<span class="h-px flex-1 bg-gray-200"></span>
        </div>
        <div class="space-y-2">
            @if (config('cloudmarktplaats.features.oauth_github'))
                <a href="/oauth/github/redirect" class="block w-full rounded border px-4 py-2 text-center hover:bg-gray-50">Registreren met GitHub</a>
            @endif
            @if (config('cloudmarktplaats.features.oauth_gitlab'))
                <a href="/oauth/gitlab/redirect" class="block w-full rounded border px-4 py-2 text-center hover:bg-gray-50">Registreren met GitLab</a>
            @endif
        </div>
    @endif
</div>
