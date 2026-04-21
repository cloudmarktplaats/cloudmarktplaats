<?php

namespace App\Models;

use App\Core\Database;

class OAuthProvider
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function link(int $userId, string $provider, string $providerUid, ?string $email): int
    {
        return $this->db->insert('oauth_providers', [
            'user_id' => $userId,
            'provider' => $provider,
            'provider_uid' => $providerUid,
            'email' => $email,
        ]);
    }

    public function findByProviderUid(string $provider, string $providerUid): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM oauth_providers WHERE provider = ? AND provider_uid = ?",
            [$provider, $providerUid]
        );
    }

    public function findByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM oauth_providers WHERE user_id = ? ORDER BY provider",
            [$userId]
        );
    }

    public function unlink(int $userId, string $provider): bool
    {
        return $this->db->delete(
            'oauth_providers',
            'user_id = ? AND provider = ?',
            [$userId, $provider]
        ) > 0;
    }
}
