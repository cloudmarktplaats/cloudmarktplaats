<div class="mx-auto max-w-md rounded border bg-white p-6 shadow">
    <h1 class="mb-2 text-xl font-bold">Verifieer je email</h1>
    <p class="mb-4 text-sm">Klik op de link in de email om je account te activeren.</p>
    <button wire:click="resend" class="rounded bg-blue-600 px-4 py-2 text-white">Verstuur opnieuw</button>
    @if($sent) <p class="mt-2 text-sm text-green-600">{{ $sent }}</p> @endif
</div>
