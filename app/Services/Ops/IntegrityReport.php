<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Wat er in 24 uur gebeurde, en waar het stil bleef.
 *
 * Gebruikers melden geen kapotte site — die verdwijnen. De foto-upload lag zes
 * dagen plat en werd pas gevonden doordat iemand toevallig de log las; de
 * verkoper die er zes keer op stukliep zei niets en kwam niet terug.
 *
 * Daarom telt dit rapport niet alleen fouten maar ook **afwezigheid**: nul
 * foto's in een week is hier een alarm, geen rustige week.
 */
class IntegrityReport
{
    /** Hoeveel bytes van de staart van de log we lezen. */
    private const LOG_TAIL_BYTES = 400_000;

    /** @return array{cijfers: array<string, int>, fouten: list<array{aantal: int, regel: string}>, signalen: list<string>} */
    public function build(Carbon $now): array
    {
        $since = $now->copy()->subDay();
        $silenceDays = (int) config('cloudmarktplaats.ops.silence_days', 7);
        $quiet = $now->copy()->subDays($silenceDays);

        $cijfers = [
            'nieuwe_leden' => User::query()->where('created_at', '>=', $since)->count(),
            'gepubliceerd' => Listing::query()->where('published_at', '>=', $since)->count(),
            'fotos' => ListingPhoto::query()->where('created_at', '>=', $since)->count(),
            'contactverzoeken' => DB::table('contact_relay_logs')->where('created_at', '>=', $since)->count(),
            'deals_bevestigd' => Transaction::query()->where('status', 'confirmed')->where('updated_at', '>=', $since)->count(),
            'mislukte_jobs' => DB::table('failed_jobs')->count(),
            'concepten_zonder_foto' => Listing::query()
                ->where('state', 'draft')
                ->where('updated_at', '<=', $since)
                ->whereDoesntHave('photos')
                ->count(),
        ];

        $fouten = $this->errorsSince($since);

        $signalen = [];
        if ($fouten !== []) {
            $signalen[] = sprintf('%d foutregel(s) in de log, zie hieronder.', array_sum(array_column($fouten, 'aantal')));
        }
        if ($cijfers['mislukte_jobs'] > 0) {
            $signalen[] = sprintf('%d mislukte job(s) in de wachtrij.', $cijfers['mislukte_jobs']);
        }

        // De stiltes. Dit is het deel dat de fotobug had gevangen.
        if (! ListingPhoto::query()->where('created_at', '>=', $quiet)->exists()) {
            $signalen[] = sprintf('Geen enkele foto geüpload in %d dagen — controleer of uploaden nog werkt.', $silenceDays);
        }
        if (! Listing::query()->where('published_at', '>=', $quiet)->exists()) {
            $signalen[] = sprintf('Geen enkele advertentie gepubliceerd in %d dagen.', $silenceDays);
        }
        if ($cijfers['concepten_zonder_foto'] > 0) {
            $signalen[] = sprintf(
                '%d concept(en) blijven hangen zonder foto — vaak het teken dat iemand vastliep bij het uploaden.',
                $cijfers['concepten_zonder_foto'],
            );
        }

        $vergeten = Transaction::query()->where('status', 'pending')->where('created_at', '<=', $quiet)->count();
        if ($vergeten > 0) {
            $signalen[] = sprintf('%d deal(s) wachten al langer dan %d dagen op bevestiging door de koper.', $vergeten, $silenceDays);
        }

        return ['cijfers' => $cijfers, 'fouten' => $fouten, 'signalen' => $signalen];
    }

    /**
     * Foutregels uit de staart van de log, gegroepeerd op boodschap.
     *
     * @return list<array{aantal: int, regel: string}>
     */
    private function errorsSince(Carbon $since): array
    {
        $path = (string) config('cloudmarktplaats.ops.log_path');
        if ($path === '' || ! is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        // Alleen de staart: de log groeit tot megabytes en de hele bak inlezen
        // om 24 uur te bekijken is verspilling.
        $size = (int) filesize($path);
        if ($size > self::LOG_TAIL_BYTES) {
            fseek($handle, -self::LOG_TAIL_BYTES, SEEK_END);
            fgets($handle); // halve eerste regel weggooien
        }

        $counts = [];
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/', $line, $m) !== 1) {
                continue;
            }
            if (Carbon::parse($m[1])->lt($since)) {
                continue;
            }
            // Stacktraces en context afkappen: de boodschap is het signaal.
            $message = trim(mb_substr(explode(' {"', $m[3])[0], 0, 160));
            $key = $m[2].': '.$message;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        fclose($handle);

        arsort($counts);
        $out = [];
        foreach (array_slice($counts, 0, 10, true) as $regel => $aantal) {
            $out[] = ['aantal' => $aantal, 'regel' => $regel];
        }

        return $out;
    }
}
