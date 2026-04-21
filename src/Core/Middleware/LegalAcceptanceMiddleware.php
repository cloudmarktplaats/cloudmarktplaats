<?php

namespace App\Core\Middleware;

use App\Core\Database;
use App\Core\Session;
use App\Models\LegalDocument;
use App\Services\Auth\LegalDocumentService;

class LegalAcceptanceMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $userId = Session::userId();
        if ($userId === null) {
            return true;
        }

        $user = Database::getInstance()->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        if ($user === false) {
            return true;
        }

        $service = new LegalDocumentService(new LegalDocument());
        if (!$service->needsAcceptance($user, 'nl')) {
            return true;
        }

        Session::set('legal_return_to', $_SERVER['REQUEST_URI'] ?? '/dashboard');
        return false;
    }
}
