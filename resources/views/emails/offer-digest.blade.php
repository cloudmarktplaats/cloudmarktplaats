@php
    // De ontvanger vinkte labels aan, geen slugs, dus die labels horen hier ook
    // te staan. Een slug die niet meer in de lijst voorkomt valt terug op
    // zichzelf, zodat een oude keuze de mail niet stukmaakt.
    $labels = \App\Livewire\Mail\Subscribe::categoryLabels();

    // Alleen de categorieën waar deze mail echt iets uit toont. Alle aangevinkte
    // categorieën opsommen leest als een gemiste advertentie: "1 nieuwe
    // advertentie in Networking, Storage" met niets uit Storage eronder.
    // `subltree(path,0,1)` in de query snijdt hetzelfde bovenste label eraf.
    $aanwezig = $listings
        ->map(fn ($listing) => explode('.', (string) $listing->category?->path)[0])
        ->unique()
        ->all();
    $gekozen = array_map(
        fn ($slug) => $labels[$slug] ?? $slug,
        array_values(array_intersect($subscription->categories ?? [], $aanwezig)),
    );

    // Zonder `wat` zou deze link ook de updates afzetten, en daar gaat deze mail
    // niet over.
    $afmeldUrl = route('mail.unsubscribe', ['token' => $subscription->unsubscribe_token, 'wat' => 'offers']);
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nieuw aanbod</title>
</head>
{{-- Light datasheet house style; system fonts because mail clients don't load
     our self-hosted woff2. --}}
<body style="margin:0;padding:0;background:#F5F6F6;color:#17191B;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;line-height:1.7;">
    <div style="max-width:560px;margin:0 auto;padding:32px 24px;">
        <p style="font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#D9480F;margin:0 0 24px;">
            cloudmarktplaats.nl
        </p>

        <div style="background:#FFFFFF;border:1px solid #D9DDDE;padding:24px;">
            <p style="margin:0 0 16px;">Hoi,</p>

            <p style="margin:0 0 24px;">
                @if ($listings->count() === 1)
                    Er staat 1 nieuwe advertentie in
                @else
                    Er staan {{ $listings->count() }} nieuwe advertenties in
                @endif
                {{ count($gekozen) === 1 ? 'de categorie' : 'de categorieën' }}
                die je hebt aangevinkt{{ $gekozen === [] ? '.' : ': '.implode(', ', $gekozen).'.' }}
            </p>

            @foreach ($listings as $listing)
                <div style="border-top:1px solid #D9DDDE;padding:16px 0;">
                    <p style="margin:0 0 4px;">
                        <a href="{{ route('listings.detail', ['ulid' => $listing->ulid, 'slug' => $listing->slug]) }}" style="color:#17191B;font-weight:700;">
                            {{ $listing->title }}
                        </a>
                    </p>
                    <p style="margin:0;font-size:13px;color:#5C6166;">
                        € {{ number_format($listing->price_cents / 100, 2, ',', '.') }}
                        @if ($listing->category)
                            · {{ $listing->category->name }}
                        @endif
                    </p>
                </div>
            @endforeach

            <p style="margin:24px 0 0;">
                <a href="{{ route('listings.index') }}" style="display:inline-block;background:#17191B;color:#FFFFFF;text-decoration:none;padding:12px 20px;font-weight:700;">
                    Bekijk het hele aanbod
                </a>
            </p>

            {{-- De enige plek waar `user_id` iets doet. Wie geen account heeft
                 kan hier alles bekijken en verkopers gewoon mailen, dus dat is
                 niet wat een account toevoegt; zelf iets plaatsen wel. Wie er al
                 1 heeft leest hier niets: die weet het. --}}
            @if ($subscription->user_id === null)
                <p style="margin:24px 0 0;font-size:13px;color:#5C6166;">
                    Kijken en een verkoper mailen kan zonder account. Zelf iets plaatsen kan alleen met een account:
                    <a href="{{ route('register') }}" style="color:#D9480F;">registreren</a>.
                </p>
            @endif
        </div>

        <p style="margin:24px 0 0;font-size:13px;color:#5C6166;">
            Je krijgt deze mail omdat je je hebt aangemeld voor nieuw aanbod in deze categorieën.
            Is er niets nieuws, dan sturen we niets.
            <a href="{{ $afmeldUrl }}" style="color:#D9480F;">Afmelden</a> kan altijd, in 1 klik.
        </p>

        @include('emails.partials.afzender')
    </div>
</body>
</html>
