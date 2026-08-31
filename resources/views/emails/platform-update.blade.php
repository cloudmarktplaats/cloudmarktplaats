@php
    // De tekst komt uit een markdownbestand op de server, niet uit een
    // blade-template. `html_input => strip` houdt ruwe HTML eruit: die maakt de
    // mail per client anders stuk, en het houdt de weg dicht als er ooit iets
    // anders dan een met de hand geschreven bestand in belandt.
    $html = \Illuminate\Support\Str::markdown($tekst, [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ]);

    // Zonder `wat` zou deze link ook het aanbod afzetten, en daar gaat deze mail
    // niet over.
    $afmeldUrl = route('mail.unsubscribe', ['token' => $subscription->unsubscribe_token, 'wat' => 'updates']);
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update van Cloudmarktplaats</title>
</head>
{{-- Light datasheet house style; system fonts because mail clients don't load
     our self-hosted woff2. --}}
<body style="margin:0;padding:0;background:#F5F6F6;color:#17191B;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;line-height:1.7;">
    <div style="max-width:560px;margin:0 auto;padding:32px 24px;">
        <p style="font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#D9480F;margin:0 0 24px;">
            cloudmarktplaats.nl
        </p>

        <div style="background:#FFFFFF;border:1px solid #D9DDDE;padding:24px;">
            {!! $html !!}

            <p style="margin:24px 0 0;">
                <a href="{{ route('listings.index') }}" style="display:inline-block;background:#17191B;color:#FFFFFF;text-decoration:none;padding:12px 20px;font-weight:700;">
                    Bekijk het aanbod
                </a>
            </p>
        </div>

        <p style="margin:24px 0 0;font-size:13px;color:#5C6166;">
            Je krijgt deze mail omdat je je hebt aangemeld voor updates over het platform.
            Dat is hooguit 1 mail per 30 dagen, en dat staat in het verzendcommando zelf.
            <a href="{{ $afmeldUrl }}" style="color:#D9480F;">Afmelden</a> kan altijd, in 1 klik.
        </p>

        {{-- Art. 3:15d BW: wie je dit stuurt hoort in de mail te staan, met
             adres en KvK-nummer. Dezelfde gegevens als in de privacyverklaring
             (database/seeders/legal/privacy.nl.md); wijken ze af, dan klopt er
             1 van de twee niet. --}}
        <p style="margin:16px 0 0;font-size:13px;color:#5C6166;">
            Cloudmarktplaats is een dienst van Aldewereld Consultancy, Nieuwe Hemweg 26, 1013 CX Amsterdam, KvK 61862533.
        </p>
    </div>
</body>
</html>
