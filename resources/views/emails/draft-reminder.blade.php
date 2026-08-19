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
                    Je bent begonnen aan <strong>{{ $listings->first()->title }}</strong> en
                    nooit toegekomen aan het laatste zetje. Hij staat er nog, precies zoals je
                    hem achterliet.
                </p>
            @else
                <p style="margin:0 0 16px;">
                    Deze advertenties staan nog als concept op je account — je bent eraan
                    begonnen en nooit toegekomen aan het laatste zetje:
                </p>
                <ul style="margin:0 0 16px;padding-left:20px;">
                    {{-- Iemand die zes keer opnieuw begon, hoeft geen zes regels te lezen. --}}
                    @foreach ($listings->take(5) as $listing)
                        <li style="margin:0 0 4px;"><strong>{{ $listing->title }}</strong></li>
                    @endforeach
                    @if ($listings->count() > 5)
                        <li style="margin:0 0 4px;color:#5C6166;">en nog {{ $listings->count() - 5 }} andere</li>
                    @endif
                </ul>
            @endif

            <p style="margin:0 0 24px;">
                Er zijn nu meer mensen op zoek dan er spullen staan, dus de kans dat er iemand
                op wacht is groter dan je denkt. Afmaken kost een minuut.
            </p>

            @foreach ($listings->take(5) as $listing)
                <p style="margin:0 0 12px;">
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
                Liep je ergens op vast? Laat het weten, dan repareer ik het. Dat is niet
                beleefdheid: de vorige keer dat iemand bleef hangen bij het uploaden van een
                foto, bleek dat een bug van ons.
            </p>

            <p style="margin:16px 0 0;">Nick</p>
        </div>

        <p style="margin:24px 0 0;font-size:13px;color:#5C6166;">
            Je krijgt deze mail omdat je een advertentie op Cloudmarktplaats bent begonnen.
            Dit is de enige herinnering die je hierover krijgt — laat je 'm staan, dan horen
            we je niet meer hierover.
        </p>
    </div>
</body>
</html>
