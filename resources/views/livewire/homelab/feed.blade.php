<div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">{{ __('Uit de homelabs') }}</div>
    <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">{{ __('Laat je lab zien.') }}</h1>
    <p class="mt-4 max-w-xl text-sm text-cmp-muted">
        {{ __('Eén foto, een korte beschrijving, volledig anoniem. Geen naam, geen profiel — alleen het rack. EXIF wordt gestript, zoals altijd.') }}
    </p>

    @error('upvote') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

    @auth
        @php
            $maxBytes = config('cloudmarktplaats.photos.max_bytes');
            $maxCount = config('cloudmarktplaats.photos.homelab_max_count');
            $maxMb = (int) ($maxBytes / 1024 / 1024);
        @endphp
        <form wire:submit="submit" class="mt-8 max-w-xl space-y-3 rounded-sm border border-cmp-border bg-cmp-surface p-5"
              x-data="{
                  bezig: false,
                  voortgang: 0,
                  probleem: '',
                  maxPerFoto: {{ $maxBytes }},
                  maxAantal: {{ $maxCount }},
                  keuze($event) {
                      this.probleem = '';
                      const fotos = [...$event.target.files];
                      const teGroot = fotos.filter(f => f.size > this.maxPerFoto);
                      if (fotos.length > this.maxAantal) {
                          this.probleem = @js(__('Je koos :n foto\'s. Er passen er maximaal :max in één homelab.', ['max' => $maxCount])).replace(':n', fotos.length);
                      } else if (teGroot.length) {
                          this.probleem = @js(__('Te groot: :namen. Maximaal :max MB per foto — verklein ze en probeer het opnieuw.', ['max' => $maxMb])).replace(':namen', teGroot.map(f => f.name).join(', '));
                      }
                  },
              }"
              x-on:livewire-upload-start="bezig = true; voortgang = 0"
              x-on:livewire-upload-progress="voortgang = $event.detail.progress"
              x-on:livewire-upload-finish="bezig = false; voortgang = 100"
              x-on:livewire-upload-cancel="bezig = false"
              x-on:livewire-upload-error="bezig = false; probleem = @js(__('Het uploaden is misgegaan. Vaak zijn de foto\'s samen te groot, of viel de verbinding weg. Probeer het opnieuw met minder of kleinere foto\'s.'))">

            @if (session('homelab-status'))
                <p class="font-mono text-sm text-cmp-signal">{{ session('homelab-status') }}</p>
            @endif

            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('Titel (optioneel)') }}</span>
                <input type="text" wire:model="title" maxlength="120"
                       class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            </label>
            @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('Vertel over je lab') }}</span>
                <textarea wire:model="body" rows="4" maxlength="500"
                          class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal"></textarea>
            </label>
            @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('Waar wil je feedback op? (optioneel)') }}</span>
                <input type="text" wire:model="feedbackPrompt" maxlength="280"
                       placeholder="{{ __('Bijv. idle-verbruik, kabelwerk, of je backup-strategie') }}"
                       class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            </label>
            @error('feedbackPrompt') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('Foto\'s (1–:max, max :mb MB elk)', ['max' => $maxCount, 'mb' => $maxMb]) }}</span>
                <input type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp"
                       x-on:change="keuze($event)"
                       class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            </label>

            <div x-show="bezig" x-cloak class="space-y-1" role="status" aria-live="polite">
                <div class="flex justify-between text-xs text-cmp-muted">
                    <span>{{ __('Foto\'s uploaden…') }}</span>
                    <span class="font-mono" x-text="voortgang + '%'"></span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-cmp-bg2">
                    <div class="h-full rounded-full bg-cmp-signal transition-all" x-bind:style="'width: ' + Math.max(2, voortgang) + '%'"></div>
                </div>
            </div>

            <p x-show="probleem" x-cloak x-text="probleem" class="text-sm text-red-600" role="alert"></p>
            @error('photos') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('photos.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <p class="text-xs text-cmp-muted">{{ __('EXIF (waaronder GPS) wordt automatisch verwijderd na uploaden.') }}</p>

            <button x-bind:disabled="bezig"
                    class="cmp-btn cmp-btn-primary disabled:cursor-not-allowed disabled:opacity-50">
                <span x-show="! bezig">{{ __('Plaatsen') }}</span>
                <span x-show="bezig" x-cloak>{{ __('Bezig met uploaden…') }}</span>
            </button>
        </form>
    @else
        <div class="mt-8 max-w-xl rounded-sm border border-dashed border-cmp-border bg-cmp-surface p-5">
            <p class="text-sm text-cmp-muted">
                <a href="{{ route('login') }}" class="text-cmp-blue underline hover:text-cmp-blue-dark">{{ __('Log in om jouw lab te tonen') }}</a>
                — {{ __('de feed toont nooit wie je bent.') }}
            </p>
        </div>
    @endauth

    @if ($posts->isEmpty())
        <div class="mt-12 rounded-sm border border-dashed border-cmp-border bg-cmp-surface px-6 py-16 text-center">
            <p class="font-display text-xl font-bold">{{ __('Nog geen labs. Die van jou kan de eerste zijn.') }}</p>
        </div>
    @else
        <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <article wire:key="homelab-{{ $post->ulid }}"
                         class="flex flex-col overflow-hidden rounded-sm border border-cmp-border bg-cmp-surface">
                    <div class="aspect-[4/3] overflow-hidden bg-cmp-bg2">
                        <img src="{{ $post->photoUrl('card') }}" alt="{{ __('Homelab-foto') }}" loading="lazy"
                             class="h-full w-full object-cover">
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <p class="text-sm text-cmp-text">{{ $post->body }}</p>
                        <div class="mt-auto flex items-center justify-between pt-1">
                            <span class="cmp-label-chip">{{ __('Homelab') }}</span>
                            <span class="font-mono text-[10px] text-cmp-faint">{{ $post->created_at->diffForHumans() }}</span>
                        </div>
                        @if (config('cloudmarktplaats.features.homelab_upvotes'))
                            <div class="flex items-center gap-2">
                                @auth
                                    <button type="button" wire:click="upvote('{{ $post->ulid }}')"
                                            @class([
                                                'inline-flex items-center gap-1 rounded-sm border px-2 py-0.5 font-mono text-[11px] transition-colors',
                                                'border-cmp-signal text-cmp-signal' => in_array($post->id, $upvotedIds, true),
                                                'border-cmp-border text-cmp-muted hover:border-cmp-ink hover:text-cmp-ink' => ! in_array($post->id, $upvotedIds, true),
                                            ])>
                                        ▲ {{ $post->upvotes_count }}
                                    </button>
                                @else
                                    <span class="inline-flex items-center gap-1 font-mono text-[11px] text-cmp-faint">▲ {{ $post->upvotes_count }}</span>
                                    <a href="{{ route('login') }}" class="text-[11px] text-cmp-blue underline hover:text-cmp-blue-dark">{{ __('log in om te waarderen') }}</a>
                                @endauth
                            </div>
                        @endif
                        @auth
                            @if ($post->user_id === auth()->id())
                                <button type="button"
                                        wire:click="deleteOwn('{{ $post->ulid }}')"
                                        wire:confirm="{{ __('Post verwijderen?') }}"
                                        class="self-start font-mono text-[10px] text-cmp-muted underline hover:text-cmp-amber">
                                    {{ __('Verwijder mijn post') }}
                                </button>
                            @endif

                            <details class="mt-1">
                                <summary class="cursor-pointer font-mono text-[10px] text-cmp-faint hover:text-cmp-amber">{{ __('Rapporteer') }}</summary>
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
            @endforeach
        </div>

        @if ($hasMore)
            <div x-data x-intersect.margin.400px="$wire.loadMore()" class="mt-10 flex justify-center">
                <button type="button" wire:click="loadMore" wire:loading.attr="disabled" class="cmp-btn cmp-btn-secondary">
                    <span wire:loading.remove wire:target="loadMore">{{ __('Meer laden') }}</span>
                    <span wire:loading wire:target="loadMore">{{ __('Laden…') }}</span>
                </button>
            </div>
        @endif
    @endif
</div>
