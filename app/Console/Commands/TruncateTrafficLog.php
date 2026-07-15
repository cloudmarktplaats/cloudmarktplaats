<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Empties the nginx access log.
 *
 * Truncate, not rename: nginx keeps the file handle open, so after a `mv` it
 * would keep writing to the old inode until it gets a USR1 — the log would look
 * empty while traffic was still flowing. `> file` keeps the handle valid.
 *
 * There is no privacy reason to rotate (the log holds no IP — see
 * docker/nginx/default.conf); this is purely about disk.
 */
class TruncateTrafficLog extends Command
{
    protected $signature = 'traffic:truncate-log';

    protected $description = 'Leegt de nginx access log (schijfruimte; bevat geen persoonsgegevens)';

    public function handle(): int
    {
        $path = (string) config('cloudmarktplaats.traffic.access_log');

        if (! file_exists($path)) {
            $this->info('Geen logbestand — niets te doen.');

            return self::SUCCESS;
        }

        $before = (int) filesize($path);
        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->error("Kan {$path} niet legen.");

            return self::FAILURE;
        }
        fclose($handle);

        $this->info(sprintf('access.log geleegd (%d KB vrijgemaakt).', (int) round($before / 1024)));

        return self::SUCCESS;
    }
}
