<?php

declare(strict_types=1);

if (PHP_SESSION_ACTIVE !== session_status()) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

session_start();

// Headers de seguranca HTTP
// Aplica apenas em respostas HTML (não afeta webhooks/APIs que já enviam JSON)
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data: https:; font-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; connect-src 'self' https://graph.facebook.com https://cdn.jsdelivr.net; frame-src 'self'; upgrade-insecure-requests");
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    // Remove informação de tecnologia
    header_remove('X-Powered-By');
}

$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envPath)) {
    $envValues = parse_ini_file($envPath, false, INI_SCANNER_TYPED);
    if (is_array($envValues)) {
        foreach ($envValues as $key => $value) {
            $_ENV[$key] = (string) $value;
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/AppLogger.php';
require_once __DIR__ . '/core/BackupService.php';
require_once __DIR__ . '/core/BillingService.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/HealthReport.php';
require_once __DIR__ . '/core/MigrationRunner.php';
require_once __DIR__ . '/core/AutomationJobRunner.php';
require_once __DIR__ . '/core/ObservabilityService.php';
require_once __DIR__ . '/core/LegalService.php';
require_once __DIR__ . '/core/MercadoPagoClient.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/View.php';
require_once __DIR__ . '/core/MailerService.php';
require_once __DIR__ . '/core/WhatsAppCloudClient.php';
require_once __DIR__ . '/core/SlotFinderService.php';
require_once __DIR__ . '/core/WhatsAppService.php';

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/PatientController.php';
require_once __DIR__ . '/controllers/AppointmentController.php';
require_once __DIR__ . '/controllers/WhatsAppController.php';
require_once __DIR__ . '/controllers/BillingController.php';
require_once __DIR__ . '/controllers/KirvanoWebhookController.php';
require_once __DIR__ . '/controllers/FinanceiroController.php';
require_once __DIR__ . '/controllers/CalendarController.php';
require_once __DIR__ . '/controllers/HealthController.php';
require_once __DIR__ . '/controllers/NotificationController.php';
require_once __DIR__ . '/controllers/ReportsController.php';
require_once __DIR__ . '/controllers/TeamController.php';
require_once __DIR__ . '/controllers/SettingsController.php';
require_once __DIR__ . '/controllers/WebhookController.php';
require_once __DIR__ . '/controllers/LegalController.php';

AppLogger::registerHandlers();

