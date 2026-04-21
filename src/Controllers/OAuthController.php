<?php

namespace App\Controllers;

use App\Core\Session;
use App\Core\Database;
use App\Models\OAuthProvider;
use App\Models\User;
use App\Services\Auth\OAuthProviderFactory;

class OAuthController extends BaseController
{
    private OAuthProviderFactory $factory;
    private OAuthProvider $links;
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->factory = new OAuthProviderFactory();
        $this->links = new OAuthProvider();
        $this->userModel = new User();
    }

    public function redirect(string $provider): void
    {
        $this->assertProviderSupported($provider);

        $league = $this->factory->make($provider);
        $scopes = $provider === 'google'
            ? ['openid', 'email', 'profile']
            : ['user:email'];

        $authUrl = $league->getAuthorizationUrl(['scope' => $scopes]);
        Session::set('oauth_state_' . $provider, $league->getState());
        $this->redirectTo($authUrl);
    }

    public function callback(string $provider): void
    {
        $this->assertProviderSupported($provider);

        $state = $_GET['state'] ?? '';
        $expected = Session::get('oauth_state_' . $provider);
        Session::remove('oauth_state_' . $provider);

        if (empty($state) || $state !== $expected) {
            http_response_code(400);
            echo 'Invalid OAuth state.';
            return;
        }

        if (empty($_GET['code'])) {
            $this->flash('error', 'OAuth login geannuleerd.');
            $this->redirectTo('/auth/login');
            return;
        }

        $league = $this->factory->make($provider);
        try {
            $token = $league->getAccessToken('authorization_code', ['code' => $_GET['code']]);
            $resourceOwner = $league->getResourceOwner($token);
        } catch (\Throwable $e) {
            $this->flash('error', 'OAuth authenticatie mislukt.');
            $this->redirectTo('/auth/login');
            return;
        }

        $uid = (string) $resourceOwner->getId();
        $email = method_exists($resourceOwner, 'getEmail') ? $resourceOwner->getEmail() : null;
        $name = method_exists($resourceOwner, 'getName') ? $resourceOwner->getName() : null;

        if (!$email && $provider === 'github') {
            $email = "noreply_github_{$uid}@users.noreply.github.com";
        }

        $userId = $this->handleProviderResponse($provider, $uid, $email, $name ?? ('user_' . $uid));

        $user = $this->userModel->findById($userId);
        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('role', $user['role'] ?? 'user');
        session_regenerate_id(true);
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        $this->flash('success', 'Welkom, ' . $user['username'] . '!');
        $this->redirectTo('/dashboard');
    }

    public function handleProviderResponse(string $provider, string $uid, ?string $email, string $name): int
    {
        $link = $this->links->findByProviderUid($provider, $uid);
        if ($link !== false) {
            return (int) $link['user_id'];
        }

        if ($email !== null) {
            $existing = $this->userModel->findByEmail($email);
            if ($existing !== false) {
                $this->links->link((int) $existing['id'], $provider, $uid, $email);
                return (int) $existing['id'];
            }
        }

        $username = $this->deriveUsername($name, $uid);
        $userId = Database::getInstance()->insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => null,
            'role' => 'user',
        ]);
        $this->links->link($userId, $provider, $uid, $email);
        return $userId;
    }

    private function deriveUsername(string $name, string $uidFallback): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'user_' . substr($uidFallback, 0, 8);
        }
        $candidate = $base;
        $suffix = 2;
        while ($this->userModel->existsWithUsername($candidate)) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
            if ($suffix > 1000) {
                $candidate = $base . '_' . bin2hex(random_bytes(3));
                break;
            }
        }
        return $candidate;
    }

    private function assertProviderSupported(string $provider): void
    {
        if (!in_array($provider, ['google', 'github'], true)) {
            http_response_code(404);
            exit;
        }
    }

    private function redirectTo(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
