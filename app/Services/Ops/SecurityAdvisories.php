<?php

declare(strict_types=1);

namespace App\Services\Ops;

use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * How many security advisories `composer audit` currently reports.
 *
 * CI has run this check since day one and it did its job: it went red on
 * 6 August over twelve advisories in guzzle and commonmark, and stayed red
 * for more than two weeks. Nobody looked. A gate that only shows up on a tab
 * is not a signal anyone receives — the daily mail is the one place this
 * project is actually read, so that is where an advisory belongs.
 *
 * Advisories deliberately ignored in `config.audit.ignore` (composer.json) are
 * not counted: `composer audit` leaves them out of its exit code and out of
 * `advisories` too. Those three Laravel entries are a tracked debt, not a daily
 * alarm — see AGENTS.md.
 */
class SecurityAdvisories
{
    /**
     * Advisory count, or null when we genuinely cannot tell.
     *
     * Null matters. Packagist unreachable, no network in the container,
     * composer missing — report "unknown", never 0. A check that says "all
     * clear" precisely when it is broken is worse than no check.
     */
    public function count(): ?int
    {
        try {
            $result = Process::path(base_path())
                ->timeout(60)
                ->run('composer audit --format=json --no-interaction');

            $payload = json_decode($result->output(), true);

            if (! is_array($payload) || ! isset($payload['advisories']) || ! is_array($payload['advisories'])) {
                return null;
            }

            // `advisories` is keyed by package, each holding a list. A count of
            // packages would read "2 advisories" for twelve real ones.
            return array_sum(array_map(
                fn ($perPackage): int => is_array($perPackage) ? count($perPackage) : 0,
                $payload['advisories'],
            ));
        } catch (Throwable) {
            return null;
        }
    }
}
