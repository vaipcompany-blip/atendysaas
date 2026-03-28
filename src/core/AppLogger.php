<?php

declare(strict_types=1);

final class AppLogger
{
    private const MAX_LOG_SIZE_BYTES = 1048576;
    private const TRUNCATED_LINES = 5000;

    public static function registerHandlers(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        self::error('PHP error', [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ]);

        return false;
    }

    public static function handleException(Throwable $e): void
    {
        self::error('Uncaught exception', [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }

        if ((string) env('APP_ENV', 'local') === 'local') {
            echo 'Erro interno: ' . $e->getMessage();
            return;
        }

        echo 'Erro interno do servidor.';
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
            return;
        }

        self::error('Fatal shutdown error', $error);
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        $line = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $logFile = self::logFilePath();
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($logFile, json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        self::rotateIfNeeded($logFile);
    }

    private static function logFilePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs/app.log';
    }

    private static function rotateIfNeeded(string $logFile): void
    {
        if (!is_file($logFile) || filesize($logFile) <= self::MAX_LOG_SIZE_BYTES) {
            return;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines) || count($lines) <= self::TRUNCATED_LINES) {
            return;
        }

        file_put_contents($logFile, implode(PHP_EOL, array_slice($lines, -self::TRUNCATED_LINES)) . PHP_EOL, LOCK_EX);
    }
}
