<?php

namespace App\Models;

use App\Core\Database;

class WalletAddress
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function link(int $userId, string $address, int $chainId): int
    {
        $row = $this->db->fetch("SELECT NOW() AS now_ts");
        return $this->db->insert('wallet_addresses', [
            'user_id' => $userId,
            'address' => strtolower($address),
            'chain_id' => $chainId,
            'verified_at' => $row['now_ts'],
        ]);
    }

    public function findByAddress(string $address): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM wallet_addresses WHERE address = ?",
            [strtolower($address)]
        );
    }

    public function findByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM wallet_addresses WHERE user_id = ? ORDER BY created_at",
            [$userId]
        );
    }

    public function unlink(int $userId, int $walletId): bool
    {
        return $this->db->delete(
            'wallet_addresses',
            'id = ? AND user_id = ?',
            [$walletId, $userId]
        ) > 0;
    }
}
