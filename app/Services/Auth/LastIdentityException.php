<?php

declare(strict_types=1);

namespace App\Services\Auth;

use RuntimeException;

/**
 * Thrown when the unlink-identity flow would leave an account with zero
 * working login methods. Surfaces as a Livewire validation error in the
 * Profile/Security UI rather than a 500.
 */
class LastIdentityException extends RuntimeException {}
