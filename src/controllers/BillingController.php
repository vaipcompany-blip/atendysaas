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
        $subscription = $this->billing->ensureWorkspaceSubscription($userId);
        $plans = $this->billing->getPlans();
        $message = $_GET['message'] ?? null;

        View::render('billing/index', [
            'subscription' => $subscription,
            'plans' => $plans,
            'message' => $message,
            'statusLabel' => $this->billing->statusLabel((string) ($subscription['status'] ?? 'past_due')),
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
            redirect(base_url('route=billing&message=' . urlencode($e->getMessage())));
        }
    }
}
