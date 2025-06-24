<?php

class BaseController {
    protected $db;
    protected $user;

    public function __construct() {
        $this->db = new Database();
        $this->user = isset($_SESSION['user_id']) ? $this->getUser() : null;
    }

    protected function getUser() {
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }

    protected function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /auth/login');
            exit;
        }
    }

    protected function requireAdmin() {
        if (!$this->isAdmin()) {
            header('Location: /');
            exit;
        }
    }

    protected function render($view, $data = []) {
        extract($data);
        ob_start();
        require_once "views/{$view}.php";
        $content = ob_get_clean();

        $is_htmx = isset($_SERVER['HTTP_HX_REQUEST']);
        if ($is_htmx) {
            // Alleen de content voor HTMX
            echo $content;
        } else {
            // Volledige pagina
            require_once 'includes/header.php';
            echo $content;
            require_once 'includes/footer.php';
        }
    }

    protected function redirect($url) {
        header("Location: {$url}");
        exit;
    }

    protected function setFlash($type, $message) {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }

    protected function getFlash() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }

    protected function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    protected function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
} 