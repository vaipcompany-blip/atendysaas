<?php

declare(strict_types=1);

final class BackupService
{
    public function createDatabaseBackup(): array
    {
        $backupDir = dirname(__DIR__, 2) . '/storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Ymd-His');
        $database = Database::databaseName();
        $fileName = $database . '-backup-' . $timestamp . '.sql';
        $filePath = $backupDir . '/' . $fileName;

        $command = $this->buildCommand($filePath);
        $stderrFile = tempnam(sys_get_temp_dir(), 'atendy_backup_err_');
        if ($stderrFile === false) {
            throw new RuntimeException('Não foi possível preparar o arquivo temporário de erro do backup.');
        }

        $password = (string) env('DB_PASSWORD', '');
        if ($password !== '') {
            putenv('MYSQL_PWD=' . $password);
        }

        $output = [];
        exec($command . ' 2>' . $this->escapeCommandArg($stderrFile), $output, $exitCode);

        $stderr = is_file($stderrFile) ? (string) file_get_contents($stderrFile) : '';
        @unlink($stderrFile);
        if ($password !== '') {
            putenv('MYSQL_PWD');
        }

        if ($exitCode !== 0 || !is_file($filePath) || filesize($filePath) === 0) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
            throw new RuntimeException('Falha ao gerar backup do banco.' . ($stderr !== '' ? ' ' . trim($stderr) : ''));
        }

        AppLogger::info('Database backup generated', [
            'file' => $fileName,
            'size' => filesize($filePath),
        ]);

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'size' => filesize($filePath),
            'created_at' => date('c'),
        ];
    }

    public function listRecentBackups(int $limit = 10): array
    {
        $backupDir = dirname(__DIR__, 2) . '/storage/backups';
        if (!is_dir($backupDir)) {
            return [];
        }

        $files = glob($backupDir . '/*.sql');
        if ($files === false) {
            return [];
        }

        usort($files, static function (string $left, string $right): int {
            return filemtime($right) <=> filemtime($left);
        });

        $files = array_slice($files, 0, $limit);

        return array_map(static function (string $filePath): array {
            return [
                'file_name' => basename($filePath),
                'file_path' => $filePath,
                'size' => filesize($filePath) ?: 0,
                'modified_at' => date('c', filemtime($filePath) ?: time()),
            ];
        }, $files);
    }

    private function buildCommand(string $filePath): string
    {
        $host = (string) env('DB_HOST', '127.0.0.1');
        $port = (string) env('DB_PORT', '3306');
        $user = (string) env('DB_USERNAME', 'root');
        $database = Database::databaseName();
        $defaultPath = PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'mysqldump';
        $dumpPath = (string) env('MYSQLDUMP_PATH', $defaultPath);

        return sprintf(
            '%s --host=%s --port=%s --user=%s --default-character-set=utf8mb4 --single-transaction --skip-lock-tables --result-file=%s %s',
            $this->escapeCommandArg($dumpPath),
            $this->escapeCommandArg($host),
            $this->escapeCommandArg($port),
            $this->escapeCommandArg($user),
            $this->escapeCommandArg($filePath),
            $this->escapeCommandArg($database)
        );
    }

    private function escapeCommandArg(string $value): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return escapeshellarg($value);
    }
}


