<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__);
$envPath = $rootPath . DIRECTORY_SEPARATOR . '.env';
if (is_file($envPath)) {
    $envValues = parse_ini_file($envPath, false, INI_SCANNER_TYPED);
    if (is_array($envValues)) {
        foreach ($envValues as $key => $value) {
            $_ENV[$key] = (string) $value;
        }
    }
}

require_once $rootPath . '/src/core/Helpers.php';
require_once $rootPath . '/src/core/Database.php';
require_once $rootPath . '/src/core/DemoSeedManager.php';
require_once $rootPath . '/src/core/MigrationRunner.php';

$verbose = !in_array('--quiet', $argv, true);

try {
    $runner = new MigrationRunner($rootPath);
    $result = $runner->run($verbose);
    $demoSeedResult = (new DemoSeedManager())->reconcile();

    if ($verbose) {
        echo 'executed=' . count($result['executed']) . ' skipped=' . count($result['skipped']) . PHP_EOL;
        echo 'demo_seed=' . (string) ($demoSeedResult['action'] ?? 'unknown') . PHP_EOL;
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[error] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
