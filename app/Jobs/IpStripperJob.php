<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 24-hour IP retention enforcer.
 *
 * The marketplace stores `last_login_ip` for fraud-investigation use, but
 * we promise (and the privacy statement says so) that it does not stick
 * around longer than necessary. This job — scheduled hourly from
 * `bootstrap/app.php` — clears the column for any user whose last login
 * was over 24 hours ago. That's long enough that ops can react to an
 * acute incident while keeping the data-retention window minimal.
 *
 * We do not strip `last_login_at` because the timestamp alone has no
 * personal-data value (it can't be tied back to a specific session) and
 * the product surface uses it for "last seen" cues.
 */
class IpStripperJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        User::query()
            ->whereNotNull('last_login_ip')
            ->where('last_login_at', '<', now()->subHours(24))
            ->update(['last_login_ip' => null]);
    }
}
