<?php

declare(strict_types=1);

use App\Models\InviteCode;
use App\Models\User;
use App\Services\Gamification\StatsService;
use Illuminate\Support\Facades\Cache;

it('counts only redeemable invite codes in the homepage stats', function () {
    // homepageStats() cachet 60 seconden; zonder flush meet je een vorige test.
    Cache::flush();
    $inviter = User::factory()->create();

    InviteCode::factory()->count(2)->create(['inviter_user_id' => $inviter->id]);
    InviteCode::factory()->used()->create(['inviter_user_id' => $inviter->id]);
    InviteCode::factory()->create(['inviter_user_id' => $inviter->id, 'revoked_at' => now()]);
    InviteCode::factory()->create(['inviter_user_id' => $inviter->id, 'expires_at' => now()->subDay()]);

    expect(app(StatsService::class)->homepageStats()['invites_open'])->toBe(2);
});
