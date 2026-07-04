<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Exceptions\UpvoteException;
use App\Models\HomelabPost;
use App\Models\HomelabPostUpvote;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class UpvoteService
{
    public function __construct(private readonly KarmaService $karma) {}

    public function toggle(HomelabPost $post, User $voter): bool
    {
        if ($voter->is_banned) {
            throw new UpvoteException('Geblokkeerde accounts kunnen niet waarderen.');
        }
        if ($voter->id === $post->user_id) {
            throw new UpvoteException('Je kunt je eigen post niet waarderen.');
        }

        return DB::transaction(function () use ($post, $voter): bool {
            $existing = HomelabPostUpvote::query()
                ->where('homelab_post_id', $post->id)
                ->where('user_id', $voter->id)
                ->lockForUpdate()
                ->first();

            $owner = $post->user;

            if ($existing !== null) {
                $existing->delete();
                if ($owner instanceof User) {
                    $this->karma->award($owner, 'homelab_upvote_reversal', -1, $post);
                }

                return false;
            }

            try {
                HomelabPostUpvote::query()->create([
                    'homelab_post_id' => $post->id,
                    'user_id' => $voter->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent request already created this vote (and already
                // awarded the owner's karma). Treat as idempotent success —
                // do NOT award again.
                return true;
            }

            if ($owner instanceof User) {
                $this->karma->award($owner, 'homelab_upvote', 1, $post);
            }

            return true;
        });
    }

    public function hasUpvoted(HomelabPost $post, User $voter): bool
    {
        return HomelabPostUpvote::query()
            ->where('homelab_post_id', $post->id)
            ->where('user_id', $voter->id)
            ->exists();
    }

    public function countFor(HomelabPost $post): int
    {
        return HomelabPostUpvote::query()->where('homelab_post_id', $post->id)->count();
    }
}
