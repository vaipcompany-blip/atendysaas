<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Este script só pode ser executado via linha de comando.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/src/bootstrap.php';

$report = (new HealthReport())->generate(true);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(((string) ($report['status'] ?? 'fail')) === 'fail' ? 1 : 0);

