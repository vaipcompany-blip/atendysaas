<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$service = new WhatsAppService();
$runner = new AutomationJobRunner();
$db = Database::connection();

$users = $db->query('SELECT id FROM users WHERE ativo = 1')->fetchAll();
$total = 0;

foreach ($users as $user) {
    $userId = (int) $user['id'];
    $execution = $runner->runUserAutomation($userId, false, function () use ($service, $userId): array {
        return $service->runAllAutomations($userId);
    });
    if (($execution['status'] ?? '') !== 'success') {
        echo 'Usuário ' . $userId . ': ' . (string) ($execution['message'] ?? 'Execução ignorada.') . PHP_EOL;
        continue;
    }

    $result = (array) ($execution['result'] ?? []);
    $total += (int) $result['total'];

    echo 'Usuário ' . $userId
        . ': confirmações=' . (int) $result['confirmations']
        . ', lembretes=' . (int) $result['reminders']
        . ', follow-up=' . (int) $result['followups']
        . ', total=' . (int) $result['total']
        . PHP_EOL;
}

echo 'Total geral: ' . $total . PHP_EOL;

