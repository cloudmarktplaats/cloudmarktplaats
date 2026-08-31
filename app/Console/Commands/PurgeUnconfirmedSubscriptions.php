<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Mail\MailSubscriptionService;
use Illuminate\Console\Command;

/**
 * Ruimt aanmeldingen op die nooit bevestigd zijn.
 *
 * Een adres dat wel is ingevuld maar nooit is bevestigd, is geen toestemming:
 * er is niets dat bewijst dat het adres van de invuller is. Zo'n rij bewaren
 * levert dus alleen een verzameling adressen op die we niet mogen gebruiken,
 * en dat is precies het soort voorraad dat je niet wilt hebben als er ooit
 * iemand in de database kijkt.
 *
 * Het venster staat in {@see MailSubscriptionService::purgeUnconfirmed()}: ruim
 * genoeg om een bevestigingsmail uit de spammap te vissen, kort genoeg om geen
 * lijst te worden.
 */
class PurgeUnconfirmedSubscriptions extends Command
{
    protected $signature = 'mail:purge-unconfirmed';

    protected $description = 'Verwijdert aanmeldingen op de mailinglijst die nooit bevestigd zijn';

    public function handle(MailSubscriptionService $subscriptions): int
    {
        $this->info(sprintf('%d onbevestigde aanmelding(en) verwijderd.', $subscriptions->purgeUnconfirmed()));

        return self::SUCCESS;
    }
}
