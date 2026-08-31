<x-layouts.marketing :title="__('Nieuwsbrief')">
    <section class="mx-auto max-w-xl px-5 py-16 sm:px-8 sm:py-20">
        <div class="cmp-section-label mb-4">{{ __('Nieuwsbrief') }}</div>

        @if ($actie === 'bevestigd')
            <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">{{ __('Je staat erop.') }}</h1>
            <p class="mt-4 text-cmp-text/90 text-[15px] leading-[1.75]">
                {{ __('Vanaf nu krijg je mail op dit adres. Afmelden kan met de link onderaan elk bericht.') }}
            </p>
        @elseif ($actie === 'hersteld')
            <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">{{ __('Hersteld.') }}</h1>
            <p class="mt-4 text-cmp-text/90 text-[15px] leading-[1.75]">
                {{ __('Je staat er weer op. Afmelden kan alsnog, met de link onderaan elk bericht.') }}
            </p>
        @else
            <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">{{ __('Je bent afgemeld.') }}</h1>
            <p class="mt-4 text-cmp-text/90 text-[15px] leading-[1.75]">
                {{ __('Er gaat geen mail meer naar dit adres. Was dat een vergissing?') }}
            </p>
            <div class="mt-6 flex gap-2">
                @foreach (['offers' => __('Toch nieuw aanbod'), 'updates' => __('Toch updates')] as $wat => $label)
                    <form method="POST" action="{{ route('mail.resubscribe', $abonnement->unsubscribe_token) }}">
                        @csrf
                        <input type="hidden" name="wat" value="{{ $wat }}">
                        <button class="rounded border px-3 py-2 text-sm">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.marketing>
