<div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">Uit de homelabs</div>
    <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">Laat je lab zien.</h1>
    <p class="mt-4 max-w-xl text-sm text-cmp-muted">
        Eén foto, een korte beschrijving, volledig anoniem. Geen naam, geen profiel —
        alleen het rack. EXIF wordt gestript, zoals altijd.
    </p>

    @auth
        <form wire:submit="submit" class="mt-8 max-w-xl space-y-3 rounded-sm border border-cmp-border bg-cmp-surface p-5">
            @if (session('homelab-status'))
                <p class="font-mono text-sm text-cmp-signal">{{ session('homelab-status') }}</p>
            @endif

            <input type="file" wire:model="photo" accept=".jpg,.jpeg,.png,.webp"
                   class="w-full text-sm file:mr-3 file:cmp-btn file:cmp-btn-secondary file:cursor-pointer">
            @error('photo') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <textarea wire:model="body" rows="3" maxlength="500"
                      placeholder="Wat draait er, en waarom?"
                      class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal"></textarea>
            @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex items-center justify-between">
                <span class="font-mono text-[11px] text-cmp-faint">max 500 tekens · 1 post per dag · anoniem</span>
                <button class="cmp-btn cmp-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Post je lab</span>
                    <span wire:loading>Uploaden…</span>
                </button>
            </div>
        </form>
    @else
        <div class="mt-8 max-w-xl rounded-sm border border-dashed border-cmp-border bg-cmp-surface p-5">
            <p class="text-sm text-cmp-muted">
                <a href="{{ route('login') }}" class="text-cmp-blue underline hover:text-cmp-blue-dark">Log in om jouw lab te tonen</a>
                — de feed toont nooit wie je bent.
            </p>
        </div>
    @endauth

    @if ($posts->isEmpty())
        <div class="mt-12 rounded-sm border border-dashed border-cmp-border bg-cmp-surface px-6 py-16 text-center">
            <p class="font-display text-xl font-bold">Nog geen labs. Die van jou kan de eerste zijn.</p>
        </div>
    @else
        <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <article wire:key="homelab-{{ $post->ulid }}"
                         class="flex flex-col overflow-hidden rounded-sm border border-cmp-border bg-cmp-surface">
                    <div class="aspect-[4/3] overflow-hidden bg-cmp-bg2">
                        <img src="{{ $post->photoUrl('card') }}" alt="Homelab-foto" loading="lazy"
                             class="h-full w-full object-cover">
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <p class="text-sm text-cmp-text">{{ $post->body }}</p>
                        <div class="mt-auto flex items-center justify-between pt-1">
                            <span class="cmp-label-chip">Homelab</span>
                            <span class="font-mono text-[10px] text-cmp-faint">{{ $post->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if ($hasMore)
            <div x-data x-intersect.margin.400px="$wire.loadMore()" class="mt-10 flex justify-center">
                <button type="button" wire:click="loadMore" wire:loading.attr="disabled" class="cmp-btn cmp-btn-secondary">
                    <span wire:loading.remove wire:target="loadMore">Meer laden</span>
                    <span wire:loading wire:target="loadMore">Laden…</span>
                </button>
            </div>
        @endif
    @endif
</div>
