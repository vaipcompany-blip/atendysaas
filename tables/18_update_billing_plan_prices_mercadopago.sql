UPDATE billing_plans
SET price_cents = 2999,
    interval_months = 1,
    updated_at = NOW()
WHERE code = 'monthly';

UPDATE billing_plans
SET price_cents = 7999,
    interval_months = 3,
    updated_at = NOW()
WHERE code = 'quarterly';

UPDATE billing_plans
SET price_cents = 33999,
    interval_months = 12,
    updated_at = NOW()
WHERE code = 'annual';
