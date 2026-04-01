<?php

namespace App\Core\Middleware;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        return isset($_SESSION['user_id']);
    }
}
