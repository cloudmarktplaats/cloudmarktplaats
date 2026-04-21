<?php

namespace App\Models;

use App\Core\Database;

class LegalDocument
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function latestVersion(string $type, string $language): int
    {
        $row = $this->db->fetch(
            "SELECT MAX(version) AS v FROM legal_documents
             WHERE type = ? AND language = ? AND published_at <= NOW()",
            [$type, $language]
        );
        return (int) ($row['v'] ?? 0);
    }

    public function find(string $type, int $version, string $language): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM legal_documents
             WHERE type = ? AND version = ? AND language = ?",
            [$type, $version, $language]
        );
    }

    public function findWithFallback(string $type, int $version, string $language): array|false
    {
        $doc = $this->find($type, $version, $language);
        if ($doc !== false) {
            return $doc;
        }
        if ($language !== 'nl') {
            return $this->find($type, $version, 'nl');
        }
        return false;
    }
}
