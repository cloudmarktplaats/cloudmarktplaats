<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bericht over je advertentie</title>
</head>
<body style="margin:0;padding:0;background:#0A0D14;color:#E8EDF8;font-family:'Space Grotesk',-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;line-height:1.7;">
    <div style="max-width:560px;margin:0 auto;padding:32px 24px;">
        <p style="font-family:'JetBrains Mono',ui-monospace,monospace;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#1A56FF;margin:0 0 24px;">
            cloudmarktplaats.nl
        </p>

        <p style="margin:0 0 16px;">Hoi,</p>

        <p style="margin:0 0 16px;">
            Iemand heeft via de site gereageerd op je advertentie
            <strong>{{ $title }}</strong>.
        </p>

        <div style="border-left:2px solid #1E2A45;padding:4px 0 4px 16px;margin:0 0 24px;color:#E8EDF8;white-space:pre-wrap;">{{ $body }}</div>

        <p style="margin:0 0 24px;color:#7B8DB0;font-size:14px;">
            Wil je reageren? Antwoord gewoon op deze mail — die gaat rechtstreeks
            naar de afzender. Vanaf dat punt loopt het contact buiten het platform om.
        </p>

        <p style="margin:0 0 24px;">
            <a href="{{ $url }}" style="color:#1A56FF;">Bekijk je advertentie</a>
        </p>

        <hr style="border:none;border-top:1px solid #1E2A45;margin:24px 0;">

        <p style="margin:0;color:#3A4560;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:11px;">
            We slaan de inhoud van dit bericht niet op. Geen trackers, geen cookiebanner, geen bullshit.
        </p>
    </div>
</body>
</html>
