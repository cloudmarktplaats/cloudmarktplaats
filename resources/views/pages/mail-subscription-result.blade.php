@php
    // Expliciete canonical, want de layout valt anders terug op url()->current()
    // en die URL draagt hier een levend token. Dat zou het token in
    // <link rel="canonical"> en og:url zetten: de twee velden die juist bedoeld
    // zijn om door te geven aan de rest van de wereld.
    //
    // Vlagbewust, want afmelden werkt ook als `features.mail_list` uit staat en
    // dat moet ook: art. 11.7 lid 4 Tw geldt net zo goed voor mail die al
    // verstuurd is toen de noodrem nog open stond. /nieuwsbrief bestaat dan
    // niet, en een canonical naar een 404 verwijst naar niets.
    $canonical = config('cloudmarktplaats.features.mail_list') ? url('/nieuwsbrief') : url('/');
@endphp
<x-layouts.marketing :title="__('Nieuwsbrief')" :canonical="$canonical">
    <section class="mx-auto max-w-xl px-5 py-16 sm:px-8 sm:py-20">
        <div class="cmp-section-label mb-4">{{ __('Nieuwsbrief') }}</div>

        @if ($actie === 'bevestigen')
            @php
                // Een geparkeerde wijziging kan van een vreemde komen (geval 4 in
                // MailSubscriptionService::write()). Laat daarom zien wat er staat
                // te gebeuren voordat er iets gebeurt.
                $wijziging = is_array($abonnement->pending_changes) ? $abonnement->pending_changes : null;
            @endphp

            <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">{{ __('Nog 1 klik.') }}</h1>
            <p class="mt-4 text-cmp-text/90 text-[15px] leading-[1.75]">
                {{ __('Je bevestigt hiermee dat wij mail mogen sturen naar :adres. Zolang je niet klikt, gebeurt er niets.', ['adres' => $abonnement->email]) }}
            </p>

            @if ($wijziging)
                <p class="mt-4 text-cmp-text/90 text-[15px] leading-[1.75]">
                    {{ __('Er staat een wijziging klaar voor dit adres. Zo komt het eruit te zien:') }}
                </p>
                <ul class="mt-2 list-disc pl-5 text-cmp-text/90 text-[15px] leading-[1.75]">
                    <li>{{ ($wijziging['wants_offers'] ?? false) ? __('Wel nieuw aanbod') : __('Geen nieuw aanbod') }}</li>
                    <li>{{ ($wijziging['wants_updates'] ?? false) ? __('Wel updates') : __('Geen updates') }}</li>
                </ul>
            @endif

            <form method="POST" action="{{ route('mail.confirm.apply', $abonnement->confirm_token) }}" class="mt-6">
                @csrf
                <button class="cmp-btn cmp-btn-primary">{{ __('Ja, bevestigen') }}</button>
            </form>
        @elseif ($actie === 'bevestigd')
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
            @php
                // Bij `?wat=offers` blijft de andere soort mail gewoon staan. De
                // pagina vertelt daarom de werkelijke stand van de rij, anders
                // belooft hij een stilte die niet komt en biedt hij herstel aan
                // voor iets dat nooit uit is gegaan.
                $aanbodUit = ! $abonnement->wants_offers;
                $updatesUit = ! $abonnement->wants_updates;
            @endphp

            <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">
                {{ $aanbodUit && $updatesUit ? __('Je bent afgemeld.') : __('Dat is aangepast.') }}
            </h1>
            <p class="mt-4 text-cmp-text/90 text-[15px] leading-[1.75]">
                @if ($aanbodUit && $updatesUit)
                    {{ __('Er gaat geen mail meer naar dit adres. Was dat een vergissing?') }}
                @elseif ($aanbodUit)
                    {{ __('Je krijgt geen nieuw aanbod meer op dit adres. Updates blijven wel komen. Was dat een vergissing?') }}
                @else
                    {{ __('Je krijgt geen updates meer op dit adres. Nieuw aanbod blijft wel komen. Was dat een vergissing?') }}
                @endif
            </p>
            <div class="mt-6 flex gap-2">
                @if ($aanbodUit)
                    <form method="POST" action="{{ route('mail.resubscribe', $abonnement->unsubscribe_token) }}">
                        @csrf
                        <input type="hidden" name="wat" value="offers">
                        <button class="cmp-btn cmp-btn-secondary">{{ __('Toch nieuw aanbod') }}</button>
                    </form>
                @endif
                @if ($updatesUit)
                    <form method="POST" action="{{ route('mail.resubscribe', $abonnement->unsubscribe_token) }}">
                        @csrf
                        <input type="hidden" name="wat" value="updates">
                        <button class="cmp-btn cmp-btn-secondary">{{ __('Toch updates') }}</button>
                    </form>
                @endif
            </div>
        @endif
    </section>
</x-layouts.marketing>
