<?php
require __DIR__ . '/../../src/bootstrap.php';
$service = new WhatsAppService();
$config = $service->getWhatsAppConfig(1);
echo 'MODE=' . ($config['mode'] ?? '') . PHP_EOL;
echo 'VERIFY=' . ($config['verify_token'] ?? '') . PHP_EOL;
