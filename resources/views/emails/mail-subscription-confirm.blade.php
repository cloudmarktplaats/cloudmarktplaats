@php
    // Twee gedaanten in één mail. Bij een vers adres is dit de gewone dubbele
    // opt-in. Bij een geparkeerde wijziging op een al bevestigd adres kan de
    // aanvrager iemand anders zijn, en dan is "bevestig je aanmelding"
    // misleidend: de ontvanger staat er al op.
    $wijziging = $isWijziging && is_array($subscription->pending_changes) ? $subscription->pending_changes : null;
    $zin = $wijziging['consent_text'] ?? $subscription->consent_text;
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

                <p style="margin:0 0 8px;">Dit is wat er is aangevraagd:</p>
                <ul style="margin:0 0 16px;padding-left:20px;">
                    <li>{{ ($wijziging['wants_offers'] ?? false) ? 'Wel nieuw aanbod' : 'Geen nieuw aanbod' }}</li>
                    <li>{{ ($wijziging['wants_updates'] ?? false) ? 'Wel updates' : 'Geen updates' }}</li>
                    @if (! empty($wijziging['categories']))
                        <li>Categorieen: {{ implode(', ', $wijziging['categories']) }}</li>
                    @endif
                </ul>
            @else
                <p style="margin:0 0 16px;">
                    Je hebt je aangemeld voor mail van Cloudmarktplaats. Nog 1 klik en het staat vast.
                    Doe je niets, dan blijft alles zoals het is en sturen we niets.
                </p>
            @endif

            <p style="margin:0 0 16px;font-size:13px;color:#5C6166;">
                Dit is de zin waarop is aangeklikt:<br>
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

        <p style="margin:24px 0 0;font-size:13px;color:#5C6166;">
            Dit adres staat op de mailinglijst van Cloudmarktplaats.
            <a href="{{ $afmeldUrl }}" style="color:#D9480F;">Afmelden</a> kan altijd, in 1 klik.
        </p>
    </div>
</body>
</html>
