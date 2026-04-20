<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\User;

class AuthController extends BaseController
{
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $this->flash('error', 'Vul alle velden in.');
                $this->render('auth/login', ['title' => 'Inloggen', 'username' => $username]);
                return;
            }

            $attempts = Session::get('login_attempts', []);
            $attempts = array_filter($attempts, fn($t) => $t > time() - 900);
            if (count($attempts) >= 5) {
                $this->flash('error', 'Te veel inlogpogingen. Probeer het over 15 minuten opnieuw.');
                $this->render('auth/login', ['title' => 'Inloggen', 'username' => $username]);
                return;
            }

            $user = $this->userModel->findByUsername($username);

            if (!$user || !password_verify($password, $user['password'])) {
                $attempts[] = time();
                Session::set('login_attempts', $attempts);
                $this->flash('error', 'Ongeldige gebruikersnaam of wachtwoord.');
                $this->render('auth/login', ['title' => 'Inloggen', 'username' => $username]);
                return;
            }

            Session::remove('login_attempts');
            session_regenerate_id(true);
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            Session::set('user_id', $user['id']);
            Session::set('username', $user['username']);
            Session::set('role', $user['role']);

            $this->flash('success', 'Welkom terug, ' . $user['username'] . '!');
            $this->redirect('/dashboard');
            return;
        }

        $this->render('auth/login', ['title' => 'Inloggen']);
    }

    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            $errors = [];

            if (strlen($username) < 3) {
                $errors[] = 'Gebruikersnaam moet minimaal 3 tekens zijn.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Ongeldig e-mailadres.';
            }
            if (strlen($password) < 8) {
                $errors[] = 'Wachtwoord moet minimaal 8 tekens zijn.';
            }
            if ($password !== $passwordConfirm) {
                $errors[] = 'Wachtwoorden komen niet overeen.';
            }
            if ($this->userModel->existsWithUsername($username)) {
                $errors[] = 'Gebruikersnaam is al in gebruik.';
            }
            if ($this->userModel->existsWithEmail($email)) {
                $errors[] = 'E-mailadres is al in gebruik.';
            }

            if (!empty($errors)) {
                $this->flash('error', implode("\n", $errors));
                $this->render('auth/register', [
                    'title' => 'Registreren',
                    'username' => $username,
                    'email' => $email,
                ]);
                return;
            }

            $this->userModel->create($username, $email, $password);
            $this->flash('success', 'Account aangemaakt! Je kunt nu inloggen.');
            $this->redirect('/auth/login');
            return;
        }

        $this->render('auth/register', ['title' => 'Registreren']);
    }

    public function logout(): void
    {
        Session::destroy();
        session_start();
        $this->flash('success', 'Je bent uitgelogd.');
        $this->redirect('/auth/login');
    }
}
