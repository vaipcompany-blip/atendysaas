<?php

declare(strict_types=1);

final class BillingService
{
    public function getPlans(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, code, name, description, price_cents, currency, interval_months, max_team_members, max_monthly_messages, is_active
             FROM billing_plans
             WHERE is_active = 1
             ORDER BY price_cents ASC, id ASC'
        );

        return $stmt ? $stmt->fetchAll() : [];
    }

    public function getCurrentSubscription(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.id, s.user_id, s.plan_id, s.status, s.started_at, s.trial_ends_at, s.current_period_end, s.canceled_at,
                    s.payment_provider, s.payment_reference,
                    p.code AS plan_code, p.name AS plan_name, p.description AS plan_description,
                    p.price_cents, p.currency, p.interval_months, p.max_team_members, p.max_monthly_messages
             FROM subscriptions s
             INNER JOIN billing_plans p ON p.id = s.plan_id
             WHERE s.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function ensureWorkspaceSubscription(int $userId): array
    {
        $existing = $this->getCurrentSubscription($userId);
        if ($existing !== null) {
            $this->refreshSubscriptionState((int) $existing['id']);
            return $this->getCurrentSubscription($userId) ?? $existing;
        }

        $plan = $this->findDefaultPlan();
        if ($plan === null) {
            throw new RuntimeException('Nenhum plano de billing ativo foi encontrado.');
        }

        $trialDays = $this->trialDays();
        $status = $trialDays > 0 ? 'trialing' : 'active';
        $trialEndsAt = $trialDays > 0 ? date('Y-m-d H:i:s', strtotime('+' . $trialDays . ' days')) : null;
        $periodEnd = $status === 'active'
            ? date('Y-m-d H:i:s', strtotime('+' . max(1, (int) $plan['interval_months']) . ' month'))
            : $trialEndsAt;

        $stmt = Database::connection()->prepare(
            'INSERT INTO subscriptions (
                user_id, plan_id, status, started_at, trial_ends_at, current_period_end, created_at, updated_at
             ) VALUES (
                :user_id, :plan_id, :status, NOW(), :trial_ends_at, :current_period_end, NOW(), NOW()
             )'
        );
        $stmt->execute([
            'user_id' => $userId,
            'plan_id' => (int) $plan['id'],
            'status' => $status,
            'trial_ends_at' => $trialEndsAt,
            'current_period_end' => $periodEnd,
        ]);

        $subscriptionId = (int) Database::connection()->lastInsertId();
        $this->recordBillingEvent(
            $subscriptionId,
            $userId,
            $status === 'trialing' ? 'trial_started' : 'subscription_started',
            'success',
            0,
            (string) ($plan['currency'] ?? 'BRL'),
            null,
            ['plan_code' => (string) $plan['code']]
        );

        return $this->getCurrentSubscription($userId) ?? [];
    }

    public function isWorkspaceAccessAllowed(int $userId): bool
    {
        $subscription = $this->ensureWorkspaceSubscription($userId);
        $status = (string) ($subscription['status'] ?? 'past_due');

        if ($status === 'active') {
            return true;
        }

        if ($status !== 'trialing') {
            return false;
        }

        $trialEndsAt = (string) ($subscription['trial_ends_at'] ?? '');
        if ($trialEndsAt === '' || strtotime($trialEndsAt) === false) {
            return false;
        }

        return strtotime($trialEndsAt) >= time();
    }

    public function activatePlan(int $userId, string $planCode, string $paymentReference = 'manual'): array
    {
        $plan = $this->findPlanByCode($planCode);
        if ($plan === null) {
            throw new RuntimeException('Plano informado não existe ou está inativo.');
        }

        $subscription = $this->ensureWorkspaceSubscription($userId);
        $subscriptionId = (int) ($subscription['id'] ?? 0);
        if ($subscriptionId <= 0) {
            throw new RuntimeException('Não foi possível localizar a assinatura atual.');
        }

        $intervalMonths = max(1, (int) ($plan['interval_months'] ?? 1));
        $periodEnd = date('Y-m-d H:i:s', strtotime('+' . $intervalMonths . ' month'));

        $stmt = Database::connection()->prepare(
            'UPDATE subscriptions
             SET plan_id = :plan_id,
                 status = "active",
                 trial_ends_at = NULL,
                 current_period_end = :current_period_end,
                 canceled_at = NULL,
                 payment_provider = "manual",
                 payment_reference = :payment_reference,
                 updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $subscriptionId,
            'user_id' => $userId,
            'plan_id' => (int) $plan['id'],
            'current_period_end' => $periodEnd,
            'payment_reference' => $paymentReference,
        ]);

        $this->recordBillingEvent(
            $subscriptionId,
            $userId,
            'subscription_activated',
            'success',
            (int) ($plan['price_cents'] ?? 0),
            (string) ($plan['currency'] ?? 'BRL'),
            $paymentReference,
            ['plan_code' => (string) $plan['code']]
        );

        return $this->getCurrentSubscription($userId) ?? [];
    }

    public function cancelSubscription(int $userId): array
    {
        $subscription = $this->getCurrentSubscription($userId);
        if ($subscription === null) {
            throw new RuntimeException('Assinatura não encontrada.');
        }

        $subscriptionId = (int) ($subscription['id'] ?? 0);
        $stmt = Database::connection()->prepare(
            'UPDATE subscriptions
             SET status = "canceled", canceled_at = NOW(), updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $subscriptionId,
            'user_id' => $userId,
        ]);

        $this->recordBillingEvent(
            $subscriptionId,
            $userId,
            'subscription_canceled',
            'success',
            0,
            (string) ($subscription['currency'] ?? 'BRL'),
            null,
            ['plan_code' => (string) ($subscription['plan_code'] ?? '')]
        );

        return $this->getCurrentSubscription($userId) ?? $subscription;
    }

    public function statusLabel(string $status): string
    {
        $map = [
            'trialing' => 'Período de teste',
            'active' => 'Ativa',
            'past_due' => 'Pagamento pendente',
            'canceled' => 'Cancelada',
            'suspended' => 'Suspensa',
        ];

        return $map[$status] ?? ucfirst($status);
    }

    private function refreshSubscriptionState(int $subscriptionId): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, status, trial_ends_at, current_period_end
             FROM subscriptions
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $subscriptionId]);
        $subscription = $stmt->fetch();
        if (!$subscription) {
            return;
        }

        $status = (string) ($subscription['status'] ?? 'past_due');
        if ($status === 'trialing') {
            $trialEndsAt = (string) ($subscription['trial_ends_at'] ?? '');
            if ($trialEndsAt !== '' && strtotime($trialEndsAt) !== false && strtotime($trialEndsAt) < time()) {
                $update = Database::connection()->prepare(
                    'UPDATE subscriptions SET status = "past_due", updated_at = NOW() WHERE id = :id'
                );
                $update->execute(['id' => $subscriptionId]);
            }
            return;
        }

        if ($status === 'active') {
            $periodEnd = (string) ($subscription['current_period_end'] ?? '');
            if ($periodEnd !== '' && strtotime($periodEnd) !== false && strtotime($periodEnd) < time()) {
                $update = Database::connection()->prepare(
                    'UPDATE subscriptions SET status = "past_due", updated_at = NOW() WHERE id = :id'
                );
                $update->execute(['id' => $subscriptionId]);
            }
        }
    }

    private function findDefaultPlan(): ?array
    {
        $preferredCode = trim((string) env('BILLING_DEFAULT_PLAN', 'starter'));
        if ($preferredCode !== '') {
            $preferred = $this->findPlanByCode($preferredCode);
            if ($preferred !== null) {
                return $preferred;
            }
        }

        $stmt = Database::connection()->query(
            'SELECT id, code, name, description, price_cents, currency, interval_months, max_team_members, max_monthly_messages
             FROM billing_plans
             WHERE is_active = 1
             ORDER BY price_cents ASC, id ASC
             LIMIT 1'
        );

        $row = $stmt ? $stmt->fetch() : null;
        return $row ?: null;
    }

    private function findPlanByCode(string $code): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, code, name, description, price_cents, currency, interval_months, max_team_members, max_monthly_messages
             FROM billing_plans
             WHERE code = :code AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function trialDays(): int
    {
        $days = (int) env('BILLING_TRIAL_DAYS', '14');
        return max(0, $days);
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
}
