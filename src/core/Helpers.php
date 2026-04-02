<?php

declare(strict_types=1);

function env(string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== null && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }

    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return (string) $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env($key);
    if ($value === null) {
        return $default;
    }

    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return $default;
    }

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function app_env(): string
{
    $value = strtolower(trim((string) env('APP_ENV', 'local')));
    return $value !== '' ? $value : 'local';
}

function app_is_local(): bool
{
    return app_env() === 'local';
}

function app_is_production(): bool
{
    return app_env() === 'production';
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function base_url(string $path = ''): string
{
    $configuredAppUrl = trim((string) env('APP_URL', ''));
    if ($configuredAppUrl !== '') {
        $base = rtrim($configuredAppUrl, '/');
    } elseif (!empty($_SERVER['SCRIPT_NAME'])) {
        $base = (string) $_SERVER['SCRIPT_NAME'];
    } else {
        $base = '/index.php';
    }

    if ($path === '') {
        return $base;
    }

    return $base . '?' . ltrim($path, '?');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_field(): string
{
    $token = $_SESSION['csrf_token'] ?? '';
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

function verify_csrf(?string $token): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && hash_equals($sessionToken, $token);
}

function auth_user_type(): string
{
    $user = Auth::user();
    return is_array($user) ? (string) ($user['type'] ?? 'owner') : 'guest';
}

function auth_user_role(): string
{
    $user = Auth::user();
    if (!is_array($user)) {
        return 'guest';
    }

    if ((string) ($user['type'] ?? 'owner') === 'team_member') {
        return (string) ($user['team_member_role'] ?? 'staff');
    }

    return 'owner';
}

function auth_is_owner(): bool
{
    return auth_user_role() === 'owner';
}

function auth_is_admin(): bool
{
    $role = auth_user_role();
    return $role === 'owner' || $role === 'admin';
}

function auth_allowed_roles_for_route(string $route, string $method = 'GET', string $action = ''): array
{
    $method = strtoupper($method);

    $matrix = [
        'dashboard' => ['owner', 'admin', 'staff'],
        'patients' => ['owner', 'admin', 'staff'],
        'appointments' => ['owner', 'admin', 'staff'],
        'whatsapp' => ['owner', 'admin', 'staff'],
        'billing' => ['owner'],
        'pricing' => ['owner'],
        'billing_result' => ['owner'],
        'billing_success' => ['owner'],
        'billing_failure' => ['owner'],
        'billing_pending' => ['owner'],
        'checkout' => ['owner'],
        'renew_subscription' => ['owner'],
        'my_subscription' => ['owner'],
        'api_checkout' => ['owner'],
        'api_renew_subscription' => ['owner'],
        'api_me_subscription' => ['owner'],
        'notifications' => ['owner', 'admin', 'staff'],
        'financeiro' => ['owner', 'admin'],
        'reports' => ['owner', 'admin'],
        'team' => ['owner'],
        'settings' => ['owner'],
        'calendar' => ['owner'],
        'logout' => ['owner', 'admin', 'staff'],
    ];

    if ($route === 'calendar_feed') {
        return ['owner', 'admin', 'staff'];
    }

    return $matrix[$route] ?? ['owner'];
}

function auth_allowed_roles_for_action(string $route, string $method = 'GET', string $action = ''): ?array
{
    $method = strtoupper($method);
    $action = trim($action);

    $matrix = [
        'patients' => [
            'POST' => [
                'create' => ['owner', 'admin', 'staff'],
                'update' => ['owner', 'admin', 'staff'],
                'archive' => ['owner', 'admin'],
            ],
        ],
        'appointments' => [
            'POST' => [
                'create' => ['owner', 'admin', 'staff'],
                'update' => ['owner', 'admin', 'staff'],
                'status' => ['owner', 'admin', 'staff'],
                'quick_reschedule' => ['owner', 'admin', 'staff'],
            ],
        ],
        'whatsapp' => [
            'POST' => [
                'send_manual' => ['owner', 'admin', 'staff'],
                'receive_simulated' => ['owner', 'admin', 'staff'],
                'run_automations' => ['owner', 'admin'],
                'run_reminders' => ['owner', 'admin'],
                'run_followups' => ['owner', 'admin'],
            ],
        ],
        'financeiro' => [
            'POST' => [
                'update_pagamento' => ['owner', 'admin'],
            ],
        ],
        'notifications' => [
            'POST' => [
                'mark_read' => ['owner', 'admin', 'staff'],
                'mark_all_read' => ['owner', 'admin', 'staff'],
                'get_unread_count' => ['owner', 'admin', 'staff'],
                'get_latest' => ['owner', 'admin', 'staff'],
            ],
        ],
        'settings' => [
            'POST' => [
                'save' => ['owner'],
                'preview_template' => ['owner'],
                'change_password' => ['owner'],
                'logout_all_sessions' => ['owner'],
                'test_connection' => ['owner'],
                'save_auto_reply' => ['owner'],
                'delete_auto_reply' => ['owner'],
                'add_blocked_date' => ['owner'],
                'delete_blocked_date' => ['owner'],
                'accept_legal' => ['owner'],
            ],
        ],
        'billing' => [
            'POST' => [
                'activate_plan' => ['owner'],
                'cancel_subscription' => ['owner'],
                'checkout' => ['owner'],
                'renew_subscription' => ['owner'],
            ],
        ],
        'team' => [
            'POST' => [
                'invite' => ['owner'],
                'update_role' => ['owner'],
                'remove' => ['owner'],
            ],
        ],
        'calendar' => [
            'POST' => [
                'generate' => ['owner'],
            ],
        ],
    ];

    if ($action === '' || !isset($matrix[$route][$method][$action])) {
        return null;
    }

    return $matrix[$route][$method][$action];
}

function auth_can_access_route(string $route, string $method = 'GET', string $action = ''): bool
{
    $role = auth_user_role();
    if ($role === 'guest') {
        return false;
    }

    $actionRoles = auth_allowed_roles_for_action($route, $method, $action);
    if (is_array($actionRoles)) {
        return in_array($role, $actionRoles, true);
    }

    return in_array($role, auth_allowed_roles_for_route($route, $method, $action), true);
}

function auth_request_expects_json(string $route, string $method = 'GET', string $action = ''): bool
{
    $method = strtoupper($method);
    if (in_array($route, ['api_checkout', 'api_renew_subscription', 'api_me_subscription', 'api_plans'], true)) {
        return true;
    }

    if ($method !== 'POST') {
        return false;
    }

    if ($route === 'notifications') {
        return true;
    }

    if ($route === 'team' && in_array($action, ['update_role', 'remove'], true)) {
        return true;
    }

    if ($route === 'financeiro' && $action === 'update_pagamento') {
        return true;
    }

    if ($route === 'settings' && $action === 'preview_template') {
        return true;
    }

    return false;
}

function forbid_access(string $message = 'Você não tem permissão para acessar esta área.', bool $expectsJson = false): void
{
    http_response_code(403);

    if ($expectsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $forbiddenMessage = $message;
    require __DIR__ . '/../views/errors/403.php';
    exit;
}

function ensure_route_access(string $route, string $method = 'GET', string $action = ''): void
{
    if (auth_can_access_route($route, $method, $action)) {
        return;
    }

    forbid_access(
        'Você não tem permissão para acessar esta área.',
        auth_request_expects_json($route, $method, $action)
    );
}

function billing_route_is_exempt(string $route): bool
{
    return in_array($route, ['billing', 'pricing', 'checkout', 'renew_subscription', 'billing_result', 'billing_success', 'billing_failure', 'billing_pending', 'my_subscription', 'api_checkout', 'api_renew_subscription', 'api_me_subscription', 'api_plans', 'logout', 'health', 'calendar_feed', 'whatsapp_webhook', 'mercadopago_webhook', 'kirvano_webhook', 'plans'], true);
}

function ensure_subscription_access(string $route, string $method = 'GET', string $action = ''): void
{
    // Emergency safety valve: keep app usable even if billing tables are inconsistent.
    if (!env_bool('BILLING_ENFORCE_ACCESS', true)) {
        return;
    }

    if (billing_route_is_exempt($route)) {
        return;
    }

    $sessionUser = Auth::user();
    if (!is_array($sessionUser)) {
        return;
    }

    $workspaceId = (int) ($sessionUser['id'] ?? 0);
    if ($workspaceId <= 0) {
        return;
    }

    try {
        $billing = new BillingService();
        $allowed = $billing->isWorkspaceAccessAllowed($workspaceId);
    } catch (Throwable $e) {
        AppLogger::error('Billing access check failed', [
            'workspace_id' => $workspaceId,
            'route' => $route,
            'method' => $method,
            'action' => $action,
            'error' => $e->getMessage(),
        ]);
        return;
    }

    if ($allowed) {
        return;
    }

    $decision = $billing->getAccessDecision($workspaceId);
    $message = (string) ($decision['message'] ?? 'Assinatura necessaria. Escolha um plano.');
    if (auth_request_expects_json($route, $method, $action)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    redirect(base_url('route=pricing&message=' . urlencode($message)));
}

function audit_log_event(?int $userId, string $eventType, string $details): void
{
    $workspaceUserId = $userId ?? (int) ((Auth::user()['id'] ?? 0));
    if ($workspaceUserId <= 0) {
        return;
    }

    $ipAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    if ($ipAddress === '') {
        $ipAddress = '0.0.0.0';
    }

    $actor = Auth::user() ?? [];
    $actorRole = auth_user_role();
    $actorName = $actorRole === 'owner'
        ? (string) ($actor['email'] ?? 'owner')
        : (string) ($actor['team_member_name'] ?? $actor['email'] ?? 'team_member');
    $fullDetails = '[' . $actorRole . '] ' . $actorName . ' - ' . $details;

    try {
        $stmt = Database::connection()->prepare(
            'INSERT INTO security_events (user_id, event_type, ip_address, details, created_at)
             VALUES (:user_id, :event_type, :ip_address, :details, NOW())'
        );
        $stmt->execute([
            'user_id' => $workspaceUserId,
            'event_type' => $eventType,
            'ip_address' => $ipAddress,
            'details' => $fullDetails,
        ]);
    } catch (Throwable $e) {
    }
}


