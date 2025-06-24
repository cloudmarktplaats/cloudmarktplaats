<?php

namespace App\Controllers;

use App\Core\Database;

abstract class BaseController {
    protected Database $db;

    public function __construct() {
        $this->db = new Database();
    }

    protected function view(string $view, array $data = []): void {
        extract($data);
        
        ob_start();
        require_once __DIR__ . "/../Views/{$view}.php";
        $content = ob_get_clean();
        
        require_once __DIR__ . "/../Views/layouts/main.php";
    }

    protected function redirect(string $url): void {
        header("Location: {$url}");
        exit;
    }

    protected function setFlash(string $message, string $type = 'success'): void {
        $_SESSION['flash'] = [
            'message' => $message,
            'type' => $type
        ];
    }

    protected function isAuthenticated(): bool {
        return isset($_SESSION['user']);
    }

    protected function requireAuth(): void {
        if (!$this->isAuthenticated()) {
            $this->setFlash('Je moet ingelogd zijn om deze pagina te bekijken.', 'danger');
            $this->redirect('/auth/login');
        }
    }

    protected function json(array $data): void {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
} 