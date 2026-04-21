<?php

namespace App\Services\Auth;

use App\Core\Config;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\Github;

class OAuthProviderFactory
{
    public function make(string $provider): AbstractProvider
    {
        return match ($provider) {
            'google' => $this->makeGoogle(),
            'github' => $this->makeGithub(),
            default => throw new \InvalidArgumentException("Unknown OAuth provider: {$provider}"),
        };
    }

    private function makeGoogle(): Google
    {
        return new Google([
            'clientId' => $this->requireConfig('GOOGLE_CLIENT_ID'),
            'clientSecret' => $this->requireConfig('GOOGLE_CLIENT_SECRET'),
            'redirectUri' => $this->redirectUri('google'),
        ]);
    }

    private function makeGithub(): Github
    {
        return new Github([
            'clientId' => $this->requireConfig('GITHUB_CLIENT_ID'),
            'clientSecret' => $this->requireConfig('GITHUB_CLIENT_SECRET'),
            'redirectUri' => $this->redirectUri('github'),
        ]);
    }

    private function redirectUri(string $provider): string
    {
        $base = rtrim((string) Config::get('APP_URL', 'http://localhost:8000'), '/');
        return "{$base}/auth/oauth/{$provider}/callback";
    }

    private function requireConfig(string $key): string
    {
        $val = Config::get($key);
        if (empty($val)) {
            throw new \RuntimeException("Missing required config: {$key}");
        }
        return (string) $val;
    }
}
