#!/usr/bin/env php
<?php

/**
 * Atendy �?" scripts/cron.php
 *
 * Script de automações periódicas. Deve ser chamado a cada 15 minutos via cron.
 *
 * �"?�"? Configuração do cron (Linux/macOS) �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
 * Abra o crontab: crontab -e
 * Adicione a linha abaixo (ajuste os caminhos):
 *
 *   * /15 * * * * /usr/bin/php /var/www/html/Aula-SQL/scripts/cron.php >> /var/www/html/Aula-SQL/storage/logs/cron.log 2>&1
 *
 * �"?�"? Configuração no Windows (XAMPP) �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
 * Use o Agendador de Tarefas do Windows:
 *   Programa: C:\xampp\php\php.exe
 *   Argumentos: C:\xampp\htdocs\Aula-SQL\scripts\cron.php
 *   Frequência: A cada 15 minutos
 *
 * �"?�"? Execução manual para teste �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
 *   php scripts/cron.php
 *   php scripts/cron.php --user-id=1       (rodar só para um dentista)
 *   php scripts/cron.php --dry-run          (simula sem enviar mensagens)
 */

declare(strict_types=1);

// �"?�"? Garante que só rode via CLI �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Este script só pode ser executado via linha de comando.\n";
    exit(1);
}

// �"?�"? Lock file: evita execuções simultâneas �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
$lockFile = __DIR__ . '/../storage/cron.lock';
$lockDir  = dirname($lockFile);

if (!is_dir($lockDir)) {
    mkdir($lockDir, 0755, true);
}

$lock = fopen($lockFile, 'w');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo '[' . date('Y-m-d H:i:s') . '] SKIP: outra instância do cron já está rodando.' . PHP_EOL;
    exit(0);
}

// �"?�"? Bootstrap �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
require_once dirname(__DIR__) . '/src/bootstrap.php';

// �"?�"? Argumentos CLI �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
$options = getopt('', ['user-id:', 'dry-run']);
$dryRun       = array_key_exists('dry-run', $options);
$targetUserId = isset($options['user-id']) ? (int) $options['user-id'] : null;

if ($dryRun) {
    echo '[' . date('Y-m-d H:i:s') . '] MODO DRY-RUN ativo �?" nenhuma mensagem será enviada.' . PHP_EOL;
}

// �"?�"? Logger simples �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
function cron_log(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    echo $line;

    $logDir  = dirname(__DIR__) . '/storage/logs';
    $logFile = $logDir . '/cron.log';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// �"?�"? Busca usuários ativos �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
try {
    $db = Database::connection();

    if ($targetUserId !== null) {
        $stmt = $db->prepare('SELECT id FROM users WHERE id = :id AND ativo = 1 LIMIT 1');
        $stmt->execute(['id' => $targetUserId]);
    } else {
        $stmt = $db->query('SELECT id FROM users WHERE ativo = 1 ORDER BY id ASC');
    }

    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    cron_log('ERRO ao buscar usuários: ' . $e->getMessage());
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(1);
}

if (empty($users)) {
    cron_log('Nenhum usuário ativo encontrado. Encerrando.');
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(0);
}

// �"?�"? Executa automações por usuário �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
$service = new WhatsAppService();
$runner = new AutomationJobRunner();
$totalAll = ['confirmations' => 0, 'reminders' => 0, 'followups' => 0, 'total' => 0];

foreach ($users as $userId) {
    $userId = (int) $userId;
    cron_log("Iniciando automações para user_id={$userId}...");

    try {
        if ($dryRun) {
            cron_log("  [dry-run] Simularia runAllAutomations para user_id={$userId}");
            continue;
        }

        $execution = $runner->runUserAutomation($userId, $dryRun, function () use ($service, $userId, $dryRun): array {
            if ($dryRun) {
                return [
                    'confirmations' => 0,
                    'reminders' => 0,
                    'followups' => 0,
                    'total' => 0,
                ];
            }

            return $service->runAllAutomations($userId);
        });

        if (($execution['status'] ?? '') === 'skipped') {
            cron_log("  user_id={$userId} �?' SKIP: " . (string) ($execution['message'] ?? 'automação em andamento'));
            continue;
        }

        if (($execution['status'] ?? '') === 'failed') {
            cron_log("  user_id={$userId} �?' ERRO: " . (string) ($execution['message'] ?? 'falha na execução'));
            continue;
        }

        $result = (array) ($execution['result'] ?? []);

        cron_log(sprintf(
            '  user_id=%d �?' confirmações=%d | lembretes=%d | follow-up=%d | total=%d',
            $userId,
            (int) $result['confirmations'],
            (int) $result['reminders'],
            (int) $result['followups'],
            (int) $result['total']
        ));

        $totalAll['confirmations'] += (int) $result['confirmations'];
        $totalAll['reminders']     += (int) $result['reminders'];
        $totalAll['followups']     += (int) $result['followups'];
        $totalAll['total']         += (int) $result['total'];

    } catch (Throwable $e) {
        cron_log("  ERRO em user_id={$userId}: " . $e->getMessage());
    }
}

cron_log(sprintf(
    'CONCLUÍDO �?" Total geral: confirmações=%d | lembretes=%d | follow-up=%d | total=%d',
    $totalAll['confirmations'],
    $totalAll['reminders'],
    $totalAll['followups'],
    $totalAll['total']
));

// �"?�"? Rotação de log (mantém últimas 5.000 linhas) �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
$logFile = dirname(__DIR__) . '/storage/logs/cron.log';
if (file_exists($logFile) && filesize($logFile) > 512 * 1024) {
    $lines = file($logFile);
    if (is_array($lines) && count($lines) > 5000) {
        file_put_contents($logFile, implode('', array_slice($lines, -5000)), LOCK_EX);
    }
}

// �"?�"? Libera o lock �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
flock($lock, LOCK_UN);
fclose($lock);

exit(0);


