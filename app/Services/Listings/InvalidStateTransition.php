<?php

declare(strict_types=1);

namespace App\Services\Listings;

use RuntimeException;

/**
 * Raised by {@see ListingStateService::transition()} when the requested
 * destination state is not reachable from the listing's current state.
 * Livewire components catch this and surface as validation errors.
 */
class InvalidStateTransition extends RuntimeException {}
