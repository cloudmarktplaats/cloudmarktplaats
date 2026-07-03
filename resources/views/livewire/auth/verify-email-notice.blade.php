<div class="mx-auto max-w-md rounded-sm border border-cmp-border bg-cmp-surface p-6">
    <h1 class="mb-2 text-xl font-bold">Verifieer je email</h1>
    <p class="mb-4 text-sm">Klik op de link in de email om je account te activeren.</p>
    <button wire:click="resend" class="cmp-btn cmp-btn-primary">Verstuur opnieuw</button>
    @if($sent) <p class="mt-2 text-sm text-green-600">{{ $sent }}</p> @endif
</div>
