<!DOCTYPE html>
<html lang="nl">
<head><meta charset="UTF-8"><title>Cloudmarktplaats dagcheck</title></head>
<body style="margin:0;padding:0;background:#F5F6F6;color:#17191B;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;line-height:1.7;">
<div style="max-width:560px;margin:0 auto;padding:32px 24px;">
    <p style="font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#D9480F;margin:0 0 24px;">
        cloudmarktplaats.nl · {{ $datum }}
    </p>

    @if ($signalen === [])
        <div style="background:#FFFFFF;border:1px solid #D9DDDE;padding:24px;">
            <p style="margin:0;">Niets aan de hand. Geen foutregels, geen mislukte jobs, en er is de afgelopen week gewoon geplaatst en geüpload.</p>
        </div>
    @else
        <div style="background:#FFFFFF;border:2px solid #D9480F;padding:24px;">
            <div style="font-family:ui-monospace,Menlo,monospace;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#D9480F;margin:0 0 12px;">Kijk hier even naar</div>
            <ul style="margin:0;padding-left:20px;">
                @foreach ($signalen as $signaal)
                    <li style="margin:0 0 6px;">{{ $signaal }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="background:#FFFFFF;border:1px solid #D9DDDE;padding:24px;margin-top:16px;">
        <div style="font-family:ui-monospace,Menlo,monospace;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#5C6166;margin:0 0 12px;">Afgelopen 24 uur</div>
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            @foreach (['nieuwe_leden' => 'Nieuwe leden', 'gepubliceerd' => 'Advertenties gepubliceerd', 'fotos' => "Foto's geüpload", 'contactverzoeken' => 'Contactverzoeken', 'verkopen_gemeld' => 'Verkopen gemeld', 'deals_bevestigd' => 'Deals bevestigd', 'mislukte_jobs' => 'Mislukte jobs (totaal)', 'concepten_zonder_foto' => 'Concepten zonder foto', 'nieuwsbrief_abonnees' => 'Nieuwsbrief-abonnees (totaal)', 'afmeldingen_afgelopen_week' => 'Afmeldingen (afgelopen week)'] as $sleutel => $label)
                <tr>
                    <td style="padding:4px 0;color:#5C6166;">{{ $label }}</td>
                    <td style="padding:4px 0;text-align:right;font-family:ui-monospace,Menlo,monospace;font-weight:700;">{{ $cijfers[$sleutel] ?? 0 }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    @if ($fouten !== [])
        <div style="background:#FFFFFF;border:1px solid #D9DDDE;padding:24px;margin-top:16px;">
            <div style="font-family:ui-monospace,Menlo,monospace;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#5C6166;margin:0 0 12px;">Foutregels</div>
            @foreach ($fouten as $fout)
                <p style="margin:0 0 8px;font-family:ui-monospace,Menlo,monospace;font-size:12px;">
                    <strong>{{ $fout['aantal'] }}×</strong> {{ $fout['regel'] }}
                </p>
            @endforeach
        </div>
    @endif

    <p style="margin:24px 0 0;font-size:13px;color:#5C6166;">
        Gebruikers melden geen kapotte site, die verdwijnen. Daarom telt deze mail ook wat er níet gebeurde.
    </p>
</div>
</body>
</html>
