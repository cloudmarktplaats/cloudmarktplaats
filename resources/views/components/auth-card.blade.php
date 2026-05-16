@props(['title' => null])
<div class="mx-auto max-w-md rounded border bg-white p-6 shadow">
    @if($title)
        <h1 class="mb-4 text-xl font-bold">{{ $title }}</h1>
    @endif
    {{ $slot }}
</div>
