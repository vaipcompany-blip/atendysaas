<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$route = (string) ($_GET['route'] ?? '');
$requestUriPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($scriptDir === '.') {
    $scriptDir = '';
}

$normalizedPath = $requestUriPath;
if ($scriptDir !== '' && strpos($normalizedPath, $scriptDir . '/') === 0) {
    $normalizedPath = substr($normalizedPath, strlen($scriptDir));
}
if ($normalizedPath === '') {
    $normalizedPath = '/';
}

if ($route === '') {
    $pathRouteMap = [
        '/api/plans' => 'api_plans',
        '/api/checkout' => 'api_checkout',
        '/api/renew-subscription' => 'api_renew_subscription',
        '/api/me/subscription' => 'api_me_subscription',
        '/webhook/mercadopago' => 'mercadopago_webhook',
        '/webhook/kirvano' => 'kirvano_webhook',
        '/pricing' => 'pricing',
        '/success' => 'billing_success',
        '/failure' => 'billing_failure',
        '/pending' => 'billing_pending',
    ];
    $route = $pathRouteMap[$normalizedPath] ?? 'dashboard';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$authController = new AuthController();
$dashboardController = new DashboardController();
$patientController = new PatientController();
$appointmentController = new AppointmentController();
$whatsAppController = new WhatsAppController();
$billingController = new BillingController();
$kirvanoWebhookController = new KirvanoWebhookController();
$financeiroController = new FinanceiroController();
$calendarController = new CalendarController();
$healthController = new HealthController();
$notificationController = new NotificationController();
$reportsController = new ReportsController();
$teamController = new TeamController();
$settingsController = new SettingsController();
$webhookController = new WebhookController();
$legalController = new LegalController();

if ($route === 'login') {
    if ($method === 'POST') {
        $authController->login();
    }

    $authController->showLogin();
    exit;
}

if ($route === 'register') {
    if ($method === 'POST') {
        $authController->register();
    }

    $authController->showRegister();
    exit;
}

if ($route === 'forgot_password') {
    if ($method === 'POST') {
        $authController->requestPasswordReset();
    }

    $authController->showForgotPassword();
    exit;
}

if ($route === 'reset_password') {
    if ($method === 'POST') {
        $authController->resetPassword();
    }

    $authController->showResetPassword();
    exit;
}

if ($route === 'team_accept') {
    if ($method === 'POST') {
        $authController->acceptTeamInvite();
    }

    $authController->showTeamAccept();
    exit;
}

if ($route === 'calendar_feed') {
    $calendarController->feed();
    exit;
}

if ($route === 'health') {
    $healthController->show();
    exit;
}

if ($route === 'legal') {
    $legalController->showPublicDoc();
    exit;
}

if ($route === 'whatsapp_webhook') {
    if ($method === 'GET') {
        $webhookController->verify();
        exit;
    }

    if ($method === 'POST') {
        $webhookController->receive();
        exit;
    }

    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

if ($route === 'mercadopago_webhook') {
    if ($method === 'POST' || $method === 'GET') {
        $billingController->webhookMercadoPago();
        exit;
    }

    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

if ($route === 'kirvano_webhook') {
    if ($method === 'POST') {
        $kirvanoWebhookController->handle();
        exit;
    }

    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

if ($route === 'plans') {
    if ($method !== 'GET') {
        http_response_code(405);
        echo 'Method not allowed';
        exit;
    }

    $billingController->plansJson();
    exit;
}

if ($route === 'api_plans') {
    if ($method !== 'GET') {
        http_response_code(405);
        echo 'Method not allowed';
        exit;
    }

    $billingController->plansJson();
    exit;
}

if (!Auth::check()) {
    redirect(base_url('route=login'));
}

$routeAction = '';
if ($method === 'POST') {
    $routeAction = (string) ($_POST['action'] ?? '');
} elseif ($method === 'GET') {
    $routeAction = (string) ($_GET['action'] ?? '');
}

ensure_route_access($route, $method, $routeAction);
ensure_subscription_access($route, $method, $routeAction);

try {
switch ($route) {
    case 'logout':
        $authController->logout();
        break;

    case 'dashboard':
        $dashboardController->index();
        break;

    case 'patients':
        if ($method === 'GET' && ($_GET['action'] ?? '') === 'export_csv') {
            $patientController->exportCsv();
            break;
        }

        if ($method === 'GET' && ($_GET['action'] ?? '') === 'export_pdf') {
            $patientController->exportPdf();
            break;
        }

        if ($method === 'GET' && ($_GET['action'] ?? '') === 'edit') {
            $patientController->edit();
            break;
        }

        if ($method === 'POST') {
            $action = $_POST['action'] ?? 'create';
            if ($action === 'archive') {
                $patientController->archive();
            }
            if ($action === 'update') {
                $patientController->update();
            }
            $patientController->create();
        }
        $patientController->index();
        break;

    case 'appointments':
        if ($method === 'GET' && ($_GET['action'] ?? '') === 'export_csv') {
            $appointmentController->exportCsv();
            break;
        }

        if ($method === 'GET' && ($_GET['action'] ?? '') === 'export_pdf') {
            $appointmentController->exportPdf();
            break;
        }

        if ($method === 'GET' && ($_GET['action'] ?? '') === 'edit') {
            $appointmentController->edit();
            break;
        }

        if ($method === 'POST') {
            $action = $_POST['action'] ?? 'create';
            if ($action === 'status') {
                $appointmentController->updateStatus();
            }
            if ($action === 'quick_reschedule') {
                $appointmentController->quickReschedule();
            }
            if ($action === 'update') {
                $appointmentController->update();
            }
            $appointmentController->create();
        }
        $appointmentController->index();
        break;

    case 'whatsapp':
        if ($method === 'GET' && ($_GET['action'] ?? '') === 'export_csv') {
            $whatsAppController->exportCsv();
            break;
        }

        if ($method === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'send_manual') {
                $whatsAppController->sendManual();
            }
            if ($action === 'receive_simulated') {
                $whatsAppController->receiveSimulated();
            }
            if ($action === 'run_automations') {
                $whatsAppController->runAutomations();
            }
            if ($action === 'run_reminders') {
                $whatsAppController->runReminders();
            }
            if ($action === 'run_followups') {
                $whatsAppController->runFollowUps();
            }
        }
        $whatsAppController->index();
        break;

    case 'billing':
        if ($method === 'POST') {
            $billingController->save();
        }
        $billingController->index();
        break;

    case 'pricing':
        if ($method === 'POST') {
            $billingController->save();
        }
        $billingController->index();
        break;

    case 'checkout':
        if ($method !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed';
            break;
        }
        $billingController->checkout();
        break;

    case 'renew_subscription':
        if ($method !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed';
            break;
        }
        $billingController->renewSubscription();
        break;

    case 'api_checkout':
        if ($method !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $billingController->checkoutJson();
        break;

    case 'api_renew_subscription':
        if ($method !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $billingController->renewSubscriptionJson();
        break;

    case 'billing_result':
        if ($method !== 'GET') {
            http_response_code(405);
            echo 'Method not allowed';
            break;
        }
        $billingController->checkoutResult();
        break;

    case 'my_subscription':
        if ($method !== 'GET') {
            http_response_code(405);
            echo 'Method not allowed';
            break;
        }
        $billingController->meSubscriptionJson();
        break;

    case 'api_me_subscription':
        if ($method !== 'GET') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $billingController->meSubscriptionJson();
        break;

    case 'billing_success':
        $_GET['status'] = 'success';
        $billingController->checkoutResult();
        break;

    case 'billing_failure':
        $_GET['status'] = 'failure';
        $billingController->checkoutResult();
        break;

    case 'billing_pending':
        $_GET['status'] = 'pending';
        $billingController->checkoutResult();
        break;

    case 'calendar':
        if ($method === 'GET' && ($_GET['action'] ?? '') === 'export') {
            $calendarController->export();
            break;
        }
        if ($method === 'POST') {
            $calendarController->generateToken();
            break;
        }
        redirect(base_url('route=settings') . '#calendar-integration');
        break;

    case 'financeiro':
        if ($method === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'update_pagamento') {
                $financeiroController->updatePagamento();
            }
        }
        $financeiroController->index();
        break;

    case 'reports':
        if ($method === 'GET' && ($_GET['action'] ?? '') === 'export_csv') {
            $reportsController->exportCsv();
            break;
        }

        if ($method === 'GET' && ($_GET['action'] ?? '') === 'export_pdf') {
            $reportsController->exportPdf();
            break;
        }

        $reportsController->index();
        break;

    case 'notifications':
        if ($method === 'GET') {
            $notificationController->index();
            break;
        }
        if ($method === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'mark_read') {
                $notificationController->markRead();
            }
            if ($action === 'mark_all_read') {
                $notificationController->markAllRead();
            }
            if ($action === 'get_unread_count') {
                $notificationController->getUnreadCount();
            }
            if ($action === 'get_latest') {
                $notificationController->getLatest();
            }
        }
        $notificationController->index();
        break;

    case 'settings':
        if ($method === 'POST') {
            $action = $_POST['action'] ?? 'save';
            if ($action === 'preview_template') {
                $settingsController->previewTemplate();
                exit;
            }
            if ($action === 'change_password') {
                $settingsController->changePassword();
            }
            if ($action === 'logout_all_sessions') {
                $settingsController->logoutAllSessions();
            }
            if ($action === 'create_backup') {
                $settingsController->createBackup();
            }
            if ($action === 'test_connection') {
                $settingsController->testConnection();
            }
            if ($action === 'save_auto_reply') {
                $settingsController->saveAutoReply();
            }
            if ($action === 'delete_auto_reply') {
                $settingsController->deleteAutoReply();
            }
            if ($action === 'add_blocked_date') {
                $settingsController->addBlockedDate();
            }
            if ($action === 'delete_blocked_date') {
                $settingsController->deleteBlockedDate();
            }
            if ($action === 'accept_legal') {
                $settingsController->acceptLegal();
            }
            $settingsController->save();
        }
        $settingsController->index();
        break;

    case 'team':
        if ($method === 'GET') {
            $teamController->index();
            break;
        }
        if ($method === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'invite') {
                $teamController->invite();
            }
            if ($action === 'update_role') {
                $teamController->updateRole();
            }
            if ($action === 'remove') {
                $teamController->remove();
            }
        }
        $teamController->index();
        break;

    default:
        require_once dirname(__DIR__) . '/src/views/errors/404.php';
        break;
}
} catch (Throwable $e) {
    AppLogger::error('Unhandled route exception', [
        'route' => $route,
        'method' => $method,
        'action' => $routeAction,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    http_response_code(500);
    echo 'Erro interno do servidor.';
}
