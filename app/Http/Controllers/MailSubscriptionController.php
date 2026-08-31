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

    public function confirm(string $token): View
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

        return view('pages.mail-subscription-result', ['actie' => 'afgemeld', 'abonnement' => $sub->fresh()]);
    }

    /**
     * Nooit terugsturen naar de afmeldroute: die meldt bij elke GET opnieuw af,
     * dus een redirect daarheen draait het herstel meteen weer terug.
     */
    public function resubscribe(Request $request, string $token): View
    {
        $sub = MailSubscription::query()->where('unsubscribe_token', $token)->first();
        abort_if($sub === null, 404);

        $wat = $request->input('wat');
        $sub->forceFill([
            'wants_offers' => $sub->wants_offers || $wat === 'offers',
            'wants_updates' => $sub->wants_updates || $wat === 'updates',
        ])->save();

        return view('pages.mail-subscription-result', ['actie' => 'hersteld', 'abonnement' => $sub]);
    }
}
