<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = (string) file_get_contents('php://input');
$decoded = json_decode($rawBody, true);

if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kirvano test payloads may arrive as an array with one item.
$payload = $decoded;
if (isset($decoded[0]) && is_array($decoded[0])) {
    $payload = $decoded[0];
}

$expectedToken = trim((string) env('KIRVANO_WEBHOOK_TOKEN', ''));
if ($expectedToken !== '') {
    $receivedToken = trim((string) ($payload['token'] ?? ''));
    if (!hash_equals($expectedToken, $receivedToken)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$event = (string) ($payload['event'] ?? '');
$status = mb_strtoupper(trim((string) ($payload['status'] ?? '')), 'UTF-8');
if ($event !== 'SALE_APPROVED' || $status !== 'APPROVED') {
    echo json_encode([
        'success' => true,
        'message' => 'Ignored event',
        'event' => $event,
        'status' => $status,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$customer = is_array($payload['customer'] ?? null) ? (array) $payload['customer'] : [];
$customerName = trim((string) ($customer['name'] ?? ''));
$email = mb_strtolower(trim((string) ($customer['email'] ?? '')), 'UTF-8');
$phone = preg_replace('/\D+/', '', (string) ($customer['phone_number'] ?? ''));
$document = preg_replace('/\D+/', '', (string) ($customer['document'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Customer email is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($document) !== 11) {
    $document = str_pad(substr((string) crc32($email) . (string) time(), 0, 11), 11, '0');
}

$plan = is_array($payload['plan'] ?? null) ? (array) $payload['plan'] : [];
$chargeFrequency = mb_strtoupper(trim((string) ($plan['charge_frequency'] ?? 'MONTHLY')), 'UTF-8');
$planType = match ($chargeFrequency) {
    'QUARTERLY' => 'quarterly',
    'ANNUALLY' => 'annual',
    default => 'monthly',
};

$db = Database::connection();
$userId = 0;
$createdUser = false;

try {
    $db->beginTransaction();

    $stmtUser = $db->prepare('SELECT id FROM users WHERE email = :email OR cpf = :cpf LIMIT 1');
    $stmtUser->execute([
        'email' => $email,
        'cpf' => $document,
    ]);
    $existing = $stmtUser->fetch();

    if ($existing) {
        $userId = (int) $existing['id'];
    } else {
        $createdUser = true;
        $temporaryPassword = bin2hex(random_bytes(6));
        $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

        $stmtInsert = $db->prepare(
            'INSERT INTO users (email, cpf, password_hash, nome_consultorio, telefone, endereco, ativo, created_at, updated_at)
             VALUES (:email, :cpf, :password_hash, :nome_consultorio, :telefone, :endereco, 1, NOW(), NOW())'
        );
        $stmtInsert->execute([
            'email' => $email,
            'cpf' => $document,
            'password_hash' => $passwordHash,
            'nome_consultorio' => $customerName !== '' ? $customerName : 'Meu Consultorio',
            'telefone' => $phone !== '' ? $phone : '00000000000',
            'endereco' => null,
        ]);

        $userId = (int) $db->lastInsertId();
    }

    $stmtSettings = $db->prepare('SELECT user_id FROM settings WHERE user_id = :user_id LIMIT 1');
    $stmtSettings->execute(['user_id' => $userId]);
    $settingsRow = $stmtSettings->fetch();

    if (!$settingsRow) {
        $verifyToken = 'atendy_' . $userId . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $insertSettings = $db->prepare(
            'INSERT INTO settings (
                user_id, horario_abertura, horario_fechamento, duracao_consulta, intervalo,
                mensagem_confirmacao, whatsapp_mode, whatsapp_api_url, whatsapp_verify_token, whatsapp_default_country,
                meta_conversao_mensal,
                created_at, updated_at
             ) VALUES (
                :user_id, "08:00:00", "18:00:00", 60, 10,
                :mensagem_confirmacao, "cloud", "https://graph.facebook.com/v20.0", :whatsapp_verify_token, "55",
                60.00,
                NOW(), NOW()
             )'
        );
        $insertSettings->execute([
            'user_id' => $userId,
            'mensagem_confirmacao' => 'Ola {{nome}}! Sua consulta sera em {{data_hora}}. Responda SIM para confirmar.',
            'whatsapp_verify_token' => $verifyToken,
        ]);
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    AppLogger::error('Standalone webhook failed to create user', [
        'email' => $email,
        'error' => $e->getMessage(),
    ]);

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create user'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $reference = 'kirvano:' . trim((string) ($payload['sale_id'] ?? 'approved'));
    (new BillingService())->activatePlan($userId, $planType, $reference);
} catch (Throwable $e) {
    AppLogger::error('Standalone webhook failed to activate plan', [
        'user_id' => $userId,
        'plan_type' => $planType,
        'error' => $e->getMessage(),
    ]);

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'User created but plan activation failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Webhook processed successfully',
    'user_id' => $userId,
    'created_user' => $createdUser,
    'plan_type' => $planType,
], JSON_UNESCAPED_UNICODE);
