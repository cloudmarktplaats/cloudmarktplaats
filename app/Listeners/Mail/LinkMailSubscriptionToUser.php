<?php

declare(strict_types=1);

namespace App\Listeners\Mail;

use App\Models\User;
use App\Services\Mail\MailSubscriptionService;
use Illuminate\Auth\Events\Verified;

/**
 * Hangt een losse inschrijving aan het account zodra dat account bewijst dat
 * het adres van hem is.
 *
 * Waarom aan dit event en niet aan registratie: er zijn drie paden die accounts
 * aanmaken (formulier, OAuth, SIWE) en die groeien vanzelf. Elk pad zelf laten
 * koppelen betekent dat het vierde pad het vergeet, en dan blijft een
 * inschrijving met toestemmingsbewijs achter als het account gewist wordt.
 * `Verified` is het enige moment dat alle paden delen, en het is precies het
 * moment waarop koppelen ook veilig is.
 */
class LinkMailSubscriptionToUser
{
    public function __construct(private MailSubscriptionService $subscriptions) {}

    public function handle(Verified $event): void
    {
        // Het event draagt een Authenticatable, niet per se onze User. In dit
        // project is dat altijd dezelfde klasse; de controle is er zodat een
        // toekomstig tweede gebruikersmodel hier niet stil op een typefout stuit.
        if ($event->user instanceof User) {
            $this->subscriptions->linkToUser($event->user);
        }
    }
}
