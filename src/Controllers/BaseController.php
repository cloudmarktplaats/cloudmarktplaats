<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;

abstract class BaseController
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function render(string $view, array $data = []): void
    {
        $data['user'] = $this->getUser();
        $data['flash'] = Session::getFlash();
        View::render($view, $data);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    protected function getUser(): ?array
    {
        $userId = Session::userId();
        if (!$userId) {
            return null;
        }
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
    }

    protected function isLoggedIn(): bool
    {
        return Session::isLoggedIn();
    }

    protected function isAdmin(): bool
    {
        return Session::isAdmin();
    }

    protected function userId(): ?int
    {
        return Session::userId();
    }
}
