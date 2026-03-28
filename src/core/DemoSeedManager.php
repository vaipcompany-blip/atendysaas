<?php

declare(strict_types=1);

final class DemoSeedManager
{
    private const DEMO_EMAIL = 'admin@atendy.local';
    private const DEMO_CPF = '11122233344';
    private const DEMO_PASSWORD_HASH = '$2y$10$XNZTD8MW8mSjVh4d/wGbWuufw.fPq2kMxLLZaUFeM0WqRjGVLkoB6';
    private const DEMO_CLINIC_NAME = 'Clínica Atendy Demo';
    private const DEMO_PHONE = '(11) 90000-0000';
    private const DEMO_ADDRESS = 'Rua Exemplo, 100';
    private const DEMO_VERIFY_TOKEN = 'atendy_verify_token';

    public function reconcile(): array
    {
        if ($this->demoSeedEnabled()) {
            return $this->ensureDemoWorkspace();
        }

        return $this->protectOrPurgeDemoWorkspace();
    }

    public function ensureDemoWorkspace(): array
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $userId = $this->findDemoUserId();
            if ($userId === null) {
                $insertUser = $db->prepare(
                    'INSERT INTO users (email, cpf, password_hash, nome_consultorio, telefone, endereco, ativo, created_at, updated_at)
                     VALUES (:email, :cpf, :password_hash, :nome_consultorio, :telefone, :endereco, 1, NOW(), NOW())'
                );
                $insertUser->execute([
                    'email' => self::DEMO_EMAIL,
                    'cpf' => self::DEMO_CPF,
                    'password_hash' => self::DEMO_PASSWORD_HASH,
                    'nome_consultorio' => self::DEMO_CLINIC_NAME,
                    'telefone' => self::DEMO_PHONE,
                    'endereco' => self::DEMO_ADDRESS,
                ]);
                $userId = (int) $db->lastInsertId();
                $action = 'seeded';
            } else {
                $updateUser = $db->prepare(
                    'UPDATE users
                     SET password_hash = :password_hash,
                         nome_consultorio = :nome_consultorio,
                         telefone = :telefone,
                         endereco = :endereco,
                         ativo = 1,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $updateUser->execute([
                    'id' => $userId,
                    'password_hash' => self::DEMO_PASSWORD_HASH,
                    'nome_consultorio' => self::DEMO_CLINIC_NAME,
                    'telefone' => self::DEMO_PHONE,
                    'endereco' => self::DEMO_ADDRESS,
                ]);
                $action = 'refreshed';
            }

            $insertSettings = $db->prepare(
                'INSERT INTO settings (
                    user_id, horario_abertura, horario_fechamento, duracao_consulta, intervalo,
                    mensagem_confirmacao, whatsapp_mode, whatsapp_api_url, whatsapp_verify_token, whatsapp_default_country,
                    meta_conversao_mensal, created_at, updated_at
                 ) VALUES (
                    :user_id, "08:00:00", "18:00:00", 60, 10,
                    :mensagem_confirmacao, "mock", "https://graph.facebook.com/v20.0", :verify_token, "55",
                    60.00, NOW(), NOW()
                 )
                 ON DUPLICATE KEY UPDATE
                    mensagem_confirmacao = VALUES(mensagem_confirmacao),
                    whatsapp_mode = VALUES(whatsapp_mode),
                    whatsapp_api_url = VALUES(whatsapp_api_url),
                    whatsapp_verify_token = VALUES(whatsapp_verify_token),
                    whatsapp_default_country = VALUES(whatsapp_default_country),
                    updated_at = NOW()'
            );
            $insertSettings->execute([
                'user_id' => $userId,
                'mensagem_confirmacao' => 'Olá! Sua consulta é amanhã. Você confirma?',
                'verify_token' => self::DEMO_VERIFY_TOKEN,
            ]);

            $db->commit();

            return [
                'action' => $action,
                'user_id' => $userId,
                'email' => self::DEMO_EMAIL,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public function protectOrPurgeDemoWorkspace(): array
    {
        $userId = $this->findDemoUserId();
        if ($userId === null) {
            return ['action' => 'absent'];
        }

        if ($this->hasMeaningfulData($userId)) {
            $this->disableDemoWorkspace($userId);

            return [
                'action' => 'disabled',
                'user_id' => $userId,
                'email' => self::DEMO_EMAIL,
            ];
        }

        $this->purgeDemoWorkspace($userId);

        return [
            'action' => 'purged',
            'user_id' => $userId,
            'email' => self::DEMO_EMAIL,
        ];
    }

    private function demoSeedEnabled(): bool
    {
        return app_is_local() && env_bool('APP_ENABLE_DEMO_SEED', true);
    }

    private function findDemoUserId(): ?int
    {
        $stmt = Database::connection()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => self::DEMO_EMAIL]);
        $userId = $stmt->fetchColumn();

        return $userId !== false ? (int) $userId : null;
    }

    private function hasMeaningfulData(int $userId): bool
    {
        $db = Database::connection();
        $checks = [
            'SELECT COUNT(*) FROM patients WHERE user_id = :user_id',
            'SELECT COUNT(*) FROM appointments WHERE user_id = :user_id',
            'SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = :user_id',
            'SELECT COUNT(*) FROM automation_logs WHERE user_id = :user_id',
            'SELECT COUNT(*) FROM team_members WHERE workspace_id = :user_id',
            'SELECT COUNT(*) FROM security_events WHERE user_id = :user_id',
            'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id',
            'SELECT COUNT(*) FROM clinic_blocked_dates WHERE user_id = :user_id',
            'SELECT COUNT(*) FROM whatsapp_auto_replies WHERE user_id = :user_id',
        ];

        foreach ($checks as $sql) {
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute(['user_id' => $userId]);
                if ((int) $stmt->fetchColumn() > 0) {
                    return true;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return false;
    }

    private function disableDemoWorkspace(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET password_hash = :password_hash,
                 ativo = 0,
                 session_version = session_version + 1,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
        ]);
    }

    private function purgeDemoWorkspace(int $userId): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $cleanupStatements = [
                'DELETE FROM settings WHERE user_id = :user_id',
                'DELETE FROM login_attempts WHERE login_identifier IN (:email, :cpf)',
                'DELETE FROM users WHERE id = :user_id',
            ];

            $stmtSettings = $db->prepare('DELETE FROM settings WHERE user_id = :user_id');
            $stmtSettings->execute(['user_id' => $userId]);

            try {
                $stmtAttempts = $db->prepare('DELETE FROM login_attempts WHERE login_identifier IN (:email, :cpf)');
                $stmtAttempts->execute([
                    'email' => self::DEMO_EMAIL,
                    'cpf' => self::DEMO_CPF,
                ]);
            } catch (Throwable $e) {
            }

            $stmtUser = $db->prepare('DELETE FROM users WHERE id = :user_id');
            $stmtUser->execute(['user_id' => $userId]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }
}

