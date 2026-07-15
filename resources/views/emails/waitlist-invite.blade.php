<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Je plek staat klaar</title>
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

            <p style="margin:0 0 16px;">
                Je stond op de wachtlijst van Cloudmarktplaats. Je plek staat klaar —
                hieronder je persoonlijke link.
            </p>

            <p style="margin:0 0 24px;">
                <a href="{{ $url }}" style="display:inline-block;background:#17191B;color:#FFFFFF;text-decoration:none;padding:12px 20px;font-weight:700;">
                    Account aanmaken
                </a>
            </p>

            <p style="margin:0 0 24px;font-size:13px;color:#5C6166;">
                Werkt de knop niet? Gebruik deze link:<br>
                <span style="font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace;font-size:12px;">{{ $url }}</span>
            </p>

            <p style="margin:0 0 16px;">
                Eerlijk erbij: het aanbod is nog dun. We zijn een paar dagen oud, en er zaten
                bugs in het plaatsen van advertenties — die zijn deze week gefixt. Dus als je
                iets in een doos hebt liggen: dat helpt op dit moment meer dan wat dan ook.
            </p>

            <p style="margin:0 0 16px;">
                Loop je ergens tegenaan? Meld het gerust via
                <a href="https://github.com/cloudmarktplaats/cloudmarktplaats/issues" style="color:#D9480F;">GitHub Issues</a>
                of stuur me een bericht. De serieuze bugs van deze week kwamen allemaal van
                gebruikers, niet uit onze eigen tests.
            </p>

            <p style="margin:16px 0 0;">Nick</p>
        </div>

        <p style="margin:24px 0 0;font-size:13px;color:#5C6166;">
            Je krijgt deze mail omdat je je op de wachtlijst van Cloudmarktplaats hebt gezet.
            De link is persoonlijk en eenmalig te gebruiken.
        </p>
    </div>
</body>
</html>
