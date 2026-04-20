<?php

namespace App\Core\Middleware;

class AdminMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';
    }
}
