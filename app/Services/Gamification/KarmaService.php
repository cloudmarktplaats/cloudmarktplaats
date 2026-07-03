<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\KarmaEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only karma ledger. karma = SUM(points). Nothing is ever
 * mutated or deleted; a reversal is a compensating negative row.
 */
class KarmaService
{
    public function award(User $user, string $type, int $points, ?Model $source = null): KarmaEvent
    {
        return KarmaEvent::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'points' => $points,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
        ]);
    }

    /**
     * Reverse every invite_activation earned *from* this invitee. Skips
     * activations that already have a matching reversal (idempotent) so
     * a repeated ban action can't double-dock the inviter.
     */
    public function revokeInviteActivation(User $invitee): void
    {
        $alias = $invitee->getMorphClass();

        $activations = KarmaEvent::query()
            ->where('type', 'invite_activation')
            ->where('source_type', $alias)
            ->where('source_id', $invitee->getKey())
            ->get();

        foreach ($activations as $activation) {
            $alreadyReversed = KarmaEvent::query()
                ->where('type', 'invite_reversal')
                ->where('source_type', $alias)
                ->where('source_id', $invitee->getKey())
                ->where('user_id', $activation->user_id)
                ->exists();

            if ($alreadyReversed) {
                continue;
            }

            KarmaEvent::query()->create([
                'user_id' => $activation->user_id,
                'type' => 'invite_reversal',
                'points' => -$activation->points,
                'source_type' => $alias,
                'source_id' => $invitee->getKey(),
            ]);
        }
    }

    public function karmaFor(User $user): int
    {
        return (int) $user->karmaEvents()->sum('points');
    }
}
