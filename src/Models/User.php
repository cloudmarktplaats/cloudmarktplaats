<?php

namespace App\Models;

use App\Core\Database;

class User
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public function findByUsername(string $username): array|false
    {
        return $this->db->fetch("SELECT * FROM users WHERE username = ?", [$username]);
    }

    public function findByEmail(string $email): array|false
    {
        return $this->db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
    }

    public function create(string $username, string $email, string $password): int
    {
        return $this->db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public function updateProfile(int $id, array $data): int
    {
        return $this->db->update('users', $data, 'id = ?', [$id]);
    }

    public function updatePassword(int $id, string $newPassword): int
    {
        return $this->db->update('users', [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        ], 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('messages', 'sender_id = ? OR receiver_id = ?', [$id, $id]);
        $this->db->delete('reviews', 'user_id = ?', [$id]);
        $this->db->delete('favorites', 'user_id = ?', [$id]);

        $products = $this->db->fetchAll("SELECT id FROM products WHERE user_id = ?", [$id]);
        foreach ($products as $product) {
            $this->db->delete('product_images', 'product_id = ?', [$product['id']]);
            $this->db->delete('product_tags', 'product_id = ?', [$product['id']]);
        }
        $this->db->delete('products', 'user_id = ?', [$id]);

        $this->db->delete('forum_replies', 'user_id = ?', [$id]);
        $this->db->delete('forum_topics', 'user_id = ?', [$id]);

        $this->db->delete('users', 'id = ?', [$id]);
    }

    public function existsWithUsername(string $username, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $row = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM users WHERE username = ? AND id != ?",
                [$username, $excludeId]
            );
        } else {
            $row = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM users WHERE username = ?",
                [$username]
            );
        }
        return $row['cnt'] > 0;
    }

    public function existsWithEmail(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $row = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM users WHERE email = ? AND id != ?",
                [$email, $excludeId]
            );
        } else {
            $row = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM users WHERE email = ?",
                [$email]
            );
        }
        return $row['cnt'] > 0;
    }

    public function toggleAdmin(int $id): void
    {
        $user = $this->findById($id);
        if ($user) {
            $newRole = $user['role'] === 'admin' ? 'user' : 'admin';
            $this->db->update('users', ['role' => $newRole], 'id = ?', [$id]);
        }
    }
}
