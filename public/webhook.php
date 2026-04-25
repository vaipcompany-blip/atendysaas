<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * @param array<mixed> $decoded
 * @return array<mixed>
 */
function normalize_webhook_payload(array $decoded): array
{
    $payload = $decoded;

    if (array_keys($decoded) === range(0, count($decoded) - 1) && isset($decoded[0]) && is_array($decoded[0])) {
        $payload = $decoded[0];
    }

    if (isset($payload['data']) && is_array($payload['data'])) {
        $payload = array_merge($payload, $payload['data']);
    }

    return $payload;
}

function payload_get(array $payload, array $paths, string $default = ''): string
{
    foreach ($paths as $path) {
        $segments = explode('.', $path);
        $value = $payload;
        $found = true;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $found = false;
                break;
            }
            $value = $value[$segment];
        }

        if ($found && !is_array($value)) {
            $stringValue = trim((string) $value);
            if ($stringValue !== '') {
                return $stringValue;
            }
        }
    }

    return $default;
}

function db_column_exists(PDO $db, string $table, string $column): bool
{
    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) ($stmt->fetch()['total'] ?? 0) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function normalize_frequency_label(string $value): string
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii === false) {
        $ascii = $value;
    }

    $upper = mb_strtoupper(trim($ascii), 'UTF-8');
    return (string) preg_replace('/[^A-Z]/', '', $upper);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = (string) file_get_contents('php://input');
$decoded = json_decode($rawBody, true);

if (!is_array($decoded)) {
    AppLogger::error('Standalone webhook received invalid JSON', [
        'raw_body' => mb_substr($rawBody, 0, 1000, 'UTF-8'),
    ]);

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = normalize_webhook_payload($decoded);

$expectedToken = trim((string) env('KIRVANO_WEBHOOK_TOKEN', ''));
if ($expectedToken !== '') {
    $receivedToken = payload_get($payload, ['token', 'webhook_token', 'secret']);
    if ($receivedToken === '') {
        $receivedToken = trim((string) ($_SERVER['HTTP_X_KIRVANO_TOKEN'] ?? ''));
    }
    if ($receivedToken === '') {
        $receivedToken = trim((string) ($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ''));
    }
    if ($receivedToken === '') {
        $receivedToken = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
    }
    if ($receivedToken === '') {
        $receivedToken = trim((string) ($_SERVER['HTTP_TOKEN'] ?? ''));
    }
    if ($receivedToken === '') {
        $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            $receivedToken = trim((string) ($matches[1] ?? ''));
        }
    }

    if ($receivedToken === '' || !hash_equals($expectedToken, $receivedToken)) {
        AppLogger::error('Standalone webhook unauthorized', [
            'has_payload_token' => payload_get($payload, ['token', 'webhook_token', 'secret']) !== '',
            'has_header_x_kirvano_token' => isset($_SERVER['HTTP_X_KIRVANO_TOKEN']),
            'has_header_x_webhook_token' => isset($_SERVER['HTTP_X_WEBHOOK_TOKEN']),
            'has_header_x_api_key' => isset($_SERVER['HTTP_X_API_KEY']),
        ]);

        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$event = mb_strtoupper(payload_get($payload, ['event', 'event_name', 'type', 'data.event', 'data.event_name']), 'UTF-8');
$status = mb_strtoupper(payload_get($payload, ['status', 'payment_status', 'transaction_status', 'data.status']), 'UTF-8');

$approvedEvents = ['SALE_APPROVED', 'PURCHASE_APPROVED', 'PAYMENT_APPROVED', 'ORDER_APPROVED'];
$approvedStatuses = ['APPROVED', 'PAID', 'SUCCESS', 'COMPLETED', 'CONFIRMED'];
$shouldProcess = in_array($event, $approvedEvents, true) || in_array($status, $approvedStatuses, true);

if (!$shouldProcess) {
    AppLogger::info('Standalone webhook ignored event', [
        'event' => $event,
        'status' => $status,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Ignored event',
        'event' => $event,
        'status' => $status,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$customerName = payload_get($payload, ['customer.name', 'buyer.name', 'client.name', 'name']);
$email = mb_strtolower(payload_get($payload, ['customer.email', 'buyer.email', 'client.email', 'email']), 'UTF-8');
$phone = preg_replace('/\D+/', '', payload_get($payload, ['customer.phone_number', 'customer.phone', 'buyer.phone', 'phone_number', 'phone']));
$document = preg_replace('/\D+/', '', payload_get($payload, ['customer.document', 'customer.cpf', 'buyer.document', 'document', 'cpf']));

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = '';
}

if ($email === '' && $document !== '') {
    $email = 'kirvano_' . $document . '@atendy.local';
}

if ($email === '') {
    $email = 'kirvano_' . substr(sha1($rawBody), 0, 12) . '@atendy.local';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Customer email is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($document) !== 11) {
    $document = str_pad(substr((string) crc32($email . microtime(true)), 0, 11), 11, '0');
}

$chargeFrequency = mb_strtoupper(payload_get(
    $payload,
    [
        'plan.charge_frequency',
        'plan.frequency',
        'charge_frequency',
        'frequency',
        'data.plan.charge_frequency',
        'plano.frequência_de_cobrança',
        'plano.frequencia_de_cobranca',
        'plano.frecuencia_de_cobranza',
        'plano.frequency',
    ],
    'MONTHLY'
), 'UTF-8');

$normalizedChargeFrequency = normalize_frequency_label($chargeFrequency);

$planType = match ($normalizedChargeFrequency) {
    'QUARTERLY', 'TRIMESTRAL', 'TRIMESTRALMENTE' => 'quarterly',
    'YEARLY', 'ANNUAL', 'ANNUALLY', 'ANUAL', 'ANUALMENTE' => 'annual',
    default => 'monthly',
};

AppLogger::info('Standalone webhook received approved payment', [
    'event' => $event,
    'status' => $status,
    'email' => $email,
    'document' => $document,
    'plan_type' => $planType,
]);

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

        $stmtUpdate = $db->prepare(
            'UPDATE users
             SET nome_consultorio = CASE WHEN (nome_consultorio IS NULL OR nome_consultorio = "" OR nome_consultorio = "Meu Consultorio") THEN :nome_consultorio ELSE nome_consultorio END,
                 telefone = CASE WHEN (telefone IS NULL OR telefone = "" OR telefone = "00000000000") THEN :telefone ELSE telefone END
             WHERE id = :id'
        );
        $stmtUpdate->execute([
            'id' => $userId,
            'nome_consultorio' => $customerName !== '' ? $customerName : 'Meu Consultorio',
            'telefone' => $phone !== '' ? $phone : '00000000000',
        ]);
    } else {
        $createdUser = true;
        $temporaryPassword = bin2hex(random_bytes(6));
        $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

        $fields = ['email', 'cpf', 'password_hash', 'nome_consultorio', 'telefone', 'endereco', 'ativo', 'created_at'];
        $values = [':email', ':cpf', ':password_hash', ':nome_consultorio', ':telefone', ':endereco', '1', 'NOW()'];

        if (db_column_exists($db, 'users', 'updated_at')) {
            $fields[] = 'updated_at';
            $values[] = 'NOW()';
        }

        $stmtInsert = $db->prepare(
            'INSERT INTO users (' . implode(', ', $fields) . ')
             VALUES (' . implode(', ', $values) . ')'
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
        try {
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
        } catch (Throwable $settingsError) {
            AppLogger::error('Standalone webhook settings creation skipped', [
                'user_id' => $userId,
                'error' => $settingsError->getMessage(),
            ]);
        }
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
    $reference = 'kirvano:' . payload_get($payload, ['sale_id', 'id', 'data.sale_id', 'data.id'], 'approved');
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
