<?php

namespace App\Models;

use App\Core\Database;

class Product
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT p.*, u.username FROM products p
             JOIN users u ON p.user_id = u.id
             WHERE p.id = ?",
            [$id]
        );
    }

    public function getImages(int $productId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM product_images WHERE product_id = ?",
            [$productId]
        );
    }

    public function getTags(int $productId): array
    {
        return $this->db->fetchAll(
            "SELECT tag FROM product_tags WHERE product_id = ?",
            [$productId]
        );
    }

    public function getApproved(array $filters = []): array
    {
        $sql = "SELECT p.*, u.username,
                (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as image_url
                FROM products p
                JOIN users u ON p.user_id = u.id
                WHERE p.approved = 1";
        $params = [];

        if (!empty($filters['category'])) {
            $sql .= " AND p.category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['state'])) {
            $sql .= " AND p.state = ?";
            $params[] = $filters['state'];
        }

        $sort = $filters['sort'] ?? 'newest';
        $sql .= match ($sort) {
            'price_asc' => " ORDER BY p.price ASC",
            'price_desc' => " ORDER BY p.price DESC",
            'oldest' => " ORDER BY p.created_at ASC",
            default => " ORDER BY p.created_at DESC",
        };

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int) $filters['limit'];
        }

        return $this->db->fetchAll($sql, $params);
    }

    public function getByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT p.*,
             (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as image_url
             FROM products p WHERE p.user_id = ? ORDER BY p.created_at DESC",
            [$userId]
        );
    }

    public function getRecent(int $limit = 8): array
    {
        return $this->getApproved(['limit' => $limit, 'sort' => 'newest']);
    }

    public function getCategoryCounts(): array
    {
        return $this->db->fetchAll(
            "SELECT category, COUNT(*) as count FROM products
             WHERE approved = 1 GROUP BY category ORDER BY count DESC LIMIT 6"
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert('products', $data);
    }

    public function update(int $id, array $data): int
    {
        return $this->db->update('products', $data, 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->delete('product_tags', 'product_id = ?', [$id]);
            $this->db->delete('product_images', 'product_id = ?', [$id]);
            $this->db->delete('favorites', 'product_id = ?', [$id]);
            $this->db->delete('reviews', 'product_id = ?', [$id]);
            $this->db->delete('products', 'id = ?', [$id]);
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function addImage(int $productId, string $imageUrl): int
    {
        return $this->db->insert('product_images', [
            'product_id' => $productId,
            'image_url' => $imageUrl,
        ]);
    }

    public function deleteImages(int $productId): void
    {
        $this->db->delete('product_images', 'product_id = ?', [$productId]);
    }

    public function addTag(int $productId, string $tag): void
    {
        $this->db->insert('product_tags', [
            'product_id' => $productId,
            'tag' => $tag,
        ]);
    }

    public function deleteTags(int $productId): void
    {
        $this->db->delete('product_tags', 'product_id = ?', [$productId]);
    }

    public function approve(int $id): int
    {
        return $this->db->update('products', ['approved' => 1], 'id = ?', [$id]);
    }

    public function reject(int $id): int
    {
        return $this->db->update('products', ['approved' => 0], 'id = ?', [$id]);
    }

    public function getForAdmin(array $filters = []): array
    {
        $sql = "SELECT p.*, u.username FROM products p
                JOIN users u ON p.user_id = u.id WHERE 1=1";
        $params = [];

        if (isset($filters['approved'])) {
            $sql .= " AND p.approved = ?";
            $params[] = $filters['approved'];
        }
        if (!empty($filters['category'])) {
            $sql .= " AND p.category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY p.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }
}
