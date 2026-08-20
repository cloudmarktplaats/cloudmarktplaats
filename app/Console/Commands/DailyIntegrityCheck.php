<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\DailyIntegrityMail;
use App\Services\Ops\IntegrityReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Dagelijkse integriteitscheck: mailt wat er gebeurde en waar het stil bleef.
 *
 * Zonder trackers is er geen dashboard dat een storing meldt, en gebruikers
 * doen het evenmin — die verdwijnen zonder iets te zeggen. Zes dagen kapotte
 * foto-upload werd pas gevonden door toevallig de log te lezen.
 */
class DailyIntegrityCheck extends Command
{
    protected $signature = 'platform:daily-check {--show : Toon het rapport in de terminal en mail niets}';

    protected $description = 'Mail de dagelijkse integriteitscheck (fouten, cijfers, en verdachte stiltes)';

    public function handle(IntegrityReport $report): int
    {
        $rapport = $report->build(now());

        if ($this->option('show')) {
            $this->table(['meting', 'aantal'], collect($rapport['cijfers'])->map(fn ($v, $k) => [$k, $v])->values()->all());
            foreach ($rapport['signalen'] as $signaal) {
                $this->warn('! '.$signaal);
            }
            foreach ($rapport['fouten'] as $fout) {
                $this->line(sprintf('  %d× %s', $fout['aantal'], $fout['regel']));
            }
            if ($rapport['signalen'] === []) {
                $this->info('Geen signalen.');
            }

            return self::SUCCESS;
        }

        $to = (string) config('cloudmarktplaats.ops.digest_to', '');
        if ($to === '') {
            $this->warn('OPS_DIGEST_TO is niet gezet; er is niets verstuurd.');

            return self::SUCCESS;
        }

        Mail::to($to)->send(new DailyIntegrityMail(
            $rapport['cijfers'],
            $rapport['fouten'],
            $rapport['signalen'],
            now()->format('d-m-Y'),
        ));

        $this->info(sprintf('Digest verstuurd naar %s (%d signalen).', $to, count($rapport['signalen'])));

        return self::SUCCESS;
    }
}
