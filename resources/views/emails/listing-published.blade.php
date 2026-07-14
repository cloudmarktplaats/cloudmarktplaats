<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Je advertentie staat live</title>
</head>
{{-- House style is the light datasheet. Fonts are system fallbacks: mail
     clients don't load our self-hosted woff2. --}}
<body style="margin:0;padding:0;background:#F5F6F6;color:#17191B;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;line-height:1.7;">
    <div style="max-width:560px;margin:0 auto;padding:32px 24px;">
        <p style="font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#D9480F;margin:0 0 24px;">
            cloudmarktplaats.nl
        </p>

        <div style="background:#FFFFFF;border:1px solid #D9DDDE;padding:24px;">
            <p style="margin:0 0 16px;">Hoi,</p>

            <p style="margin:0 0 16px;">
                Je advertentie <strong>{{ $title }}</strong> is goedgekeurd en staat live.
            </p>

            @if ($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $title }}" width="512" style="width:100%;max-width:512px;height:auto;border:1px solid #D9DDDE;margin:0 0 16px;">
            @endif

            <p style="margin:0 0 24px;">
                Deel 'm om de eerste reacties binnen te krijgen — op de pagina staan knoppen
                voor LinkedIn en MainDeck, en een kant-en-klare tekst om te kopiëren.
            </p>

            <p style="margin:0;">
                <a href="{{ $url }}" style="display:inline-block;background:#17191B;color:#FFFFFF;text-decoration:none;padding:12px 20px;font-weight:700;">
                    Bekijk &amp; deel je advertentie
                </a>
            </p>
        </div>

        <p style="margin:24px 0 0;font-size:13px;color:#5C6166;">
            Je krijgt deze mail omdat je een advertentie op Cloudmarktplaats hebt geplaatst.
        </p>
    </div>
</body>
</html>
