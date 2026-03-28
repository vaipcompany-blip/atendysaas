<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Este script só pode ser executado via linha de comando.\n";
    exit(1);
}

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

$options = getopt('', ['purge', 'force']);
$purge = array_key_exists('purge', $options);
$force = array_key_exists('force', $options);

if (!app_is_local() && !$force) {
    fwrite(STDERR, "[error] O seed demo só pode ser gerado automaticamente em APP_ENV=local. Use --force se quiser sobrescrever manualmente.\n");
    exit(1);
}

try {
    $manager = new DemoSeedManager();
    $result = $purge ? $manager->protectOrPurgeDemoWorkspace() : $manager->ensureDemoWorkspace();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[error] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
