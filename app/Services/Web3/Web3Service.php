<?php

declare(strict_types=1);

namespace App\Services\Web3;

/**
 * Placeholder for the future web3 escrow service.
 *
 * Foundation does not ship escrow — the surface (button on the listing,
 * "Beheer escrow" link in the seller dashboard, etc.) is hidden behind
 * the `web3_escrow` feature flag, which is `false` by default. This
 * service exists so that other code can depend on a stable, single
 * implementation today; the v0.x web3 sub-project replaces the
 * sentinel returns with real on-chain calls behind the same surface.
 *
 * Methods return documented sentinel values:
 *   - {@see getEscrowStatus()} → `null` (no escrow exists for any listing)
 *   - {@see isEscrowEnabled()} → `false` (feature flag is off)
 */
class Web3Service
{
    public function getEscrowStatus(int $listingId): ?string
    {
        return null;
    }

    public function isEscrowEnabled(): bool
    {
        return false;
    }
}
