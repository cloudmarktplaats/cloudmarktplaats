<?php

namespace App\Models;

use App\Core\Database;

class Message
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getConversations(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT
                CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END as other_user_id,
                u.username as other_username,
                MAX(m.created_at) as last_message_at,
                SUM(CASE WHEN m.receiver_id = ? AND m.read_at IS NULL THEN 1 ELSE 0 END) as unread_count,
                (SELECT m2.message FROM messages m2
                 WHERE (m2.sender_id = ? AND m2.receiver_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END)
                    OR (m2.receiver_id = ? AND m2.sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END)
                 ORDER BY m2.created_at DESC LIMIT 1) as last_message
             FROM messages m
             JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
             WHERE m.sender_id = ? OR m.receiver_id = ?
             GROUP BY other_user_id, u.username
             ORDER BY last_message_at DESC",
            [$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]
        );
    }

    public function getMessages(int $userId, int $otherUserId): array
    {
        return $this->db->fetchAll(
            "SELECT m.*, u.username as sender_name
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE (m.sender_id = ? AND m.receiver_id = ?)
                OR (m.sender_id = ? AND m.receiver_id = ?)
             ORDER BY m.created_at ASC",
            [$userId, $otherUserId, $otherUserId, $userId]
        );
    }

    public function markAsRead(int $userId, int $senderId): void
    {
        $this->db->query(
            "UPDATE messages SET read_at = NOW() WHERE receiver_id = ? AND sender_id = ? AND read_at IS NULL",
            [$userId, $senderId]
        );
    }

    public function send(int $senderId, int $receiverId, string $message, ?int $productId = null): int
    {
        $data = [
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $message,
        ];
        if ($productId) {
            $data['product_id'] = $productId;
        }
        return $this->db->insert('messages', $data);
    }

    public function getUnreadCount(int $userId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM messages WHERE receiver_id = ? AND read_at IS NULL",
            [$userId]
        );
        return (int) $row['cnt'];
    }

    public function getRecent(int $userId, int $limit = 5): array
    {
        return $this->db->fetchAll(
            "SELECT m.*, u.username as sender_name
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.receiver_id = ?
             ORDER BY m.created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }
}
