CREATE TABLE IF NOT EXISTS billing_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    price_cents INT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    interval_months INT UNSIGNED NOT NULL DEFAULT 1,
    max_team_members INT UNSIGNED NOT NULL DEFAULT 1,
    max_monthly_messages INT UNSIGNED NOT NULL DEFAULT 500,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_billing_plans_code (code),
    KEY idx_billing_plans_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    status ENUM('trialing','active','past_due','canceled','suspended') NOT NULL DEFAULT 'trialing',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    trial_ends_at DATETIME NULL,
    current_period_end DATETIME NULL,
    canceled_at DATETIME NULL,
    payment_provider VARCHAR(40) NULL,
    payment_reference VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_subscriptions_user (user_id),
    KEY idx_subscriptions_status (status),
    KEY idx_subscriptions_plan (plan_id),
    CONSTRAINT fk_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES billing_plans(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS billing_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'success',
    amount_cents INT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    provider_reference VARCHAR(120) NULL,
    payload_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_billing_events_subscription (subscription_id),
    KEY idx_billing_events_user (user_id),
    KEY idx_billing_events_type (event_type),
    CONSTRAINT fk_billing_events_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    CONSTRAINT fk_billing_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO billing_plans (code, name, description, price_cents, currency, interval_months, max_team_members, max_monthly_messages, is_active, created_at, updated_at)
VALUES
('starter', 'Starter', 'Plano inicial para clínicas em começo de operação.', 9900, 'BRL', 1, 3, 2000, 1, NOW(), NOW()),
('growth', 'Growth', 'Plano para clínicas com maior volume de atendimento.', 19900, 'BRL', 1, 10, 8000, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    price_cents = VALUES(price_cents),
    currency = VALUES(currency),
    interval_months = VALUES(interval_months),
    max_team_members = VALUES(max_team_members),
    max_monthly_messages = VALUES(max_monthly_messages),
    is_active = VALUES(is_active),
    updated_at = NOW();
