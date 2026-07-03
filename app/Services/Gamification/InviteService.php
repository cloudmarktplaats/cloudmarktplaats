<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Exceptions\InviteException;
use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InviteService
{
    public function generate(User $inviter): InviteCode
    {
        return DB::transaction(function () use ($inviter): InviteCode {
            $locked = User::query()->lockForUpdate()->find($inviter->id);
            if ($locked === null) {
                throw new InviteException('Account niet gevonden.');
            }

            if ($locked->email_verified_at === null) {
                throw new InviteException('Verifieer eerst je e-mailadres.');
            }
            if ($locked->is_banned) {
                throw new InviteException('Geblokkeerde accounts kunnen geen uitnodigingen maken.');
            }
            if ($locked->invite_credits < 1) {
                throw new InviteException('Je hebt geen uitnodigingen meer over.');
            }

            $locked->decrement('invite_credits');

            return InviteCode::query()->create([
                'inviter_user_id' => $locked->id,
            ]);
        });
    }

    public function redeem(string $code, User $invitee): InviteCode
    {
        return DB::transaction(function () use ($code, $invitee): InviteCode {
            /** @var InviteCode|null $row */
            $row = InviteCode::query()->where('code', $code)->lockForUpdate()->first();

            if ($row === null || $row->used_at !== null || $row->revoked_at !== null
                || ($row->expires_at !== null && $row->expires_at->isPast())) {
                throw new InviteException('Deze uitnodigingscode is ongeldig of al gebruikt.');
            }
            if ($row->inviter_user_id === $invitee->id) {
                throw new InviteException('Je kunt je eigen code niet inwisselen.');
            }

            /** @var User $lockedInvitee */
            $lockedInvitee = User::query()->lockForUpdate()->findOrFail($invitee->id);
            if ($lockedInvitee->invited_by !== null) {
                throw new InviteException('Dit account is al aan een uitnodiging gekoppeld.');
            }

            $row->forceFill([
                'invitee_user_id' => $lockedInvitee->id,
                'used_at' => now(),
            ])->save();

            $lockedInvitee->forceFill(['invited_by' => $row->inviter_user_id])->save();

            // Keep the caller's instance consistent with what we persisted.
            $invitee->setAttribute('invited_by', $row->inviter_user_id);

            return $row;
        });
    }
}
