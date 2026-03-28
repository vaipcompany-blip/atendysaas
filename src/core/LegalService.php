<?php

declare(strict_types=1);

final class LegalService
{
    public function currentVersions(): array
    {
        return [
            'terms_version' => (string) env('LEGAL_TERMS_VERSION', 'v1.0'),
            'privacy_version' => (string) env('LEGAL_PRIVACY_VERSION', 'v1.0'),
        ];
    }

    public function legalLinks(): array
    {
        return [
            'terms_url' => base_url('route=legal&doc=terms'),
            'privacy_url' => base_url('route=legal&doc=privacy'),
        ];
    }

    public function registerConsent(int $userId, ?string $ipAddress, ?string $userAgent): void
    {
        $versions = $this->currentVersions();

        $stmt = Database::connection()->prepare(
            'INSERT INTO legal_consents (
                user_id, terms_version, privacy_version, accepted_at, accepted_ip, user_agent, created_at
             ) VALUES (
                :user_id, :terms_version, :privacy_version, NOW(), :accepted_ip, :user_agent, NOW()
             )'
        );
        $stmt->execute([
            'user_id' => $userId,
            'terms_version' => $versions['terms_version'],
            'privacy_version' => $versions['privacy_version'],
            'accepted_ip' => $ipAddress !== null && trim($ipAddress) !== '' ? trim($ipAddress) : null,
            'user_agent' => $userAgent !== null && trim($userAgent) !== '' ? mb_substr(trim($userAgent), 0, 255) : null,
        ]);
    }

    public function latestConsent(int $userId): ?array
    {
        if (!$this->hasConsentTable()) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, terms_version, privacy_version, accepted_at, accepted_ip
             FROM legal_consents
             WHERE user_id = :user_id
             ORDER BY accepted_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function complianceStatus(int $userId): array
    {
        $versions = $this->currentVersions();
        $latest = $this->latestConsent($userId);
        $upToDate = $latest !== null
            && (string) ($latest['terms_version'] ?? '') === $versions['terms_version']
            && (string) ($latest['privacy_version'] ?? '') === $versions['privacy_version'];

        return [
            'up_to_date' => $upToDate,
            'latest_consent' => $latest,
            'required_versions' => $versions,
        ];
    }

    private function hasConsentTable(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "legal_consents"'
        );
        $stmt->execute();
        $row = $stmt->fetch();
        $cached = ((int) ($row['total'] ?? 0)) > 0;

        return $cached;
    }
}