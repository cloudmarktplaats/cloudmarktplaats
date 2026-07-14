<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Je advertentie staat nog als concept</title>
</head>
{{-- Light datasheet house style. Fonts are system fallbacks: mail clients
     don't load our self-hosted woff2. --}}
<body style="margin:0;padding:0;background:#F5F6F6;color:#17191B;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;line-height:1.7;">
    <div style="max-width:560px;margin:0 auto;padding:32px 24px;">
        <p style="font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#D9480F;margin:0 0 24px;">
            cloudmarktplaats.nl
        </p>

        <div style="background:#FFFFFF;border:1px solid #D9DDDE;padding:24px;">
            <p style="margin:0 0 16px;">Hoi,</p>

            @if ($listings->count() === 1)
                <p style="margin:0 0 16px;">
                    Je advertentie <strong>{{ $listings->first()->title }}</strong> staat nog als
                    concept op Cloudmarktplaats. Dat lag niet aan jou: de foto-upload crashte
                    op foto's die met een telefoon zijn gemaakt. En omdat je zonder foto niet
                    kunt publiceren, liep je vast bij de laatste stap.
                </p>
            @else
                <p style="margin:0 0 16px;">
                    Deze advertenties staan nog als concept op Cloudmarktplaats:
                </p>
                <ul style="margin:0 0 16px;padding-left:20px;">
                    @foreach ($listings as $listing)
                        <li style="margin:0 0 4px;"><strong>{{ $listing->title }}</strong></li>
                    @endforeach
                </ul>
                <p style="margin:0 0 16px;">
                    Dat lag niet aan jou: de foto-upload crashte op foto's die met een telefoon
                    zijn gemaakt. En omdat je zonder foto niet kunt publiceren, liep je vast bij
                    de laatste stap.
                </p>
            @endif

            <p style="margin:0 0 24px;">
                Dat is gefixt. Voeg je foto opnieuw toe en je kunt 'm gewoon live zetten.
            </p>

            @foreach ($listings as $listing)
                <p style="margin:0 0 12px;">
                    {{-- Straight into the wizard on their own draft: the photo step is
                         the only thing left to do. --}}
                    <a href="{{ route('listings.edit', $listing) }}" style="display:inline-block;background:#17191B;color:#FFFFFF;text-decoration:none;padding:12px 20px;font-weight:700;">
                        @if ($listings->count() === 1)
                            Advertentie afmaken
                        @else
                            {{ $listing->title }} afmaken
                        @endif
                    </a>
                </p>
            @endforeach

            <p style="margin:24px 0 0;">
                Sorry voor de verspilde moeite — je was er duidelijk even mee bezig.
                Cloudmarktplaats is open source en nog jong; kom je meer bugs tegen, meld ze
                gerust via
                <a href="https://github.com/cloudmarktplaats/cloudmarktplaats/issues" style="color:#D9480F;">GitHub Issues</a>.
                Dat helpt echt.
            </p>

            <p style="margin:16px 0 0;">Nick</p>
        </div>

        <p style="margin:24px 0 0;font-size:13px;color:#5C6166;">
            Je krijgt deze mail omdat je een advertentie op Cloudmarktplaats bent begonnen.
        </p>
    </div>
</body>
</html>
