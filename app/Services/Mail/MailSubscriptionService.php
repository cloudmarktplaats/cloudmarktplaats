<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\MailSubscription;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * De enige plek waar inschrijvingen ontstaan, bevestigd worden en verdwijnen.
 *
 * Toestemming moet aantoonbaar zijn (art. 7 lid 1 AVG) en dit platform bewaart
 * geen IP's, dus het bewijs bestaat uit de letterlijke zin waarop iemand ja
 * zei plus de bevestigingsklik uit zijn eigen mailbox.
 */
class MailSubscriptionService
{
    /** @param list<string> $categories */
    public function subscribe(
        string $email,
        bool $wantsOffers,
        bool $wantsUpdates,
        array $categories,
        string $consentText,
        string $source,
        ?User $user = null,
    ): MailSubscription {
        // Een geverifieerd account heeft de mailbox al bewezen; daar voegt een
        // tweede klik niets aan bewijskracht toe.
        $alreadyProven = $user !== null && $user->email_verified_at !== null;
        $normalizedEmail = Str::lower(trim($email));

        // Een bestaand `unsubscribe_token` blijft staan bij een hernieuwde
        // aanmelding op hetzelfde adres: dat token zit al in elke mail die ooit
        // verstuurd is, en een nieuw token zou de afmeldlink daarin met
        // terugwerkende kracht breken.
        $existingToken = MailSubscription::query()
            ->where('email', $normalizedEmail)
            ->value('unsubscribe_token');

        return MailSubscription::query()->updateOrCreate(
            ['email' => $normalizedEmail],
            [
                'user_id' => $user?->id,
                'wants_offers' => $wantsOffers,
                'wants_updates' => $wantsUpdates,
                'categories' => array_values($categories),
                'consent_text' => $consentText,
                'consent_given_at' => now(),
                'consent_source' => $source,
                'confirmed_at' => $alreadyProven ? now() : null,
                'confirm_token' => $alreadyProven ? null : Str::random(48),
                'unsubscribe_token' => $existingToken ?? Str::random(48),
            ],
        );
    }

    public function confirm(string $token): ?MailSubscription
    {
        $sub = MailSubscription::query()->where('confirm_token', $token)->first();

        $sub?->forceFill(['confirmed_at' => now(), 'confirm_token' => null])->save();

        return $sub;
    }

    /** @param  'offers'|'updates'|null  $what */
    public function unsubscribe(string $token, ?string $what = null): ?MailSubscription
    {
        $sub = MailSubscription::query()->where('unsubscribe_token', $token)->first();

        $sub?->forceFill([
            'wants_offers' => $what === 'updates' && $sub->wants_offers,
            'wants_updates' => $what === 'offers' && $sub->wants_updates,
        ])->save();

        return $sub;
    }

    /** Onbevestigde aanmeldingen zijn geen toestemming, dus die blijven niet staan. */
    public function purgeUnconfirmed(int $days = 7): int
    {
        return MailSubscription::query()
            ->whereNull('confirmed_at')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
