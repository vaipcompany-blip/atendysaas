<?php

declare(strict_types=1);

final class ObservabilityService
{
    public function buildWorkspaceOverview(int $userId): array
    {
        $jobs24h = $this->jobStatusSummary($userId, 24);
        $jobs7d = $this->jobStatusSummary($userId, 24 * 7);
        $automation24h = $this->automationSummary($userId, 24);
        $automation7d = $this->automationSummary($userId, 24 * 7);

        return [
            'jobs_24h' => $jobs24h,
            'jobs_7d' => $jobs7d,
            'automation_24h' => $automation24h,
            'automation_7d' => $automation7d,
            'recent_failed_jobs' => $this->recentFailedJobs($userId, 8),
            'recent_job_runs' => $this->recentJobRuns($userId, 12),
            'app_errors_24h' => $this->appErrorSummary(24),
            'app_errors_7d' => $this->appErrorSummary(24 * 7),
        ];
    }

    public function latestWorkspaceStatus(int $userId): array
    {
        $jobs = $this->jobStatusSummary($userId, 24);
        $appErrors = $this->appErrorSummary(24);

        $status = 'ok';
        $alerts = [];

        if (($jobs['failed'] ?? 0) > 0) {
            $status = 'warn';
            $alerts[] = 'Há execuções de automação com falha nas últimas 24h.';
        }

        if (($jobs['success'] ?? 0) === 0) {
            $status = 'warn';
            $alerts[] = 'Nenhuma automação concluída com sucesso nas últimas 24h.';
        }

        if (($appErrors['total'] ?? 0) >= 10) {
            $status = 'warn';
            $alerts[] = 'Volume alto de erros no app.log nas últimas 24h.';
        }

        return [
            'status' => $status,
            'alerts' => $alerts,
        ];
    }

    private function jobStatusSummary(int $userId, int $hours): array
    {
        if (!$this->tableExists('job_executions')) {
            return [
                'hours' => $hours,
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'skipped' => 0,
                'running' => 0,
                'latest_success_at' => null,
                'latest_failure_at' => null,
            ];
        }

        $stmt = Database::connection()->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) AS success_count,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN status = "skipped" THEN 1 ELSE 0 END) AS skipped_count,
                SUM(CASE WHEN status = "running" THEN 1 ELSE 0 END) AS running_count,
                MAX(CASE WHEN status = "success" THEN finished_at ELSE NULL END) AS latest_success_at,
                MAX(CASE WHEN status = "failed" THEN finished_at ELSE NULL END) AS latest_failure_at
             FROM job_executions
             WHERE user_id = :user_id
               AND started_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('hours', $hours, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch() ?: [];

        return [
            'hours' => $hours,
            'total' => (int) ($row['total'] ?? 0),
            'success' => (int) ($row['success_count'] ?? 0),
            'failed' => (int) ($row['failed_count'] ?? 0),
            'skipped' => (int) ($row['skipped_count'] ?? 0),
            'running' => (int) ($row['running_count'] ?? 0),
            'latest_success_at' => $row['latest_success_at'] ?? null,
            'latest_failure_at' => $row['latest_failure_at'] ?? null,
        ];
    }

    private function automationSummary(int $userId, int $hours): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status_envio = "enviado" THEN 1 ELSE 0 END) AS sent_count,
                SUM(CASE WHEN status_envio = "erro" THEN 1 ELSE 0 END) AS error_count,
                COUNT(DISTINCT tipo_automacao) AS automation_types
             FROM automation_logs
             WHERE user_id = :user_id
               AND timestamp >= DATE_SUB(NOW(), INTERVAL :hours HOUR)'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('hours', $hours, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch() ?: [];

        return [
            'hours' => $hours,
            'total' => (int) ($row['total'] ?? 0),
            'sent' => (int) ($row['sent_count'] ?? 0),
            'errors' => (int) ($row['error_count'] ?? 0),
            'automation_types' => (int) ($row['automation_types'] ?? 0),
        ];
    }

    private function recentFailedJobs(int $userId, int $limit): array
    {
        if (!$this->tableExists('job_executions')) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, job_type, lock_key, status, error_message, started_at, finished_at
             FROM job_executions
             WHERE user_id = :user_id AND status = "failed"
             ORDER BY started_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function recentJobRuns(int $userId, int $limit): array
    {
        if (!$this->tableExists('job_executions')) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, job_type, status, dry_run, started_at, finished_at, error_message
             FROM job_executions
             WHERE user_id = :user_id
             ORDER BY started_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function appErrorSummary(int $hours): array
    {
        $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
        if (!is_file($logFile)) {
            return [
                'hours' => $hours,
                'total' => 0,
                'by_message' => [],
            ];
        }

        $threshold = time() - ($hours * 3600);
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || $lines === []) {
            return [
                'hours' => $hours,
                'total' => 0,
                'by_message' => [],
            ];
        }

        $lines = array_reverse($lines);
        $count = 0;
        $messages = [];

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            if ((string) ($decoded['level'] ?? '') !== 'error') {
                continue;
            }

            $ts = strtotime((string) ($decoded['timestamp'] ?? ''));
            if ($ts === false || $ts < $threshold) {
                continue;
            }

            $count++;
            $message = trim((string) ($decoded['message'] ?? 'erro_sem_mensagem'));
            if ($message === '') {
                $message = 'erro_sem_mensagem';
            }

            $messages[$message] = ($messages[$message] ?? 0) + 1;
            if ($count >= 500) {
                break;
            }
        }

        arsort($messages);
        $top = array_slice($messages, 0, 6, true);
        $formatted = [];
        foreach ($top as $message => $qty) {
            $formatted[] = [
                'message' => $message,
                'count' => $qty,
            ];
        }

        return [
            'hours' => $hours,
            'total' => $count,
            'by_message' => $formatted,
        ];
    }

    private function tableExists(string $table): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => $table]);
        $row = $stmt->fetch();

        return ((int) ($row['total'] ?? 0)) > 0;
    }
}
