<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\User;
use App\Models\Product;
use App\Models\Review;
use App\Models\Forum;
use App\Models\OAuthProvider;
use App\Models\WalletAddress;

class ProfileController extends BaseController
{
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    public function view(string $id): void
    {
        $user = $this->userModel->findById((int) $id);
        if (!$user) {
            $this->flash('error', 'Gebruiker niet gevonden.');
            $this->redirect('/');
            return;
        }

        $productModel = new Product();
        $reviewModel = new Review();
        $products = $productModel->getByUser((int) $id);
        $reviews = $reviewModel->getForUser((int) $id);

        $this->render('profile/view', [
            'title' => $user['username'],
            'profile_user' => $user,
            'products' => $products,
            'reviews' => $reviews,
        ]);
    }

    public function index(): void
    {
        $userId = $this->userId();
        $user = $this->userModel->findById($userId);

        $productModel = new Product();
        $forumModel = new Forum();
        $products = $productModel->getByUser($userId);
        $forumStats = $forumModel->getUserStats($userId);

        $favCount = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM favorites WHERE user_id = ?", [$userId]
        );

        $this->render('profile/index', [
            'title' => 'Mijn Profiel',
            'profile_user' => $user,
            'products' => $products,
            'forum_stats' => $forumStats,
            'favorite_count' => (int) $favCount['cnt'],
        ]);
    }

    public function products(string $id): void
    {
        $productModel = new Product();
        $products = $productModel->getByUser((int) $id);
        $this->render('profile/_products', ['products' => $products]);
    }

    public function topics(string $id): void
    {
        $forumModel = new Forum();
        $topics = $forumModel->getTopicsByUser((int) $id);
        $this->render('profile/_topics', ['topics' => $topics]);
    }

    public function edit(): void
    {
        $userId = $this->userId();
        $user = $this->userModel->findById($userId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';

            $errors = [];

            if (strlen($username) < 3) {
                $errors[] = 'Gebruikersnaam moet minimaal 3 tekens zijn.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Ongeldig e-mailadres.';
            }
            if ($this->userModel->existsWithUsername($username, $userId)) {
                $errors[] = 'Gebruikersnaam is al in gebruik.';
            }
            if ($this->userModel->existsWithEmail($email, $userId)) {
                $errors[] = 'E-mailadres is al in gebruik.';
            }

            if (!empty($newPassword)) {
                if (strlen($newPassword) < 8) {
                    $errors[] = 'Nieuw wachtwoord moet minimaal 8 tekens zijn.';
                }
                if (!password_verify($currentPassword, $user['password'])) {
                    $errors[] = 'Huidig wachtwoord is onjuist.';
                }
            }

            if (!empty($errors)) {
                $this->flash('error', implode("\n", $errors));
                $this->render('profile/edit', [
                    'title' => 'Profiel Bewerken',
                    'profile_user' => $user,
                ]);
                return;
            }

            $this->userModel->updateProfile($userId, [
                'username' => $username,
                'email' => $email,
            ]);

            if (!empty($newPassword)) {
                $this->userModel->updatePassword($userId, $newPassword);
            }

            Session::set('username', $username);
            $this->flash('success', 'Profiel bijgewerkt.');
            $this->redirect('/profile');
            return;
        }

        $this->render('profile/edit', [
            'title' => 'Profiel Bewerken',
            'profile_user' => $user,
        ]);
    }

    public function delete(): void
    {
        $this->userModel->delete($this->userId());
        Session::destroy();
        session_start();
        Session::flash('success', 'Account verwijderd.');
        header('Location: /');
        exit;
    }

    public function security(): void
    {
        $userId = $this->userId();
        if ($userId === null) {
            $this->redirect('/auth/login');
            return;
        }

        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        $oauth = (new OAuthProvider())->findByUser($userId);
        $wallets = (new WalletAddress())->findByUser($userId);

        $this->render('profile/security', [
            'title' => 'Beveiliging',
            'user_row' => $user,
            'oauth' => $oauth,
            'wallets' => $wallets,
            'auth_methods_count' => $this->countAuthMethods($userId),
        ]);
    }

    public function unlinkOAuth(string $provider): void
    {
        $userId = $this->userId();
        if ($userId === null) {
            $this->redirect('/auth/login');
            return;
        }

        if ($this->countAuthMethods($userId) <= 1) {
            $this->flash('error', 'Je kunt je laatste inlogmethode niet loskoppelen.');
            $this->redirect('/profile/security');
            return;
        }

        (new OAuthProvider())->unlink($userId, $provider);
        $this->flash('success', 'Koppeling verwijderd.');
        $this->redirect('/profile/security');
    }

    public function unlinkWallet(string $id): void
    {
        $userId = $this->userId();
        if ($userId === null) {
            $this->redirect('/auth/login');
            return;
        }

        if ($this->countAuthMethods($userId) <= 1) {
            $this->flash('error', 'Je kunt je laatste inlogmethode niet loskoppelen.');
            $this->redirect('/profile/security');
            return;
        }

        (new WalletAddress())->unlink($userId, (int) $id);
        $this->flash('success', 'Wallet ontkoppeld.');
        $this->redirect('/profile/security');
    }

    public function countAuthMethods(int $userId): int
    {
        $user = $this->db->fetch("SELECT password FROM users WHERE id = ?", [$userId]);
        $passwordCount = ($user && !empty($user['password'])) ? 1 : 0;

        $oauthCount = (int) $this->db->fetch("SELECT COUNT(*) AS c FROM oauth_providers WHERE user_id = ?", [$userId])['c'];
        $walletCount = (int) $this->db->fetch("SELECT COUNT(*) AS c FROM wallet_addresses WHERE user_id = ?", [$userId])['c'];

        return $passwordCount + $oauthCount + $walletCount;
    }
}
