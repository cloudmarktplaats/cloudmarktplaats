<?php

namespace App\Models;

use App\Core\Database;

class Forum
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getCategories(): array
    {
        return $this->db->fetchAll(
            "SELECT fc.*,
                (SELECT COUNT(*) FROM forum_topics ft WHERE ft.category_id = fc.id) as topic_count,
                (SELECT COUNT(*) FROM forum_replies fr
                 JOIN forum_topics ft2 ON fr.topic_id = ft2.id
                 WHERE ft2.category_id = fc.id) as reply_count
             FROM forum_categories fc ORDER BY fc.name ASC"
        );
    }

    public function findCategoryById(int $id): array|false
    {
        return $this->db->fetch("SELECT * FROM forum_categories WHERE id = ?", [$id]);
    }

    public function createCategory(string $name, string $description): int
    {
        return $this->db->insert('forum_categories', [
            'name' => $name,
            'description' => $description,
        ]);
    }

    public function getTopics(int $categoryId): array
    {
        return $this->db->fetchAll(
            "SELECT ft.*, u.username,
                (SELECT COUNT(*) FROM forum_replies fr WHERE fr.topic_id = ft.id) as reply_count,
                (SELECT MAX(fr2.created_at) FROM forum_replies fr2 WHERE fr2.topic_id = ft.id) as last_reply_at
             FROM forum_topics ft
             JOIN users u ON ft.user_id = u.id
             WHERE ft.category_id = ?
             ORDER BY ft.updated_at DESC",
            [$categoryId]
        );
    }

    public function findTopicById(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT ft.*, u.username, fc.name as category_name, fc.id as category_id
             FROM forum_topics ft
             JOIN users u ON ft.user_id = u.id
             JOIN forum_categories fc ON ft.category_id = fc.id
             WHERE ft.id = ?",
            [$id]
        );
    }

    public function createTopic(int $categoryId, int $userId, string $title, string $content): int
    {
        return $this->db->insert('forum_topics', [
            'category_id' => $categoryId,
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
        ]);
    }

    public function incrementViews(int $topicId): void
    {
        $this->db->query("UPDATE forum_topics SET views = views + 1 WHERE id = ?", [$topicId]);
    }

    public function getReplies(int $topicId): array
    {
        return $this->db->fetchAll(
            "SELECT fr.*, u.username FROM forum_replies fr
             JOIN users u ON fr.user_id = u.id
             WHERE fr.topic_id = ?
             ORDER BY fr.created_at ASC",
            [$topicId]
        );
    }

    public function createReply(int $topicId, int $userId, string $content): int
    {
        $id = $this->db->insert('forum_replies', [
            'topic_id' => $topicId,
            'user_id' => $userId,
            'content' => $content,
        ]);
        $this->db->query(
            "UPDATE forum_topics SET updated_at = NOW() WHERE id = ?",
            [$topicId]
        );
        return $id;
    }

    public function getTopicsByUser(int $userId, int $limit = 5): array
    {
        return $this->db->fetchAll(
            "SELECT ft.*, fc.name as category_name FROM forum_topics ft
             JOIN forum_categories fc ON ft.category_id = fc.id
             WHERE ft.user_id = ? ORDER BY ft.created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    public function getUserStats(int $userId): array
    {
        $topics = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM forum_topics WHERE user_id = ?", [$userId]
        );
        $replies = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM forum_replies WHERE user_id = ?", [$userId]
        );
        return [
            'topics' => (int) $topics['cnt'],
            'replies' => (int) $replies['cnt'],
        ];
    }
}
