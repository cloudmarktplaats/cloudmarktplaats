<div>
    <article class="mx-auto max-w-3xl px-5 py-10 sm:px-8">

        <div class="mb-2 flex items-baseline justify-between gap-4">
            <h1 class="text-2xl font-bold tracking-display-tight sm:text-3xl">{{ $this->heading() }}</h1>
            <span class="shrink-0 text-sm text-cmp-muted">{{ $post->created_at->diffForHumans() }}</span>
        </div>

        @php
            $lightboxPhotos = $post->photos->map(fn ($photo) => [
                'card' => $photo->urlFor('card'),
                'original' => $photo->urlFor('original'),
                'alt' => __('Homelab-foto'),
            ])->all();
        @endphp
        <div class="mb-6">
            <x-photo-lightbox :photos="$lightboxPhotos" :columns="2" />
        </div>

        <div class="prose prose-sm max-w-none whitespace-pre-line text-cmp-text">{{ $post->body }}</div>

        @if ($post->feedback_prompt)
            <div class="mt-6 rounded-sm border-2 border-cmp-ink bg-cmp-surface p-4">
                <div class="cmp-section-label mb-1">{{ __('De bouwer vraagt feedback op') }}</div>
                <p class="text-cmp-text">{{ $post->feedback_prompt }}</p>
            </div>
        @endif

        <div class="mt-6 flex items-center justify-between border-t border-cmp-border pt-4">
            <div class="font-mono text-sm text-cmp-muted">
                ▲ {{ number_format($post->upvotes()->count(), 0, ',', '.') }} {{ __('waarderingen') }}
            </div>

            @auth
                <details>
                    <summary class="cursor-pointer font-mono text-[10px] text-cmp-faint hover:text-cmp-amber">{{ __('Melden') }}</summary>
                    <form method="POST" action="{{ route('reports.homelab.store', $post->ulid) }}" class="mt-2 flex items-center gap-2">
                        @csrf
                        <select name="reason" class="rounded-sm border-cmp-border text-xs focus:border-cmp-signal focus:ring-cmp-signal">
                            <option value="illegal">{{ __('Illegaal') }}</option>
                            <option value="spam">{{ __('Spam') }}</option>
                            <option value="other" selected>{{ __('Anders') }}</option>
                        </select>
                        <button class="rounded-sm bg-cmp-ink px-2 py-1 text-[11px] text-white hover:bg-cmp-signal">{{ __('Verstuur') }}</button>
                    </form>
                </details>
            @endauth
        </div>
    </article>
</div>
