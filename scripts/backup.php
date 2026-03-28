<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Este script só pode ser executado via linha de comando.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/src/bootstrap.php';

try {
    $result = (new BackupService())->createDatabaseBackup();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    AppLogger::error('CLI backup failed', ['message' => $e->getMessage()]);
    fwrite(STDERR, '[error] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

