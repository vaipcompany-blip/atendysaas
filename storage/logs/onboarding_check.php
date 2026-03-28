<?php
require __DIR__ . '/../../src/bootstrap.php';
$service = new WhatsAppService();
$config = $service->getWhatsAppConfig(1);
$client = new WhatsAppCloudClient();
echo 'MODE=' . ($config['mode'] ?? '') . PHP_EOL;
echo 'HAS_PHONE_ID=' . ((($config['phone_number_id'] ?? '') !== '') ? 'yes' : 'no') . PHP_EOL;
echo 'ENABLED=' . ($client->isEnabled($config) ? 'yes' : 'no') . PHP_EOL;
