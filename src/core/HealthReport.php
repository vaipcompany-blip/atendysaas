<?php

declare(strict_types=1);

final class HealthReport
{
    public function generate(bool $detailed = true): array
    {
        $checks = [];

        $checks['database'] = $this->checkDatabase();
        $checks['storage_logs'] = $this->checkStorageLogs();
        $checks['migrations'] = $this->checkMigrations();
        $checks['php'] = $this->checkPhp();
        $checks['mail'] = $this->checkMailConfiguration();
        $checks['automations'] = $this->checkAutomationHealth();

        $overall = 'ok';
        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? 'fail');
            if ($status === 'fail') {
                $overall = 'fail';
                break;
            }
            if ($status === 'warn' && $overall !== 'fail') {
                $overall = 'warn';
            }
        }

        $report = [
            'status' => $overall,
            'app' => (string) env('APP_NAME', 'Atendy'),
            'environment' => (string) env('APP_ENV', 'local'),
            'generated_at' => date('c'),
            'checks' => $checks,
        ];

        if ($detailed) {
            return $report;
        }

        return $this->summarize($report);
    }

    private function summarize(array $report): array
    {
        $checks = (array) ($report['checks'] ?? []);
        $summary = [];

        foreach ($checks as $name => $check) {
            $summary[$name] = [
                'status' => (string) ($check['status'] ?? 'fail'),
            ];
        }

        return [
            'status' => (string) ($report['status'] ?? 'fail'),
            'app' => (string) ($report['app'] ?? 'Atendy'),
            'generated_at' => (string) ($report['generated_at'] ?? date('c')),
            'checks' => $summary,
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $stmt = Database::connection()->query('SELECT 1 AS ok');
            $row = $stmt ? $stmt->fetch() : null;

            return [
                'status' => ((int) ($row['ok'] ?? 0) === 1) ? 'ok' : 'fail',
                'database' => Database::databaseName(),
            ];
        } catch (Throwable $e) {
            AppLogger::error('Health check database failed', ['message' => $e->getMessage()]);
            return [
                'status' => 'fail',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkStorageLogs(): array
    {
        $logsPath = dirname(__DIR__, 2) . '/storage/logs';
        $exists = is_dir($logsPath);
        $writable = $exists && is_writable($logsPath);

        return [
            'status' => ($exists && $writable) ? 'ok' : 'fail',
            'path' => $logsPath,
            'exists' => $exists,
            'writable' => $writable,
        ];
    }

    private function checkMigrations(): array
    {
        try {
            $db = Database::connection();
            $db->query('SELECT 1 FROM schema_migrations LIMIT 1');

            $files = glob(dirname(__DIR__, 2) . '/tables/*.sql');
            $files = $files === false ? [] : $files;
            sort($files, SORT_NATURAL);
            $fileNames = array_map('basename', $files);

            $stmt = $db->query('SELECT file_name FROM schema_migrations ORDER BY file_name ASC');
            $applied = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            $pending = array_values(array_diff($fileNames, $applied));

            return [
                'status' => empty($pending) ? 'ok' : 'warn',
                'applied_count' => count($applied),
                'pending_count' => count($pending),
                'pending' => $pending,
            ];
        } catch (Throwable $e) {
            AppLogger::error('Health check migrations failed', ['message' => $e->getMessage()]);
            return [
                'status' => 'fail',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkPhp(): array
    {
        $version = PHP_VERSION;
        $status = version_compare($version, '8.1.0', '>=') ? 'ok' : 'warn';

        return [
            'status' => $status,
            'version' => $version,
            'sapi' => PHP_SAPI,
        ];
    }

    private function checkMailConfiguration(): array
    {
        $host = trim((string) env('MAIL_HOST', ''));
        $from = trim((string) env('MAIL_FROM_ADDRESS', ''));
        $configured = $host !== '' && $from !== '';

        return [
            'status' => $configured ? 'ok' : 'warn',
            'configured' => $configured,
            'host' => $host !== '' ? $host : null,
        ];
    }

    private function checkAutomationHealth(): array
    {
        try {
            $stmt = Database::connection()->query(
                'SELECT
                    SUM(CASE WHEN status = "success" AND started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS success_24h,
                    SUM(CASE WHEN status = "failed" AND started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS failed_24h,
                    MAX(CASE WHEN status = "success" THEN finished_at ELSE NULL END) AS latest_success_at
                 FROM job_executions'
            );
            $row = $stmt ? $stmt->fetch() : [];

            $success24h = (int) ($row['success_24h'] ?? 0);
            $failed24h = (int) ($row['failed_24h'] ?? 0);
            $latestSuccessAt = $row['latest_success_at'] ?? null;

            $status = 'ok';
            if ($success24h === 0 || $failed24h > 0) {
                $status = 'warn';
            }

            return [
                'status' => $status,
                'success_24h' => $success24h,
                'failed_24h' => $failed24h,
                'latest_success_at' => $latestSuccessAt,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'warn',
                'message' => 'Tabela job_executions ainda não disponível para monitoramento.',
            ];
        }
    }
}


