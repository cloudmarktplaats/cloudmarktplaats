<div class="mx-auto max-w-2xl rounded-sm border border-cmp-border bg-cmp-surface p-6">
    <h1 class="mb-4 text-2xl font-bold">Nieuwe advertentie</h1>

    <ol class="mb-6 flex items-center gap-2 text-sm">
        <li class="rounded px-3 py-1 {{ $step === 1 ? 'bg-cmp-ink text-white' : 'bg-cmp-bg2 text-cmp-muted' }}">1. Basis</li>
        <li class="rounded px-3 py-1 {{ $step === 2 ? 'bg-cmp-ink text-white' : 'bg-cmp-bg2 text-cmp-muted' }}">2. Details</li>
        <li class="rounded px-3 py-1 {{ $step === 3 ? 'bg-cmp-ink text-white' : 'bg-cmp-bg2 text-cmp-muted' }}">3. Foto's</li>
    </ol>

    @if ($step === 1)
        <form wire:submit="next" class="space-y-3">
            <label class="block text-sm">
                <span class="mb-1 block font-medium">Titel</span>
                <input wire:model="title" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
            </label>
            @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">Categorie</span>
                <select wire:model="category_id" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
                    <option value="">— Kies een categorie —</option>
                    @foreach (\App\Models\Category::query()->where('is_active', true)->orderBy('name')->get() as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </label>
            @error('category_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">Staat</span>
                <select wire:model="condition" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
                    <option value="new">Nieuw</option>
                    <option value="used">Gebruikt</option>
                    <option value="defective">Defect</option>
                    <option value="for_parts">Voor onderdelen</option>
                </select>
            </label>
            @error('condition') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">Prijs (in centen)</span>
                <input wire:model="price_cents" type="number" min="0" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
            </label>
            @error('price_cents') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="flex items-center gap-2 text-sm">
                <input wire:model="is_trade_allowed" type="checkbox">
                <span>Ruilen toegestaan</span>
            </label>

            <button class="w-full cmp-btn cmp-btn-primary">Volgende</button>
        </form>
    @elseif ($step === 2)
        <form wire:submit="next" class="space-y-3">
            <label class="block text-sm">
                <span class="mb-1 block font-medium">Beschrijving (markdown)</span>
                <textarea wire:model="description" rows="8" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required></textarea>
            </label>
            @error('description') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">Postcode (4 cijfers)</span>
                <input wire:model="region_postcode" maxlength="4" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            </label>
            @error('region_postcode') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <fieldset class="space-y-1 text-sm">
                <legend class="font-medium">Verzendopties</legend>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="shipping_pickup"> Ophalen
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="shipping_post"> Verzending via post
                </label>
            </fieldset>

            <div class="flex justify-between">
                <button type="button" wire:click="back" class="rounded border px-4 py-2">Terug</button>
                <button class="cmp-btn cmp-btn-primary">Volgende</button>
            </div>
        </form>
    @else
        <form wire:submit="submit" class="space-y-3" enctype="multipart/form-data">
            <label class="block text-sm">
                <span class="mb-1 block font-medium">Foto's (1–10, max 8MB elk)</span>
                <input type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            </label>
            @error('photos') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('photos.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <p class="text-xs text-cmp-muted">EXIF (waaronder GPS) wordt automatisch verwijderd na uploaden.</p>

            <div class="flex justify-between">
                <button type="button" wire:click="back" class="rounded border px-4 py-2">Terug</button>
                <button class="rounded bg-green-600 px-4 py-2 text-white">Indienen voor moderatie</button>
            </div>
        </form>
    @endif
</div>
