<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by the listing-photo pipeline when an upload fails one of the
 * gate checks (MIME mismatch, dimensions out of bounds, corrupt bytes).
 * Surfaces as a Livewire validation error in the wizard, not a 500.
 */
class InvalidUploadException extends RuntimeException {}
