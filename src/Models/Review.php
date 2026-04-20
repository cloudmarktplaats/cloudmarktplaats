<?php

namespace App\Models;

use App\Core\Database;

class Review
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getForProduct(int $productId): array
    {
        return $this->db->fetchAll(
            "SELECT r.*, u.username FROM reviews r
             JOIN users u ON r.user_id = u.id
             WHERE r.product_id = ?
             ORDER BY r.created_at DESC",
            [$productId]
        );
    }

    public function getForUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT r.*, u.username, p.name as product_name FROM reviews r
             JOIN users u ON r.user_id = u.id
             JOIN products p ON r.product_id = p.id
             WHERE p.user_id = ?
             ORDER BY r.created_at DESC",
            [$userId]
        );
    }

    public function create(int $userId, int $productId, int $rating, string $comment): int
    {
        return $this->db->insert('reviews', [
            'user_id' => $userId,
            'product_id' => $productId,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }
}
