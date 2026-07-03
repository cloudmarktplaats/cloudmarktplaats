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

    @if (config('cloudmarktplaats.features.oauth_github') || config('cloudmarktplaats.features.oauth_gitlab'))
        <div class="my-4 flex items-center gap-3 text-sm text-gray-500">
            <span class="h-px flex-1 bg-gray-200"></span>of<span class="h-px flex-1 bg-gray-200"></span>
        </div>
        <div class="space-y-2">
            @if (config('cloudmarktplaats.features.oauth_github'))
                <a href="/oauth/github/redirect" class="block w-full rounded border px-4 py-2 text-center hover:bg-gray-50">Inloggen met GitHub</a>
            @endif
            @if (config('cloudmarktplaats.features.oauth_gitlab'))
                <a href="/oauth/gitlab/redirect" class="block w-full rounded border px-4 py-2 text-center hover:bg-gray-50">Inloggen met GitLab</a>
            @endif
        </div>
    @endif
</div>
