<div>
    @if ($posts->isNotEmpty())
        <section aria-labelledby="homelab-heading">
            <div class="flex items-end justify-between mb-6">
                <div>
                    <div class="cmp-section-label mb-3">Community</div>
                    <h2 id="homelab-heading" class="text-2xl font-bold tracking-display-tight">Uit de homelabs</h2>
                </div>
                <a href="{{ route('homelabs') }}" class="hidden sm:inline text-sm text-cmp-muted hover:text-cmp-ink">
                    Alle labs →
                </a>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach ($posts as $post)
                    <article wire:key="homelab-recent-{{ $post->ulid }}"
                             class="flex flex-col overflow-hidden rounded-sm border border-cmp-border bg-cmp-surface">
                        <div class="aspect-[4/3] overflow-hidden bg-cmp-bg2">
                            <img src="{{ $post->photoUrl('card') }}" alt="Homelab-foto" loading="lazy"
                                 class="h-full w-full object-cover">
                        </div>
                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <p class="line-clamp-2 text-sm text-cmp-text">{{ $post->body }}</p>
                            <div class="mt-auto flex items-center justify-between pt-1">
                                <span class="cmp-label-chip">Homelab</span>
                                <span class="font-mono text-[10px] text-cmp-faint">{{ $post->created_at->diffForHumans() }}</span>
                            </div>
                            @if (config('cloudmarktplaats.features.homelab_upvotes'))
                                <span class="font-mono text-[10px] text-cmp-faint">▲ {{ $post->upvotes_count }}</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</div>
