<?php

declare(strict_types=1);

namespace App\Services\Dac7;

/**
 * Placeholder for the future DAC7 (EU platform-reporting directive) service.
 *
 * The Foundation phase wires the schema (sufficient `transactions` /
 * `users` columns to compute threshold progress) but does not yet act on
 * it: the Dutch implementation of DAC7 requires reporting once a seller
 * crosses 30 transactions OR €2.000 in a calendar year on-platform.
 *
 * See {@see docs/dac7-position.md} for the policy rationale; the noop
 * here keeps every consumer of this class against a single contract so
 * that switching the flag `dac7_reporting` on (and dropping in the real
 * implementation) is a one-binding change in the service container.
 *
 * Sentinel returns:
 *   - {@see getThresholdProgress()} → `0`  (no on-platform transactions)
 *   - {@see isReportable()}         → `false`
 */
class Dac7Service
{
    public function getThresholdProgress(int $userId): int
    {
        return 0;
    }

    public function isReportable(int $userId): bool
    {
        return false;
    }
}
