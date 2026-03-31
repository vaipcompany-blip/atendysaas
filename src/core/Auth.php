<?php

declare(strict_types=1);

final class Auth
{
    private static ?array $usersColumnsCache = null;

    public static function check(): bool
    {
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user) || !isset($user['id'])) {
            return false;
        }

        if (self::isSessionExpired()) {
            self::invalidateSession('Sessão expirada por inatividade. Faça login novamente.');
            return false;
        }

        $userType = (string) ($user['type'] ?? 'owner');

        if ($userType === 'team_member') {
            $teamMemberId = (int) ($user['team_member_id'] ?? 0);
            $workspaceId = (int) ($user['id'] ?? 0);
            if ($teamMemberId <= 0 || $workspaceId <= 0) {
                self::invalidateSession();
                return false;
            }

            $stmt = Database::connection()->prepare(
                'SELECT tm.workspace_id
                 FROM team_members tm
                 INNER JOIN users u ON u.id = tm.workspace_id
                 WHERE tm.id = :id
                   AND tm.status = "active"
                   AND tm.deleted_at IS NULL
                   AND u.ativo = 1
                 LIMIT 1'
            );
            $stmt->execute(['id' => $teamMemberId]);
            $row = $stmt->fetch();

            if (!$row || (int) ($row['workspace_id'] ?? 0) !== $workspaceId) {
                self::invalidateSession();
                return false;
            }

            self::touchSession();
            return true;
        }

        $userId = (int) $user['id'];
        $sessionVersion = (int) ($user['session_version'] ?? 0);

        $hasAtivo = self::userHasColumn('ativo');
        $hasSessionVersion = self::userHasColumn('session_version');

        $sql = 'SELECT ' . ($hasSessionVersion ? 'session_version' : '0 AS session_version') . ' FROM users WHERE id = :id';
        if ($hasAtivo) {
            $sql .= ' AND ativo = 1';
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            self::invalidateSession();
            return false;
        }

        $currentVersion = (int) ($row['session_version'] ?? $sessionVersion);
        if ($hasSessionVersion && $sessionVersion !== $currentVersion) {
            self::invalidateSession();
            return false;
        }

        self::touchSession();
        return true;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function attempt(string $login, string $password): bool
    {
        $normalizedLogin = mb_strtolower(trim($login), 'UTF-8');

        $hasAtivo = self::userHasColumn('ativo');
        $hasCpf = self::userHasColumn('cpf');
        $hasSessionVersion = self::userHasColumn('session_version');

        $sql = 'SELECT id, nome_consultorio, email, ' .
            ($hasCpf ? 'cpf' : 'NULL AS cpf') . ', password_hash, ' .
            ($hasSessionVersion ? 'session_version' : '0 AS session_version') .
            ' FROM users WHERE ';

        if ($hasAtivo) {
            $sql .= 'ativo = 1 AND ';
        }

        $sql .= '(email = :login';
        if ($hasCpf) {
            $sql .= ' OR cpf = :login';
        }
        $sql .= ') LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['login' => $normalizedLogin]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            self::establishSession([
                'id' => (int) $user['id'],
                'nome_consultorio' => $user['nome_consultorio'],
                'email' => $user['email'],
                'cpf' => $user['cpf'],
                'session_version' => (int) ($user['session_version'] ?? 1),
                'type' => 'owner',
            ]);

            return true;
        }

        if (!self::teamMemberCredentialsEnabled()) {
            return false;
        }

        $memberStmt = Database::connection()->prepare(
            'SELECT tm.id, tm.workspace_id, tm.email, tm.nome_completo, tm.role, tm.password_hash, u.nome_consultorio
             FROM team_members tm
             INNER JOIN users u ON u.id = tm.workspace_id
             WHERE tm.status = "active"
               AND tm.deleted_at IS NULL
               AND u.ativo = 1
               AND tm.email = :email
             LIMIT 1'
        );
        $memberStmt->execute([
            'email' => $normalizedLogin,
        ]);
        $member = $memberStmt->fetch();

        $memberHash = (string) ($member['password_hash'] ?? '');
        if (!$member || $memberHash === '' || !password_verify($password, $memberHash)) {
            return false;
        }

        self::establishSession([
            'id' => (int) $member['workspace_id'],
            'nome_consultorio' => (string) ($member['nome_consultorio'] ?? ''),
            'email' => (string) ($member['email'] ?? ''),
            'cpf' => null,
            'session_version' => 0,
            'type' => 'team_member',
            'team_member_id' => (int) ($member['id'] ?? 0),
            'team_member_role' => (string) ($member['role'] ?? 'staff'),
            'team_member_name' => (string) ($member['nome_completo'] ?? ''),
        ]);

        $updateLastLogin = Database::connection()->prepare('UPDATE team_members SET last_login = NOW(), updated_at = NOW() WHERE id = :id');
        $updateLastLogin->execute(['id' => (int) $member['id']]);

        return true;
    }

    public static function logout(): void
    {
        self::invalidateSession();
    }

    private static function establishSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        self::touchSession();
    }

    private static function invalidateSession(?string $error = null): void
    {
        unset($_SESSION['user'], $_SESSION['last_activity_at']);
        if ($error !== null && $error !== '') {
            $_SESSION['auth_error'] = $error;
        }
        session_regenerate_id(true);
    }

    private static function touchSession(): void
    {
        $_SESSION['last_activity_at'] = time();
    }

    private static function isSessionExpired(): bool
    {
        $lastActivity = (int) ($_SESSION['last_activity_at'] ?? 0);
        if ($lastActivity <= 0) {
            self::touchSession();
            return false;
        }

        $idleTimeoutSeconds = self::idleTimeoutSeconds();
        if ($idleTimeoutSeconds <= 0) {
            return false;
        }

        return (time() - $lastActivity) > $idleTimeoutSeconds;
    }

    private static function idleTimeoutSeconds(): int
    {
        $minutes = (int) env('SESSION_IDLE_TIMEOUT_MINUTES', '120');
        if ($minutes < 5) {
            $minutes = 5;
        }

        return $minutes * 60;
    }

    private static function teamMemberCredentialsEnabled(): bool
    {
        static $enabled = null;

        if ($enabled !== null) {
            return $enabled;
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT 1
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = "team_members"
                   AND COLUMN_NAME = "password_hash"
                 LIMIT 1'
            );
            $stmt->execute();
            $enabled = (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $enabled = false;
        }

        return $enabled;
    }

    private static function userHasColumn(string $column): bool
    {
        $columns = self::usersColumns();
        return in_array($column, $columns, true);
    }

    private static function usersColumns(): array
    {
        if (is_array(self::$usersColumnsCache)) {
            return self::$usersColumnsCache;
        }

        try {
            $stmt = Database::connection()->query('SHOW COLUMNS FROM users');
            $columns = [];
            foreach ($stmt->fetchAll() as $row) {
                $field = (string) ($row['Field'] ?? '');
                if ($field !== '') {
                    $columns[] = $field;
                }
            }

            self::$usersColumnsCache = $columns;
            return $columns;
        } catch (Throwable $e) {
            self::$usersColumnsCache = [];
            return [];
        }
    }
}

