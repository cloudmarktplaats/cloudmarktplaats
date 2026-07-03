{{-- Thermal-printed inventory label — the signature element (docs/DESIGN.md).
     `rows` is an ordered [label => value] map; `highlight` (optional) names the
     row whose value gets the inverted ink block (typically the condition). --}}
@props([
    'title'     => 'CLOUDMARKTPLAATS.NL',
    'rows'      => [],
    'highlight' => null,
])
<div {{ $attributes->merge(['class' => 'cmp-label']) }}>
    <div class="border-b-2 border-cmp-ink px-3 py-1.5 text-[10px] tracking-[0.14em]">
        {{ $title }}
    </div>
    <dl class="px-3 py-2 space-y-1">
        @foreach ($rows as $label => $value)
            <div class="flex items-baseline justify-between gap-4 text-[11px]">
                <dt class="tracking-[0.08em] text-cmp-muted">{{ $label }}</dt>
                @if ($highlight === $label)
                    <dd class="bg-cmp-ink px-1.5 py-0.5 font-medium tracking-[0.08em] text-white">{{ $value }}</dd>
                @else
                    <dd class="font-medium tracking-[0.04em] text-right">{{ $value }}</dd>
                @endif
            </div>
        @endforeach
    </dl>
    <div class="cmp-label-barcode mx-3 mb-2" aria-hidden="true"></div>
</div>
