<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Je account werkt weer</title>
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
                Je hebt je met GitHub aangemeld op Cloudmarktplaats, en daarna kon je
                waarschijnlijk niets: geen advertentie plaatsen, geen uitnodiging versturen.
                Misschien stond er dat je je e-mailadres moest bevestigen, terwijl je nooit
                een mail kreeg.
            </p>

            <p style="margin:0 0 16px;">
                Dat lag niet aan jou. Er zat een fout in onze code waardoor accounts die via
                GitHub binnenkomen als "onbevestigd" bleven staan — terwijl GitHub je adres
                allang had bevestigd. En omdat er via die route ook geen bevestigingsmail
                werd verstuurd, kon je er zelf niets aan doen.
            </p>

            <p style="margin:0 0 24px;">
                Het is gefixt en je account is hersteld. Je kunt gewoon inloggen met GitHub
                en meteen aan de slag.
            </p>

            <p style="margin:0 0 24px;">
                <a href="{{ $newListingUrl }}" style="display:inline-block;background:#17191B;color:#FFFFFF;text-decoration:none;padding:12px 20px;font-weight:700;">
                    Advertentie plaatsen
                </a>
            </p>

            <p style="margin:0 0 16px;">
                Sorry voor de verspilde moeite — en dank dat je het geprobeerd hebt. We zijn
                een paar dagen oud en dit soort dingen komen we alleen op het spoor doordat
                mensen zoals jij het proberen.
            </p>

            <p style="margin:0 0 16px;">
                Loop je ergens tegenaan? Meld het gerust via
                <a href="https://github.com/cloudmarktplaats/cloudmarktplaats/issues" style="color:#D9480F;">GitHub Issues</a>
                of stuur me een bericht.
            </p>

            <p style="margin:16px 0 0;">Nick</p>
        </div>

        <p style="margin:24px 0 0;font-size:13px;color:#5C6166;">
            Je krijgt deze mail omdat je een account op Cloudmarktplaats hebt aangemaakt via GitHub.
        </p>
    </div>
</body>
</html>
