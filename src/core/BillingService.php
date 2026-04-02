<?php

declare(strict_types=1);

final class BillingService
{
    /** @var array<string, array{name:string,price:float,duration_days:int,duration_label:string,save:?string}> */
    private const PLAN_CATALOG = [
        'monthly' => [
            'name' => 'Mensal',
            'price' => 29.99,
            'duration_days' => 30,
            'duration_label' => '1 mes',
            'save' => null,
        ],
        'quarterly' => [
            'name' => 'Trimestral',
            'price' => 79.99,
            'duration_days' => 90,
            'duration_label' => '3 meses',
            'save' => '11%',
        ],
        'annual' => [
            'name' => 'Anual',
            'price' => 339.99,
            'duration_days' => 365,
            'duration_label' => '12 meses',
            'save' => '6%',
        ],
    ];

    public function getPlans(): array
    {
        $result = [];
        foreach (self::PLAN_CATALOG as $id => $plan) {
            $result[] = [
                'id' => $id,
                'code' => $id,
                'name' => (string) $plan['name'],
                'price' => (float) $plan['price'],
                'price_cents' => (int) round(((float) $plan['price']) * 100),
                'currency' => 'BRL',
                'duration' => (string) $plan['duration_label'],
                'duration_days' => (int) $plan['duration_days'],
                'durationDays' => (int) $plan['duration_days'],
                'save' => $plan['save'],
                'description' => 'Acesso total ao SaaS, todas as features, suporte por email e atualizacoes.',
            ];
        }

        return $result;
    }

    public function getCurrentSubscription(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.id, s.external_uuid, s.user_id, s.plan_id, s.plan_type, s.status, s.payment_status,
                    s.amount, s.duration_days, s.start_date, s.end_date,
                    s.started_at, s.trial_ends_at, s.current_period_end, s.canceled_at,
                    s.payment_provider, s.payment_reference,
                    s.mercadopago_preference_id, s.mercadopago_payment_id,
                    s.created_at, s.updated_at,
                    p.code AS plan_code, p.name AS plan_name, p.description AS plan_description,
                    p.price_cents, p.currency, p.interval_months, p.max_team_members, p.max_monthly_messages
             FROM subscriptions s
             LEFT JOIN billing_plans p ON p.id = s.plan_id
             WHERE s.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $planType = (string) ($row['plan_type'] ?? '');
        if ($planType === '') {
            $planType = (string) ($row['plan_code'] ?? 'monthly');
        }
        if (!isset(self::PLAN_CATALOG[$planType])) {
            $planType = 'monthly';
        }

        $plan = self::PLAN_CATALOG[$planType];
        $amount = (float) ($row['amount'] ?? 0);
        if ($amount <= 0) {
            $amount = (float) $plan['price'];
        }

        $durationDays = (int) ($row['duration_days'] ?? 0);
        if ($durationDays <= 0) {
            $durationDays = (int) $plan['duration_days'];
        }

        $endDate = (string) ($row['end_date'] ?? $row['current_period_end'] ?? '');
        $daysRemaining = $this->daysRemaining($endDate);
        $normalizedStatus = $this->normalizeSubscriptionStatus($row, $daysRemaining);

        $row['plan_type'] = $planType;
        $row['amount'] = $amount;
        $row['duration_days'] = $durationDays;
        $row['duration'] = (string) $plan['duration_label'];
        $row['save'] = $plan['save'];
        $row['status_normalized'] = $normalizedStatus;
        $row['days_remaining'] = $daysRemaining;

        return $row;
    }

    public function ensureWorkspaceSubscription(int $userId): array
    {
        $existing = $this->getCurrentSubscription($userId);
        if ($existing !== null) {
            return $existing;
        }

        return [];
    }

    public function getAccessDecision(int $userId): array
    {
        $subscription = $this->getCurrentSubscription($userId);
        if ($subscription === null) {
            return [
                'allowed' => false,
                'reason' => 'no_subscription',
                'message' => 'Assinatura necessaria. Escolha um plano.',
                'subscription' => null,
            ];
        }

        $paymentStatus = mb_strtolower(trim((string) ($subscription['payment_status'] ?? 'pending')), 'UTF-8');
        if ($paymentStatus !== 'approved') {
            return [
                'allowed' => false,
                'reason' => 'pending_payment',
                'message' => 'Pagamento pendente.',
                'subscription' => $subscription,
            ];
        }

        $endDate = (string) ($subscription['end_date'] ?? $subscription['current_period_end'] ?? '');
        if ($endDate === '' || strtotime($endDate) === false || strtotime($endDate) < time()) {
            return [
                'allowed' => false,
                'reason' => 'expired',
                'message' => 'Assinatura expirada. Renove agora.',
                'subscription' => $subscription,
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'active',
            'message' => '',
            'subscription' => $subscription,
        ];
    }

    public function isWorkspaceAccessAllowed(int $userId): bool
    {
        $decision = $this->getAccessDecision($userId);
        return (bool) ($decision['allowed'] ?? false);
    }

    public function createCheckout(int $userId, string $planType): array
    {
        $plan = $this->planByType($planType);
        if ($plan === null) {
            throw new RuntimeException('Plano informado não existe ou está inativo.');
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $subscription = $this->upsertPendingSubscription($db, $userId, $planType, $plan);

            $preferencePayload = $this->buildPreferencePayload(
                $userId,
                $subscription,
                $planType,
                $plan
            );

            $client = new MercadoPagoClient();
            $preference = $client->createPreference($preferencePayload);

            $preferenceId = (string) ($preference['id'] ?? '');
            $preferenceUrl = (string) ($preference['init_point'] ?? $preference['sandbox_init_point'] ?? '');
            if ($preferenceId === '' || $preferenceUrl === '') {
                throw new RuntimeException('Nao foi possivel gerar o checkout no Mercado Pago.');
            }

            $stmt = $db->prepare(
                'UPDATE subscriptions
                 SET mercadopago_preference_id = :preference_id,
                     payment_provider = "mercadopago",
                     payment_reference = :payment_reference,
                     updated_at = NOW()
                 WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([
                'preference_id' => $preferenceId,
                'payment_reference' => $preferenceId,
                'id' => (int) $subscription['id'],
                'user_id' => $userId,
            ]);

            $this->recordBillingEvent(
                (int) $subscription['id'],
                $userId,
                'checkout_created',
                'pending',
                (int) round(((float) $plan['price']) * 100),
                'BRL',
                $preferenceId,
                [
                    'plan_type' => $planType,
                    'duration_days' => (int) $plan['duration_days'],
                ]
            );

            $db->commit();

            return [
                'subscription_id' => (int) $subscription['id'],
                'preference_id' => $preferenceId,
                'preference_url' => $preferenceUrl,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function renewSubscription(int $userId, string $planType): array
    {
        return $this->createCheckout($userId, $planType);
    }

    public function processPaymentWebhook(string $paymentId): array
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            throw new RuntimeException('Payment ID ausente no webhook.');
        }

        $client = new MercadoPagoClient();
        $payment = $client->getPayment($paymentId);

        $status = mb_strtolower((string) ($payment['status'] ?? ''), 'UTF-8');
        $preferenceId = (string) ($payment['order']['id'] ?? '');
        $externalReference = (string) ($payment['external_reference'] ?? '');

        $subscription = $this->findSubscriptionByProviderReference($preferenceId, $externalReference);
        if ($subscription === null) {
            AppLogger::error('Mercado Pago webhook without subscription match', [
                'payment_id' => $paymentId,
                'preference_id' => $preferenceId,
                'external_reference' => $externalReference,
            ]);
            return [
                'updated' => false,
                'status' => $status,
                'user_id' => 0,
            ];
        }

        $subscriptionId = (int) ($subscription['id'] ?? 0);
        $userId = (int) ($subscription['user_id'] ?? 0);

        if ($status === 'approved') {
            $durationDays = max(1, (int) ($subscription['duration_days'] ?? 30));
            $startDate = date('Y-m-d H:i:s');
            $endDate = date('Y-m-d H:i:s', strtotime('+' . $durationDays . ' days'));

            $update = Database::connection()->prepare(
                'UPDATE subscriptions
                 SET payment_status = "approved",
                     status = "active",
                     mercadopago_payment_id = :payment_id,
                     start_date = :start_date,
                     end_date = :end_date,
                     started_at = :start_date,
                     current_period_end = :end_date,
                     payment_provider = "mercadopago",
                     payment_reference = :payment_reference,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'id' => $subscriptionId,
                'payment_id' => $paymentId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payment_reference' => $preferenceId !== '' ? $preferenceId : $paymentId,
            ]);

            $this->recordBillingEvent(
                $subscriptionId,
                $userId,
                'payment_approved',
                'success',
                (int) round(((float) ($subscription['amount'] ?? 0)) * 100),
                'BRL',
                $paymentId,
                [
                    'provider_status' => $status,
                    'preference_id' => $preferenceId,
                ]
            );

            AppLogger::info('Pagamento aprovado no Mercado Pago', [
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
                'payment_id' => $paymentId,
            ]);

            return [
                'updated' => true,
                'status' => $status,
                'user_id' => $userId,
            ];
        }

        if (in_array($status, ['rejected', 'cancelled'], true)) {
            $update = Database::connection()->prepare(
                'UPDATE subscriptions
                 SET payment_status = "failed",
                     status = "past_due",
                     mercadopago_payment_id = :payment_id,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'id' => $subscriptionId,
                'payment_id' => $paymentId,
            ]);

            $this->recordBillingEvent(
                $subscriptionId,
                $userId,
                'payment_failed',
                'failed',
                (int) round(((float) ($subscription['amount'] ?? 0)) * 100),
                'BRL',
                $paymentId,
                [
                    'provider_status' => $status,
                    'preference_id' => $preferenceId,
                ]
            );

            AppLogger::info('Pagamento rejeitado no Mercado Pago', [
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
                'payment_id' => $paymentId,
                'status' => $status,
            ]);
        }

        return [
            'updated' => false,
            'status' => $status,
            'user_id' => $userId,
        ];
    }

    public function activatePlan(int $userId, string $planCode, string $paymentReference = 'manual'): array
    {
        $plan = $this->planByType($planCode);
        if ($plan === null) {
            throw new RuntimeException('Plano informado nao existe.');
        }

        $subscription = $this->getCurrentSubscription($userId);
        if ($subscription === null) {
            $subscription = $this->upsertPendingSubscription(Database::connection(), $userId, $planCode, $plan);
        }

        $startDate = date('Y-m-d H:i:s');
        $endDate = date('Y-m-d H:i:s', strtotime('+' . (int) $plan['duration_days'] . ' days'));

        $stmt = Database::connection()->prepare(
            'UPDATE subscriptions
             SET plan_type = :plan_type,
                 payment_status = "approved",
                 status = "active",
                 amount = :amount,
                 duration_days = :duration_days,
                 start_date = :start_date,
                 end_date = :end_date,
                 started_at = :start_date,
                 current_period_end = :end_date,
                 payment_provider = "manual",
                 payment_reference = :payment_reference,
                 updated_at = NOW()
             WHERE user_id = :user_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'plan_type' => $planCode,
            'amount' => (float) $plan['price'],
            'duration_days' => (int) $plan['duration_days'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'payment_reference' => $paymentReference,
        ]);

        return $this->getCurrentSubscription($userId) ?? [];
    }

    public function cancelSubscription(int $userId): array
    {
        $subscription = $this->getCurrentSubscription($userId);
        if ($subscription === null) {
            throw new RuntimeException('Assinatura nao encontrada.');
        }

        $stmt = Database::connection()->prepare(
            'UPDATE subscriptions
             SET status = "canceled",
                 payment_status = "cancelled",
                 canceled_at = NOW(),
                 updated_at = NOW()
             WHERE user_id = :user_id'
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        return $this->getCurrentSubscription($userId) ?? [];
    }

    public function statusLabel(string $status): string
    {
        $map = [
            'active' => 'Ativa',
            'approved' => 'Ativa',
            'pending' => 'Pagamento pendente',
            'past_due' => 'Pagamento pendente',
            'failed' => 'Falhou',
            'expired' => 'Expirada',
            'cancelled' => 'Cancelada',
            'canceled' => 'Cancelada',
            'suspended' => 'Suspensa',
        ];

        return $map[$status] ?? ucfirst($status);
    }

    public function getSubscriptionApiPayload(int $userId): ?array
    {
        $subscription = $this->getCurrentSubscription($userId);
        if ($subscription === null) {
            return null;
        }

        $normalizedStatus = (string) ($subscription['status_normalized'] ?? 'pending');

        return [
            'planType' => (string) ($subscription['plan_type'] ?? 'monthly'),
            'amount' => (float) ($subscription['amount'] ?? 0.0),
            'endDate' => (string) ($subscription['end_date'] ?? ''),
            'daysRemaining' => (int) ($subscription['days_remaining'] ?? 0),
            'status' => $normalizedStatus,
        ];
    }

    private function findPlanByCode(string $code): ?array
    {
        return $this->planByType($code);
    }

    private function planByType(string $planType): ?array
    {
        $normalized = mb_strtolower(trim($planType), 'UTF-8');
        if (!isset(self::PLAN_CATALOG[$normalized])) {
            return null;
        }

        return self::PLAN_CATALOG[$normalized];
    }

    private function recordBillingEvent(
        int $subscriptionId,
        int $userId,
        string $eventType,
        string $status,
        int $amountCents,
        string $currency,
        ?string $providerReference,
        array $payload
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO billing_events (
                subscription_id, user_id, event_type, status, amount_cents, currency, provider_reference, payload_json, created_at
             ) VALUES (
                :subscription_id, :user_id, :event_type, :status, :amount_cents, :currency, :provider_reference, :payload_json, NOW()
             )'
        );
        $stmt->execute([
            'subscription_id' => $subscriptionId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'status' => $status,
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency) ?: 'BRL',
            'provider_reference' => $providerReference,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function upsertPendingSubscription(PDO $db, int $userId, string $planType, array $plan): array
    {
        $subscription = $this->getCurrentSubscription($userId);
        $planId = $this->resolvePlanIdByType($planType);

        if ($subscription === null) {
            $stmt = $db->prepare(
                'INSERT INTO subscriptions (
                    external_uuid, user_id, plan_id, plan_type, status, payment_status,
                    amount, duration_days, started_at, current_period_end,
                    start_date, end_date,
                    payment_provider, payment_reference,
                    created_at, updated_at
                 ) VALUES (
                    :external_uuid, :user_id, :plan_id, :plan_type, "past_due", "pending",
                    :amount, :duration_days, NOW(), NULL,
                    NULL, NULL,
                    "mercadopago", NULL,
                    NOW(), NOW()
                 )'
            );
            $stmt->execute([
                'external_uuid' => $this->generateUuidV4(),
                'user_id' => $userId,
                'plan_id' => $planId,
                'plan_type' => $planType,
                'amount' => (float) $plan['price'],
                'duration_days' => (int) $plan['duration_days'],
            ]);
        } else {
            $stmt = $db->prepare(
                'UPDATE subscriptions
                 SET plan_id = :plan_id,
                     plan_type = :plan_type,
                     status = "past_due",
                     payment_status = "pending",
                     amount = :amount,
                     duration_days = :duration_days,
                     mercadopago_payment_id = NULL,
                     start_date = NULL,
                     end_date = NULL,
                     started_at = NOW(),
                     current_period_end = NULL,
                     payment_provider = "mercadopago",
                     payment_reference = NULL,
                     updated_at = NOW()
                 WHERE user_id = :user_id'
            );
            $stmt->execute([
                'user_id' => $userId,
                'plan_id' => $planId,
                'plan_type' => $planType,
                'amount' => (float) $plan['price'],
                'duration_days' => (int) $plan['duration_days'],
            ]);
        }

        $fresh = $this->getCurrentSubscription($userId);
        if ($fresh === null) {
            throw new RuntimeException('Falha ao preparar assinatura para checkout.');
        }

        return $fresh;
    }

    private function resolvePlanIdByType(string $planType): int
    {
        $stmt = Database::connection()->prepare('SELECT id FROM billing_plans WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $planType]);
        $existing = $stmt->fetch();
        if (is_array($existing) && isset($existing['id'])) {
            return (int) $existing['id'];
        }

        $plan = $this->planByType($planType);
        if ($plan === null) {
            throw new RuntimeException('Plano nao encontrado.');
        }

        $insert = Database::connection()->prepare(
            'INSERT INTO billing_plans (
                code, name, description, price_cents, currency, interval_months,
                max_team_members, max_monthly_messages, is_active, created_at, updated_at
             ) VALUES (
                :code, :name, :description, :price_cents, "BRL", :interval_months,
                9999, 999999, 1, NOW(), NOW()
             )'
        );
        $insert->execute([
            'code' => $planType,
            'name' => (string) $plan['name'],
            'description' => 'Acesso total ao SaaS.',
            'price_cents' => (int) round(((float) $plan['price']) * 100),
            'interval_months' => (int) ceil(((int) $plan['duration_days']) / 30),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function buildPreferencePayload(int $userId, array $subscription, string $planType, array $plan): array
    {
        $workspace = $this->loadWorkspaceUser($userId);
        $payerEmail = (string) ($workspace['email'] ?? '');
        $externalUuid = (string) ($subscription['external_uuid'] ?? '');

        $frontendUrl = rtrim((string) env('FRONTEND_URL', ''), '/');
        $apiUrl = rtrim((string) env('API_URL', ''), '/');

        $successUrl = $frontendUrl !== ''
            ? $frontendUrl . '/success'
            : $this->routeUrl('route=billing_result&status=success');
        $failureUrl = $frontendUrl !== ''
            ? $frontendUrl . '/failure'
            : $this->routeUrl('route=billing_result&status=failure');
        $pendingUrl = $frontendUrl !== ''
            ? $frontendUrl . '/pending'
            : $this->routeUrl('route=billing_result&status=pending');
        $notificationUrl = $apiUrl !== ''
            ? $apiUrl . '/webhook/mercadopago'
            : $this->routeUrl('route=mercadopago_webhook');

        return [
            'items' => [
                [
                    'id' => $planType,
                    'title' => 'Atendy - Plano ' . (string) $plan['name'],
                    'description' => 'Assinatura com acesso total ao SaaS.',
                    'quantity' => 1,
                    'currency_id' => 'BRL',
                    'unit_price' => (float) $plan['price'],
                ],
            ],
            'payer' => [
                'email' => $payerEmail,
            ],
            'payment_methods' => [
                'installments' => 12,
                'excluded_payment_types' => [
                    ['id' => 'ticket'],
                    ['id' => 'atm'],
                ],
                'excluded_payment_methods' => [],
            ],
            'external_reference' => $externalUuid,
            'metadata' => [
                'user_id' => $userId,
                'plan_type' => $planType,
                'duration_days' => (int) $plan['duration_days'],
                'subscription_id' => (int) ($subscription['id'] ?? 0),
            ],
            'back_urls' => [
                'success' => $successUrl,
                'failure' => $failureUrl,
                'pending' => $pendingUrl,
            ],
            'notification_url' => $notificationUrl,
        ];
    }

    private function routeUrl(string $queryString): string
    {
        $appUrl = trim((string) env('APP_URL', ''));
        if ($appUrl === '') {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $appUrl = $scheme . '://' . $host . $script;
        }

        $separator = str_contains($appUrl, '?') ? '&' : '?';
        return rtrim($appUrl, '?&') . $separator . ltrim($queryString, '?&');
    }

    private function loadWorkspaceUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT id, email, nome_consultorio FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : [];
    }

    private function daysRemaining(string $endDate): int
    {
        if ($endDate === '' || strtotime($endDate) === false) {
            return 0;
        }

        $seconds = strtotime($endDate) - time();
        if ($seconds <= 0) {
            return 0;
        }

        return (int) ceil($seconds / 86400);
    }

    private function normalizeSubscriptionStatus(array $subscription, int $daysRemaining): string
    {
        $paymentStatus = mb_strtolower(trim((string) ($subscription['payment_status'] ?? 'pending')), 'UTF-8');
        if ($paymentStatus === 'approved') {
            return $daysRemaining > 0 ? 'active' : 'expired';
        }

        if (in_array($paymentStatus, ['failed', 'cancelled'], true)) {
            return 'expired';
        }

        return 'pending';
    }

    private function findSubscriptionByProviderReference(string $preferenceId, string $externalReference): ?array
    {
        if ($preferenceId !== '') {
            $byPreference = Database::connection()->prepare(
                'SELECT id, user_id, amount, duration_days
                 FROM subscriptions
                 WHERE mercadopago_preference_id = :preference_id
                 LIMIT 1'
            );
            $byPreference->execute(['preference_id' => $preferenceId]);
            $row = $byPreference->fetch();
            if ($row) {
                return $row;
            }
        }

        if ($externalReference !== '') {
            $byExternal = Database::connection()->prepare(
                'SELECT id, user_id, amount, duration_days
                 FROM subscriptions
                 WHERE external_uuid = :external_uuid
                 LIMIT 1'
            );
            $byExternal->execute(['external_uuid' => $externalReference]);
            $row = $byExternal->fetch();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
