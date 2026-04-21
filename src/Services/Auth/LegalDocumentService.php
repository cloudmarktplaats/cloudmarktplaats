<?php

namespace App\Services\Auth;

use App\Core\Database;
use App\Models\LegalDocument;

class LegalDocumentService
{
    public function __construct(private LegalDocument $docs)
    {
    }

    public function currentVersions(string $language): array
    {
        return [
            'tos' => $this->docs->latestVersion('tos', $language),
            'privacy' => $this->docs->latestVersion('privacy', $language),
        ];
    }

    public function needsAcceptance(array $user, string $language): bool
    {
        $current = $this->currentVersions($language);
        $userTos = (int) ($user['tos_version'] ?? 0);
        $userPrivacy = (int) ($user['privacy_version'] ?? 0);
        return $userTos < $current['tos'] || $userPrivacy < $current['privacy'];
    }

    public function accept(int $userId, string $language): void
    {
        $current = $this->currentVersions($language);
        $now = date('Y-m-d H:i:s');
        Database::getInstance()->update('users', [
            'tos_version' => $current['tos'],
            'tos_accepted_at' => $now,
            'privacy_version' => $current['privacy'],
            'privacy_accepted_at' => $now,
        ], 'id = ?', [$userId]);
    }

    public function getDocument(string $type, int $version, string $language): array|false
    {
        return $this->docs->findWithFallback($type, $version, $language);
    }
}
