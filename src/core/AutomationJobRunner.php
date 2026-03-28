<?php

declare(strict_types=1);

final class AutomationJobRunner
{
    public function runUserAutomation(int $userId, bool $dryRun, callable $callback): array
    {
        return $this->runExclusive('automations_user', $this->buildUserLockKey($userId), $userId, $dryRun, $callback);
    }

    private function runExclusive(string $jobType, string $lockKey, ?int $userId, bool $dryRun, callable $callback): array
    {
        $executionId = $this->createExecution($jobType, $lockKey, $userId, $dryRun);

        if (!$this->acquireLock($lockKey)) {
            $message = 'Execução ignorada: já existe uma automação em andamento para este workspace.';
            $this->finalizeExecution($executionId, 'skipped', [], $message);

            return [
                'status' => 'skipped',
                'message' => $message,
                'result' => [
                    'confirmations' => 0,
                    'reminders' => 0,
                    'followups' => 0,
                    'total' => 0,
                ],
                'execution_id' => $executionId,
            ];
        }

        try {
            $result = (array) $callback();
            $this->finalizeExecution($executionId, 'success', $result, null);

            return [
                'status' => 'success',
                'message' => 'Automações executadas com sucesso.',
                'result' => $result,
                'execution_id' => $executionId,
            ];
        } catch (Throwable $e) {
            $this->finalizeExecution($executionId, 'failed', [], $e->getMessage());

            return [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'result' => [
                    'confirmations' => 0,
                    'reminders' => 0,
                    'followups' => 0,
                    'total' => 0,
                ],
                'execution_id' => $executionId,
            ];
        } finally {
            $this->releaseLock($lockKey);
        }
    }

    private function buildUserLockKey(int $userId): string
    {
        return 'atendy:automations:user:' . $userId;
    }

    private function acquireLock(string $lockKey): bool
    {
        $stmt = Database::connection()->prepare('SELECT GET_LOCK(:lock_key, 0) AS acquired');
        $stmt->execute(['lock_key' => $lockKey]);
        $row = $stmt->fetch();

        return (int) ($row['acquired'] ?? 0) === 1;
    }

    private function releaseLock(string $lockKey): void
    {
        try {
            $stmt = Database::connection()->prepare('SELECT RELEASE_LOCK(:lock_key)');
            $stmt->execute(['lock_key' => $lockKey]);
        } catch (Throwable $e) {
        }
    }

    private function createExecution(string $jobType, string $lockKey, ?int $userId, bool $dryRun): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO job_executions (
                job_type, lock_key, user_id, dry_run, status, started_at, created_at, updated_at
             ) VALUES (
                :job_type, :lock_key, :user_id, :dry_run, "running", NOW(), NOW(), NOW()
             )'
        );
        $stmt->execute([
            'job_type' => $jobType,
            'lock_key' => $lockKey,
            'user_id' => $userId,
            'dry_run' => $dryRun ? 1 : 0,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function finalizeExecution(int $executionId, string $status, array $result, ?string $error): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE job_executions
             SET status = :status,
                 result_json = :result_json,
                 error_message = :error_message,
                 finished_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $executionId,
            'status' => $status,
            'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'error_message' => $error !== null ? mb_substr($error, 0, 500) : null,
        ]);
    }
}

