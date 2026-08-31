<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailSubscription;
use App\Services\Mail\MailSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Bevestigen en afmelden, allebei zonder login.
 *
 * Bewust een controller en geen Livewire-component: dit moet werken als een
 * kale GET uit een mailprogramma, ook met JavaScript uit.
 */
class MailSubscriptionController extends Controller
{
    public function __construct(private MailSubscriptionService $subscriptions) {}

    /**
     * Alleen tonen wat er bevestigd gaat worden. Deze GET schrijft niets.
     *
     * `confirmed_at` is het bewijsstuk onder art. 7 lid 1 AVG: het zegt dat
     * iemand zélf op de link in zijn eigen mailbox heeft geklikt. Een
     * linkscanner van een spamfilter of een prefetch van een mailclient doet
     * exact dezelfde GET zonder dat er een mens bij is; zou die het bewijs
     * aanmaken, dan bewijst het niets meer. Erger nog: een geparkeerde
     * wijziging kan van een vreemde komen, en die zou dan zonder klik van de
     * eigenaar worden doorgevoerd.
     *
     * Lezen mag hier wel rechtstreeks: de service is de enige plek die
     * inschrijvingen schrijft, niet de enige die ze mag opzoeken.
     */
    public function confirm(string $token): View
    {
        $sub = MailSubscription::query()->where('confirm_token', $token)->first();
        abort_if($sub === null, 404);

        return view('pages.mail-subscription-result', ['actie' => 'bevestigen', 'abonnement' => $sub]);
    }

    /** De knop op dat tussenscherm. Pas hier ontstaat het bewijs. */
    public function applyConfirmation(string $token): View
    {
        $sub = $this->subscriptions->confirm($token);
        abort_if($sub === null, 404);

        return view('pages.mail-subscription-result', ['actie' => 'bevestigd', 'abonnement' => $sub]);
    }

    public function unsubscribe(Request $request, string $token): View
    {
        $wat = $request->query('wat');

        // Alles behalve een kale string is per definitie geen geldig doel
        // (bijv. `?wat[]=offers`). Dat hier al afvangen voorkomt dat zo'n
        // waarde de service in gaat als iets anders dan een string.
        abort_if($wat !== null && ! is_string($wat), 400);

        // `MailSubscriptionService::unsubscribe()` gooit een
        // `InvalidArgumentException` op een onbekend doel — expres, sinds
        // taak 2, want stil op `null` ("meld alles af") zetten verbergt een
        // kapotte link en meldt bovendien meer af dan gevraagd. Deze
        // controller dupliceert de whitelist van geldige waarden daarom niet:
        // hij vangt de uitzondering op en maakt er een nette 400 van. Een
        // 404 zou suggereren dat het token het probleem is, terwijl het
        // token hier prima klopt en alleen `?wat=` onzin bevat.
        try {
            $sub = $this->subscriptions->unsubscribe($token, $wat);
        } catch (InvalidArgumentException) {
            abort(400);
        }

        abort_if($sub === null, 404);

        // Geen `fresh()`: de service heeft precies deze instantie net opgeslagen,
        // dus een tweede SELECT levert dezelfde rij op.
        return view('pages.mail-subscription-result', ['actie' => 'afgemeld', 'abonnement' => $sub]);
    }

    /**
     * Nooit terugsturen naar de afmeldroute: die meldt bij elke GET opnieuw af,
     * dus een redirect daarheen draait het herstel meteen weer terug.
     */
    public function resubscribe(Request $request, string $token): View
    {
        $wat = $request->input('wat');

        abort_if($wat !== null && ! is_string($wat), 400);

        // Zelfde afspraak als bij afmelden: de whitelist staat in de service,
        // de route maakt er een 400 van. Het schrijven zelf hoort daar ook
        // thuis, want daar wordt het nieuwe toestemmingsmoment vastgelegd.
        try {
            $sub = $this->subscriptions->resubscribe($token, $wat);
        } catch (InvalidArgumentException) {
            abort(400);
        }

        abort_if($sub === null, 404);

        return view('pages.mail-subscription-result', ['actie' => 'hersteld', 'abonnement' => $sub]);
    }
}
