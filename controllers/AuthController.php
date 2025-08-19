<?php
require_once 'controllers/BaseController.php';

class AuthController extends BaseController {
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $this->setFlash('danger', 'Vul alle velden in.');
                $this->redirect('/auth/login');
            }

            $user = $this->db->fetch("SELECT * FROM users WHERE username = ?", [$username]);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['role'] === 'admin';
                
                $this->setFlash('success', 'Je bent succesvol ingelogd!');
                $this->redirect('/dashboard');
            } else {
                $this->setFlash('danger', 'Ongeldige gebruikersnaam of wachtwoord.');
                $this->redirect('/auth/login');
            }
        }

        $this->render('auth/login');
    }

    public function register() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';

            if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
                $this->setFlash('danger', 'Vul alle velden in.');
                $this->redirect('/auth/register');
            }

            if ($password !== $password_confirm) {
                $this->setFlash('danger', 'Wachtwoorden komen niet overeen.');
                $this->redirect('/auth/register');
            }

            if (strlen($password) < 8) {
                $this->setFlash('danger', 'Wachtwoord moet minimaal 8 tekens lang zijn.');
                $this->redirect('/auth/register');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->setFlash('danger', 'Ongeldig e-mailadres.');
                $this->redirect('/auth/register');
            }

            // Controleer of gebruikersnaam of email al bestaat
            $existing_user = $this->db->fetch(
                "SELECT * FROM users WHERE username = ? OR email = ?", 
                [$username, $email]
            );

            if ($existing_user) {
                $this->setFlash('danger', 'Gebruikersnaam of e-mailadres bestaat al.');
                $this->redirect('/auth/register');
            }

            // Maak nieuwe gebruiker aan
            $this->db->query(
                "INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, 'user', NOW())",
                [$username, $email, password_hash($password, PASSWORD_DEFAULT)]
            );

            $this->setFlash('success', 'Je account is aangemaakt! Je kunt nu inloggen.');
            $this->redirect('/auth/login');
        }

        $this->render('auth/register');
    }

    public function logout() {
        session_destroy();
        $this->redirect('/');
    }

    public function dashboard() {
        $this->requireLogin();

        // Haal gebruikersgegevens op
        $user = $this->getUser();

        // Haal producten van de gebruiker op
        $products = $this->db->fetchAll(
            "SELECT * FROM products WHERE user_id = ? ORDER BY created_at DESC",
            [$user['id']]
        );

        // Haal favorieten op
        $favorites = $this->db->fetchAll(
            "SELECT p.*, f.created_at as favorited_at 
             FROM favorites f 
             JOIN products p ON f.product_id = p.id 
             WHERE f.user_id = ? 
             ORDER BY f.created_at DESC",
            [$user['id']]
        );

        // Haal ongelezen berichten op
        $unread_messages = $this->db->fetch(
            "SELECT COUNT(*) as count 
             FROM messages 
             WHERE receiver_id = ? AND read_at IS NULL",
            [$user['id']]
        )['count'];

        // Haal recente berichten op
        $recent_messages = $this->db->fetchAll(
            "SELECT m.*, u.username as sender_username 
             FROM messages m 
             JOIN users u ON m.sender_id = u.id 
             WHERE m.receiver_id = ? 
             ORDER BY m.created_at DESC 
             LIMIT 5",
            [$user['id']]
        );

        $this->render('auth/dashboard', [
            'user' => $user,
            'products' => $products,
            'favorites' => $favorites,
            'unread_messages' => $unread_messages,
            'recent_messages' => $recent_messages
        ]);
    }
} 