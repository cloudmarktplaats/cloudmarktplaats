<?php

namespace App\Models;

use App\Core\Database;

class AuthNonce
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(string $nonce, ?string $address, int $ttlSeconds): int
    {
        $row = $this->db->fetch("SELECT DATE_ADD(NOW(), INTERVAL ? SECOND) AS expires", [$ttlSeconds]);
        return $this->db->insert('auth_nonces', [
            'nonce' => $nonce,
            'address' => $address,
            'expires_at' => $row['expires'],
        ]);
    }

    public function findValid(string $nonce, string $address): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM auth_nonces
             WHERE nonce = ? AND address = ?
               AND consumed_at IS NULL
               AND expires_at > NOW()",
            [$nonce, $address]
        );
    }

    public function consume(string $nonce): bool
    {
        $row = $this->db->fetch("SELECT NOW() AS now_ts");
        $rows = $this->db->update(
            'auth_nonces',
            ['consumed_at' => $row['now_ts']],
            'nonce = ? AND consumed_at IS NULL',
            [$nonce]
        );
        return $rows > 0;
    }

    public function deleteExpired(int $olderThanSeconds = 86400): int
    {
        $row = $this->db->fetch("SELECT DATE_SUB(NOW(), INTERVAL ? SECOND) AS cutoff", [$olderThanSeconds]);
        return $this->db->delete('auth_nonces', 'expires_at < ?', [$row['cutoff']]);
    }
}
