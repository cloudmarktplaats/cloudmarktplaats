@props(['photos' => [], 'columns' => 3])

@php
    // Geen dynamische Tailwind-classnamen (die purgt de build weg): map op literals.
    $gridCols = (int) $columns === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-3';
    $items = array_values($photos);
@endphp

@if (! empty($items))
    <div
        x-data="photoLightbox(@js($items))"
        x-on:keydown.window="onKey($event)"
    >
        {{-- Thumbnail-grid: card-variant, bijgesneden. Elke tegel is een knop. --}}
        <div class="grid grid-cols-1 gap-1 bg-cmp-bg2 {{ $gridCols }}">
            @foreach ($items as $i => $photo)
                <button
                    type="button"
                    @click="show({{ $i }}, $event)"
                    class="block aspect-[4/3] w-full overflow-hidden bg-cmp-bg2"
                    aria-label="{{ __('Foto :n van :total groter bekijken', ['n' => $i + 1, 'total' => count($items)]) }}"
                >
                    <img
                        src="{{ $photo['card'] }}"
                        alt="{{ $photo['alt'] }}"
                        loading="lazy"
                        class="h-full w-full object-cover transition-transform duration-200 hover:scale-105 motion-reduce:transition-none motion-reduce:hover:scale-100"
                    >
                </button>
            @endforeach
        </div>

        {{-- Overlay, geteleporteerd naar body zodat z-index/overflow van de
             pagina er niet mee bemoeit. --}}
        <template x-teleport="body">
            <div
                x-show="open"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 motion-reduce:transition-none"
                style="display: none;"
            >
                <div
                    x-ref="dialog"
                    x-trap.noscroll="open"
                    tabindex="-1"
                    role="dialog"
                    aria-modal="true"
                    aria-label="{{ __('Fotoweergave') }}"
                    class="relative flex h-full w-full items-center justify-center overflow-hidden"
                    @pointerdown="onPointerDown($event)"
                    @pointermove="onPointerMove($event)"
                    @pointerup="onPointerUp($event)"
                    @pointercancel="onPointerUp($event)"
                >
                    {{-- De foto: original, lui geladen (src pas gezet als open). --}}
                    <img
                        x-show="open"
                        :src="open ? current?.original : null"
                        :alt="current?.alt"
                        @wheel.prevent="onWheel($event)"
                        @dblclick="toggleZoom()"
                        :style="`transform: translate(${tx}px, ${ty}px) scale(${scale}); cursor: ${scale > 1 ? 'grab' : 'zoom-in'};`"
                        class="max-h-full max-w-full touch-pinch-zoom select-none object-contain"
                        draggable="false"
                    >

                    {{-- Sluiten --}}
                    <button
                        type="button"
                        @click="close()"
                        aria-label="{{ __('Sluiten') }}"
                        class="absolute right-4 top-4 rounded-full bg-black/50 p-2 text-white hover:bg-black/70"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>

                    {{-- Teller --}}
                    <div
                        x-show="hasMultiple"
                        class="absolute left-1/2 top-4 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1 text-sm text-white"
                        x-text="`${index + 1} / ${photos.length}`"
                    ></div>

                    {{-- Chevrons --}}
                    <button
                        type="button"
                        x-show="hasMultiple"
                        @click="prev()"
                        aria-label="{{ __('Vorige foto') }}"
                        class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button
                        type="button"
                        x-show="hasMultiple"
                        @click="next()"
                        aria-label="{{ __('Volgende foto') }}"
                        class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </template>
    </div>
@endif
