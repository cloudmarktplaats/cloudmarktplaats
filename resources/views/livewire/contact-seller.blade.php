<div class="rounded-sm border border-cmp-border bg-cmp-surface p-5 sm:p-6">
    @if ($sent)
        <div class="flex items-start gap-3" role="status">
            <svg class="mt-0.5 shrink-0 text-cmp-signal" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            <div>
                <p class="font-medium text-cmp-text">Bericht verstuurd.</p>
                <p class="mt-1 text-sm text-cmp-muted">
                    De verkoper kan je rechtstreeks per e-mail antwoorden. We bewaren de
                    inhoud van je bericht niet.
                </p>
            </div>
        </div>
    @else
        <h2 class="font-display text-lg font-bold tracking-display-tight">Stuur de verkoper een bericht</h2>
        <p class="mt-1 text-sm text-cmp-muted">
            Geen account nodig. De verkoper ziet jouw e-mailadres pas als je verstuurt,
            en kan daar rechtstreeks op antwoorden.
        </p>

        <form wire:submit="send" class="mt-5 space-y-4">
            {{-- Honeypot: hidden from humans, irresistible to bots. --}}
            <div class="hidden" aria-hidden="true">
                <label for="cs-website">Website (niet invullen)</label>
                <input type="text" id="cs-website" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label for="cs-email" class="block text-sm font-medium text-cmp-text">Jouw e-mailadres</label>
                <input
                    type="email"
                    id="cs-email"
                    wire:model="email"
                    required
                    autocomplete="email"
                    class="mt-1 block w-full rounded-md border-cmp-border bg-cmp-bg2 text-cmp-text placeholder:text-cmp-faint focus:border-cmp-blue focus:ring-cmp-blue"
                    placeholder="jij@voorbeeld.nl"
                >
                @error('email') <p class="mt-1 text-sm text-cmp-amber">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="cs-body" class="block text-sm font-medium text-cmp-text">Bericht</label>
                <textarea
                    id="cs-body"
                    wire:model="body"
                    rows="4"
                    required
                    minlength="10"
                    maxlength="2000"
                    class="mt-1 block w-full rounded-md border-cmp-border bg-cmp-bg2 text-cmp-text placeholder:text-cmp-faint focus:border-cmp-blue focus:ring-cmp-blue"
                    placeholder="Is dit nog beschikbaar? Kan ik het komen bekijken?"
                ></textarea>
                @error('body') <p class="mt-1 text-sm text-cmp-amber">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="cmp-btn cmp-btn-primary" wire:loading.attr="disabled" wire:target="send">
                <span wire:loading.remove wire:target="send">Verstuur bericht</span>
                <span wire:loading wire:target="send">Versturen…</span>
            </button>
        </form>
    @endif
</div>
