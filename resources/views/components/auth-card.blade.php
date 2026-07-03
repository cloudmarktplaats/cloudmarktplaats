@props(['title' => null])
<div class="mx-auto max-w-md rounded-sm border border-cmp-border bg-cmp-surface p-6">
    @if($title)
        <h1 class="mb-4 text-xl font-bold">{{ $title }}</h1>
    @endif
    {{ $slot }}
</div>
