@php
    // Twee gedaanten in één mail. Bij een vers adres is dit de gewone dubbele
    // opt-in. Bij een geparkeerde wijziging op een al bevestigd adres kan de
    // aanvrager iemand anders zijn, en dan is "bevestig je aanmelding"
    // misleidend: de ontvanger staat er al op.
    $wijziging = $isWijziging && is_array($subscription->pending_changes) ? $subscription->pending_changes : null;
    $zin = $wijziging['consent_text'] ?? $subscription->consent_text;

    // In de wijzigingsgedaante staat de rij nog op de oude stand, dus wat er is
    // gevraagd komt uit het geparkeerde vak; in de andere gedaante uit de rij.
    $keuze = $wijziging ?? [
        'wants_offers' => $subscription->wants_offers,
        'wants_updates' => $subscription->wants_updates,
        'categories' => $subscription->categories,
    ];

    // De aanvrager zag labels op het scherm, dus die horen hier ook te staan.
    // Een slug die niet meer in de lijst voorkomt, valt terug op zichzelf.
    $labels = \App\Livewire\Mail\Subscribe::categoryLabels();
    $gekozen = array_map(fn ($slug) => $labels[$slug] ?? $slug, $keuze['categories'] ?? []);

    $url = route('mail.confirm', $subscription->confirm_token);
    $afmeldUrl = route('mail.unsubscribe', $subscription->unsubscribe_token);
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isWijziging ? 'Er is een wijziging aangevraagd' : 'Bevestig je aanmelding' }}</title>
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

            @if ($isWijziging)
                <p style="margin:0 0 16px;">
                    Iemand heeft een wijziging aangevraagd voor de mail die naar dit adres gaat.
                    Was jij dat niet, sluit deze mail dan gewoon. Doe je niets, dan blijft alles zoals het is.
                </p>
            @else
                <p style="margin:0 0 16px;">
                    Je hebt je aangemeld voor mail van Cloudmarktplaats. Nog 1 klik en het staat vast.
                    Doe je niets, dan blijft alles zoals het is en sturen we niets.
                </p>
            @endif

            <p style="margin:0 0 8px;">{{ $isWijziging ? 'Dit is wat er is aangevraagd:' : 'Dit heb je aangevinkt:' }}</p>
            <ul style="margin:0 0 16px;padding-left:20px;">
                <li>{{ ($keuze['wants_offers'] ?? false) ? 'Wel nieuw aanbod' : 'Geen nieuw aanbod' }}</li>
                <li>{{ ($keuze['wants_updates'] ?? false) ? 'Wel updates' : 'Geen updates' }}</li>
                @if ($gekozen !== [])
                    <li>Categorieën: {{ implode(', ', $gekozen) }}</li>
                @endif
            </ul>

            <p style="margin:0 0 16px;font-size:13px;color:#5C6166;">
                {{ $isWijziging ? 'Dit is de zin die de aanvrager heeft aangevinkt:' : 'Dit is de zin die je hebt aangevinkt:' }}<br>
                {{ $zin }}
            </p>

            <p style="margin:0 0 24px;">
                <a href="{{ $url }}" style="display:inline-block;background:#17191B;color:#FFFFFF;text-decoration:none;padding:12px 20px;font-weight:700;">
                    {{ $isWijziging ? 'Wijziging doorvoeren' : 'Ja, bevestigen' }}
                </a>
            </p>

            <p style="margin:0;font-size:13px;color:#5C6166;">
                Werkt de knop niet? Gebruik deze link:<br>
                <span style="font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace;font-size:12px;">{{ $url }}</span>
            </p>
        </div>

        {{-- Alleen in de wijzigingsgedaante staat het adres echt op de lijst. Bij
             een onbevestigde rij zou die zin de dubbele opt-in ondergraven: de
             ontvanger leest dan dat hij er al op staat en klikt de mail weg. --}}
        <p style="margin:24px 0 0;font-size:13px;color:#5C6166;">
            @if ($isWijziging)
                Dit adres staat op de mailinglijst van Cloudmarktplaats.
                <a href="{{ $afmeldUrl }}" style="color:#D9480F;">Afmelden</a> kan altijd, in 1 klik.
            @else
                Je krijgt deze mail omdat dit adres is opgegeven voor de mailinglijst van
                Cloudmarktplaats. Zolang je niet op de knop klikt, staat het er niet op.
                Wil je zeker weten dat je niets krijgt?
                <a href="{{ $afmeldUrl }}" style="color:#D9480F;">Meld dit adres dan af</a>.
            @endif
        </p>

        @include('emails.partials.afzender')
    </div>
</body>
</html>
