<?php

declare(strict_types=1);

final class BillingController
{
    private BillingService $billing;

    public function __construct()
    {
        $this->billing = new BillingService();
    }

    public function index(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        $subscription = $this->billing->getCurrentSubscription($userId);
        $plans = $this->billing->getPlans();
        $message = $_GET['message'] ?? null;
        $publicKey = trim((string) env('MERCADOPAGO_PUBLIC_KEY', ''));

        View::render('billing/index', [
            'subscription' => $subscription,
            'plans' => $plans,
            'message' => $message,
            'statusLabel' => $this->billing->statusLabel((string) ($subscription['status_normalized'] ?? 'pending')),
            'publicKey' => $publicKey,
        ]);
    }

    public function save(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=billing&message=' . urlencode('Token inválido.')));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $action = trim((string) ($_POST['action'] ?? ''));

        try {
            if ($action === 'checkout') {
                $this->checkout();
            }

            if ($action === 'renew_subscription') {
                $this->renewSubscription();
            }

            if ($action === 'activate_plan') {
                $planCode = trim((string) ($_POST['plan_code'] ?? ''));
                if ($planCode === '') {
                    throw new RuntimeException('Plano inválido.');
                }

                $this->billing->activatePlan($userId, $planCode, 'manual_' . date('YmdHis'));
                audit_log_event($userId, 'subscription_plan_activated', 'Plano ativado manualmente: ' . $planCode . '.');
                redirect(base_url('route=billing&message=' . urlencode('Plano ativado com sucesso.')));
            }

            if ($action === 'cancel_subscription') {
                $this->billing->cancelSubscription($userId);
                audit_log_event($userId, 'subscription_canceled', 'Assinatura cancelada no painel de billing.');
                redirect(base_url('route=billing&message=' . urlencode('Assinatura cancelada.')));
            }

            throw new RuntimeException('Ação de billing inválida.');
        } catch (Throwable $e) {
            AppLogger::error('Billing save failed', [
                'user_id' => $userId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            redirect(base_url('route=billing&message=' . urlencode($e->getMessage())));
        }
    }

    public function checkout(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=billing&message=' . urlencode('Token invalido.')));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $planType = trim((string) ($_POST['plan_type'] ?? ''));
        if ($planType === '') {
            redirect(base_url('route=billing&message=' . urlencode('Plano invalido.')));
        }

        try {
            $checkout = $this->billing->createCheckout($userId, $planType);
            $url = (string) ($checkout['preference_url'] ?? '');
            if ($url === '') {
                throw new RuntimeException('Falha ao iniciar checkout.');
            }

            redirect($url);
        } catch (Throwable $e) {
            AppLogger::error('Billing checkout failed', [
                'user_id' => $userId,
                'plan_type' => $planType,
                'error' => $e->getMessage(),
            ]);
            redirect(base_url('route=billing&message=' . urlencode('Nao foi possivel iniciar o pagamento.')));
        }
    }

    public function renewSubscription(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=billing&message=' . urlencode('Token invalido.')));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $planType = trim((string) ($_POST['plan_type'] ?? ''));
        if ($planType === '') {
            redirect(base_url('route=billing&message=' . urlencode('Plano invalido.')));
        }

        try {
            $checkout = $this->billing->renewSubscription($userId, $planType);
            $url = (string) ($checkout['preference_url'] ?? '');
            if ($url === '') {
                throw new RuntimeException('Falha ao iniciar renovacao.');
            }

            redirect($url);
        } catch (Throwable $e) {
            AppLogger::error('Billing renewal failed', [
                'user_id' => $userId,
                'plan_type' => $planType,
                'error' => $e->getMessage(),
            ]);
            redirect(base_url('route=billing&message=' . urlencode('Nao foi possivel iniciar a renovacao.')));
        }
    }

    public function webhookMercadoPago(): void
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode((string) $raw, true);

        if (!is_array($payload)) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['received' => true], JSON_UNESCAPED_UNICODE);
            return;
        }

        $type = mb_strtolower(trim((string) ($payload['type'] ?? '')), 'UTF-8');
        $paymentId = (string) ($payload['data']['id'] ?? '');

        if ($type === 'payment' && $paymentId !== '') {
            try {
                $this->billing->processPaymentWebhook($paymentId);
            } catch (Throwable $e) {
                AppLogger::error('Mercado Pago webhook process failed', [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['received' => true], JSON_UNESCAPED_UNICODE);
    }

    public function checkoutResult(): void
    {
        $status = mb_strtolower(trim((string) ($_GET['status'] ?? 'pending')), 'UTF-8');
        if (!in_array($status, ['success', 'failure', 'pending'], true)) {
            $status = 'pending';
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $subscription = $this->billing->getCurrentSubscription($userId);

        View::render('billing/result', [
            'status' => $status,
            'subscription' => $subscription,
        ]);
    }

    public function plansJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->billing->getPlans(), JSON_UNESCAPED_UNICODE);
    }

    public function checkoutJson(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['message' => 'Nao autenticado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $planType = $this->extractPlanTypeFromRequest();
        if ($planType === '') {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['message' => 'planType invalido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $checkout = $this->billing->createCheckout($userId, $planType);
            $url = (string) ($checkout['preference_url'] ?? '');
            if ($url === '') {
                throw new RuntimeException('Falha ao gerar link de pagamento.');
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'preferenceUrl' => $url,
                'preferenceId' => (string) ($checkout['preference_id'] ?? ''),
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            AppLogger::error('Billing API checkout failed', [
                'user_id' => $userId,
                'plan_type' => $planType,
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['message' => 'Nao foi possivel iniciar o checkout.'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function renewSubscriptionJson(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['message' => 'Nao autenticado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $planType = $this->extractPlanTypeFromRequest();
        if ($planType === '') {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['message' => 'planType invalido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $checkout = $this->billing->renewSubscription($userId, $planType);
            $url = (string) ($checkout['preference_url'] ?? '');
            if ($url === '') {
                throw new RuntimeException('Falha ao gerar link de renovacao.');
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'preferenceUrl' => $url,
                'preferenceId' => (string) ($checkout['preference_id'] ?? ''),
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            AppLogger::error('Billing API renewal failed', [
                'user_id' => $userId,
                'plan_type' => $planType,
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['message' => 'Nao foi possivel iniciar a renovacao.'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function meSubscriptionJson(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        $payload = $this->billing->getSubscriptionApiPayload($userId);
        if ($payload === null) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'message' => 'Assinatura nao encontrada.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    private function extractPlanTypeFromRequest(): string
    {
        $planType = trim((string) ($_POST['plan_type'] ?? $_POST['planType'] ?? ''));
        if ($planType !== '') {
            return $planType;
        }

        $raw = file_get_contents('php://input');
        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            return '';
        }

        return trim((string) ($json['planType'] ?? $json['plan_type'] ?? ''));
    }
}
