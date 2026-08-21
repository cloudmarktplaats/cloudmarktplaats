@props(['text', 'label' => null])

{{--
    Kopieerknop met een eerlijke terugval.

    De clipboard-API vereist een secure context. Productie is https en
    localhost telt als secure, dus dat is het normale pad; de catch is er voor
    de rest. We claimen nooit succes dat we niet kunnen vaststellen: mislukt
    het schrijven, dan onthullen we het invoerveld en selecteren we de tekst
    zodat de bezoeker zelf kan kopiëren.
--}}
<div
    x-data="{
        copied: false,
        failed: false,
        async copy() {
            const input = $refs.source;

            try {
                if (! navigator.clipboard) {
                    throw new Error('clipboard api unavailable');
                }
                await navigator.clipboard.writeText(input.value);
            } catch (error) {
                this.failed = true;
                input.classList.remove('sr-only');
                input.removeAttribute('aria-hidden');
                input.removeAttribute('tabindex');
                input.select();

                return;
            }

            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        },
    }"
    {{ $attributes->class(['flex flex-wrap items-center gap-2']) }}
>
    <button type="button" class="cmp-btn cmp-btn-ghost" @click="copy()">
        <span x-show="!copied">{{ $label ?? __('Kopieer') }}</span>
        <span x-show="copied" x-cloak>{{ __('Gekopieerd') }}</span>
    </button>

    {{-- Start verborgen; wordt alleen onthuld als het schrijven mislukt. --}}
    <input
        type="text"
        x-ref="source"
        value="{{ $text }}"
        readonly
        class="sr-only w-full font-mono text-xs sm:w-auto sm:flex-1"
        tabindex="-1"
        aria-hidden="true"
    >

    <p x-show="failed" x-cloak class="text-sm text-cmp-muted">
        {{ __('Kopiëren lukte niet — selecteer de tekst hierboven en kopieer zelf.') }}
    </p>
</div>
