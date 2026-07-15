<div class="mx-auto max-w-2xl rounded-sm border border-cmp-border bg-cmp-surface p-6">
    <h1 class="mb-1 text-2xl font-bold">{{ $editing ? __('Advertentie bewerken') : __('Nieuwe advertentie') }}</h1>
    @if ($editing)
        <p class="mb-4 text-sm text-cmp-muted">{{ __('Na opslaan gaat je advertentie opnieuw langs moderatie en is zolang niet zichtbaar in het aanbod.') }}</p>
    @endif

    <ol class="mb-6 flex items-center gap-2 text-sm">
        <li class="rounded px-3 py-1 {{ $step === 1 ? 'bg-cmp-ink text-white' : 'bg-cmp-bg2 text-cmp-muted' }}">1. {{ __('Basis') }}</li>
        <li class="rounded px-3 py-1 {{ $step === 2 ? 'bg-cmp-ink text-white' : 'bg-cmp-bg2 text-cmp-muted' }}">2. {{ __('Details') }}</li>
        <li class="rounded px-3 py-1 {{ $step === 3 ? 'bg-cmp-ink text-white' : 'bg-cmp-bg2 text-cmp-muted' }}">3. {{ __("Foto's") }}</li>
    </ol>

    @if ($step === 1)
        <form wire:submit="next" class="space-y-3">
            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('Titel') }}</span>
                <input wire:model="title" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
            </label>
            @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('Categorie') }}</span>
                <select wire:model="category_id" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
                    <option value="">{{ __('— Kies een categorie —') }}</option>
                    @foreach ($categoryGroups as $groupLabel => $options)
                        <optgroup label="{{ $groupLabel }}">
                            @foreach ($options as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>
            @error('category_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('Staat') }}</span>
                <select wire:model="condition" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
                    <option value="new">{{ __('Nieuw') }}</option>
                    <option value="used">{{ __('Gebruikt') }}</option>
                    <option value="defective">{{ __('Defect') }}</option>
                    <option value="for_parts">{{ __('Voor onderdelen') }}</option>
                </select>
            </label>
            @error('condition') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('Prijs (in centen)') }}</span>
                <input wire:model="price_cents" type="number" min="0" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required>
            </label>
            @error('price_cents') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="flex items-center gap-2 text-sm">
                <input wire:model="is_trade_allowed" type="checkbox">
                <span>{{ __('Ruilen toegestaan') }}</span>
            </label>

            <button class="w-full cmp-btn cmp-btn-primary">{{ __('Volgende') }}</button>
        </form>
    @elseif ($step === 2)
        <form wire:submit="next" class="space-y-3">
            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('Beschrijving (markdown)') }}</span>
                <textarea wire:model="description" rows="8" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal" required></textarea>
            </label>
            @error('description') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label class="block text-sm">
                <span class="mb-1 block font-medium">{{ __('Postcode (4 cijfers)') }}</span>
                <input wire:model="region_postcode" maxlength="4" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            </label>
            @error('region_postcode') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <fieldset class="space-y-1 text-sm">
                <legend class="font-medium">{{ __('Verzendopties') }}</legend>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="shipping_pickup"> {{ __('Ophalen') }}
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="shipping_post"> {{ __('Verzending via post') }}
                </label>
            </fieldset>

            <div class="flex justify-between">
                <button type="button" wire:click="back" class="rounded border px-4 py-2">{{ __('Terug') }}</button>
                <button class="cmp-btn cmp-btn-primary">{{ __('Volgende') }}</button>
            </div>
        </form>
    @else
        @php
            $maxBytes = \App\Livewire\Listings\Wizard::maxPhotoBytes();
            $maxCount = \App\Livewire\Listings\Wizard::maxPhotoCount();
            $maxMb = (int) ($maxBytes / 1024 / 1024);
        @endphp
        {{-- An upload that fails is invisible: Livewire leaves $photos empty and
             says nothing, so the first sign was "photos is required" at submit —
             blaming the seller for photos they had picked. These handlers turn
             every failure mode into something you can read on screen. --}}
        <form wire:submit="submit" class="space-y-3" enctype="multipart/form-data"
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
                          this.probleem = @js(__('Je koos :n foto\'s. Er passen er maximaal :max in één advertentie.', ['max' => $maxCount])).replace(':n', fotos.length);
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
            @php($existingPhotoCount = $editing && $listing ? $listing->photos()->count() : 0)
            @if ($existingPhotoCount > 0)
                <p class="rounded-sm bg-cmp-bg2 p-3 text-sm text-cmp-muted">
                    {{ __(':count foto(\'s) blijven behouden. Nieuwe foto\'s toevoegen is optioneel.', ['count' => $existingPhotoCount]) }}
                </p>
            @endif
            <label class="block text-sm">
                <span class="mb-1 block font-medium">
                    {{ $existingPhotoCount > 0
                        ? __('Foto\'s toevoegen (optioneel, max :max)', ['max' => $maxCount])
                        : __('Foto\'s (1–:max, max :mb MB elk)', ['max' => $maxCount, 'mb' => $maxMb]) }}
                </span>
                <input type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp"
                       x-on:change="keuze($event)"
                       class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            </label>

            {{-- Uploading used to be silent: on a phone the only signal was the
                 page sitting there. --}}
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

            <div class="flex justify-between">
                <button type="button" wire:click="back" class="rounded border px-4 py-2">{{ __('Terug') }}</button>
                {{-- Livewire queues the submit until the upload finishes, so this
                     is about telling the seller why nothing happens yet. --}}
                <button x-bind:disabled="bezig"
                        class="rounded bg-green-600 px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-50">
                    <span x-show="! bezig">{{ __('Indienen voor moderatie') }}</span>
                    <span x-show="bezig" x-cloak>{{ __('Bezig met uploaden…') }}</span>
                </button>
            </div>
        </form>
    @endif
</div>
