ALTER TABLE subscriptions
    ADD COLUMN external_uuid CHAR(36) NULL AFTER id,
    ADD COLUMN plan_type VARCHAR(20) NULL AFTER plan_id,
    ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER plan_type,
    ADD COLUMN amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER payment_status,
    ADD COLUMN duration_days INT UNSIGNED NOT NULL DEFAULT 0 AFTER amount,
    ADD COLUMN start_date DATETIME NULL AFTER duration_days,
    ADD COLUMN end_date DATETIME NULL AFTER start_date,
    ADD COLUMN mercadopago_preference_id VARCHAR(120) NULL AFTER end_date,
    ADD COLUMN mercadopago_payment_id VARCHAR(120) NULL AFTER mercadopago_preference_id,
    ADD COLUMN payment_metadata_json LONGTEXT NULL AFTER mercadopago_payment_id,
    ADD UNIQUE KEY uniq_subscriptions_external_uuid (external_uuid),
    ADD KEY idx_subscriptions_payment_status (payment_status),
    ADD KEY idx_subscriptions_end_date (end_date),
    ADD KEY idx_subscriptions_mercadopago_pref (mercadopago_preference_id),
    ADD KEY idx_subscriptions_mercadopago_payment (mercadopago_payment_id);

UPDATE subscriptions
SET external_uuid = UUID()
WHERE external_uuid IS NULL OR external_uuid = '';

UPDATE subscriptions
SET plan_type = CASE
    WHEN plan_type IS NULL OR plan_type = '' THEN 'monthly'
    ELSE plan_type
END;

UPDATE subscriptions
SET payment_status = CASE
    WHEN status = 'active' THEN 'approved'
    WHEN status = 'trialing' THEN 'pending'
    WHEN status = 'canceled' THEN 'cancelled'
    ELSE 'failed'
END,
amount = CASE
    WHEN amount <= 0 THEN COALESCE((SELECT bp.price_cents FROM billing_plans bp WHERE bp.id = subscriptions.plan_id LIMIT 1), 0) / 100
    ELSE amount
END,
duration_days = CASE
    WHEN duration_days > 0 THEN duration_days
    ELSE CASE
        WHEN COALESCE((SELECT bp.interval_months FROM billing_plans bp WHERE bp.id = subscriptions.plan_id LIMIT 1), 1) >= 12 THEN 365
        WHEN COALESCE((SELECT bp.interval_months FROM billing_plans bp WHERE bp.id = subscriptions.plan_id LIMIT 1), 1) >= 3 THEN 90
        ELSE 30
    END
END,
start_date = COALESCE(start_date, started_at),
end_date = COALESCE(end_date, current_period_end);

ALTER TABLE subscriptions
    MODIFY COLUMN external_uuid CHAR(36) NOT NULL,
    MODIFY COLUMN plan_type VARCHAR(20) NOT NULL DEFAULT 'monthly';

INSERT INTO billing_plans (code, name, description, price_cents, currency, interval_months, max_team_members, max_monthly_messages, is_active, created_at, updated_at)
VALUES
('monthly', 'Mensal', 'Acesso total por 1 mes.', 2999, 'BRL', 1, 9999, 999999, 1, NOW(), NOW()),
('quarterly', 'Trimestral', 'Acesso total por 3 meses.', 7999, 'BRL', 3, 9999, 999999, 1, NOW(), NOW()),
('annual', 'Anual', 'Acesso total por 12 meses.', 33999, 'BRL', 12, 9999, 999999, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    price_cents = VALUES(price_cents),
    currency = VALUES(currency),
    interval_months = VALUES(interval_months),
    is_active = VALUES(is_active),
    updated_at = NOW();
