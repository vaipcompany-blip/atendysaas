<?php
$subscription = $subscription ?? [];
$plans = $plans ?? [];
$message = $message ?? null;
$statusLabel = $statusLabel ?? '';
$publicKey = $publicKey ?? '';

$priceFormatter = function (float $amount): string {
    return 'R$ ' . number_format($amount, 2, ',', '.');
};

$currentPlanCode = (string) ($subscription['plan_code'] ?? '');
$currentStatus = (string) ($subscription['status_normalized'] ?? 'pending');
$daysRemaining = (int) ($subscription['days_remaining'] ?? 0);
$subscriptionEndDate = (string) ($subscription['end_date'] ?? '');
$currentPlanType = (string) ($subscription['plan_type'] ?? '');

$statusTone = 'pending';
if ($currentStatus === 'active') {
    $statusTone = 'active';
}
if ($currentStatus === 'expired') {
    $statusTone = 'expired';
}
?>

<style>
.pricing-wrap{display:flex;flex-direction:column;gap:14px}
.pricing-hero{border-radius:18px;padding:20px;background:linear-gradient(120deg,#164e63,#0f766e 45%,#14b8a6);color:#ecfeff;box-shadow:0 14px 30px rgba(15,118,110,.25)}
.pricing-hero h1{margin:0 0 6px;font-size:28px;line-height:1.15}
.pricing-hero p{margin:0;color:rgba(236,254,255,.88)}
.subscription-status{border:1px solid #dbeafe;background:#eff6ff;border-radius:14px;padding:14px}
.subscription-status.active{border-color:#86efac;background:#f0fdf4}
.subscription-status.expired{border-color:#fecaca;background:#fef2f2}
.subscription-status.pending{border-color:#fde68a;background:#fffbeb}
.status-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.status-chip{display:inline-flex;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#dbeafe;color:#1d4ed8}
.status-chip.active{background:#dcfce7;color:#166534}
.status-chip.expired{background:#fee2e2;color:#991b1b}
.status-chip.pending{background:#fef3c7;color:#92400e}
.status-warning{margin-top:9px;font-size:13px;font-weight:700;padding:9px 11px;border-radius:10px}
.status-warning.warn{color:#92400e;background:#fef3c7;border:1px solid #fcd34d}
.status-warning.danger{color:#991b1b;background:#fee2e2;border:1px solid #fca5a5}
.plans-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:14px}
.plan-card{border:1px solid #dbe7f5;background:#fff;border-radius:16px;padding:16px;box-shadow:0 8px 18px rgba(15,23,42,.06);display:flex;flex-direction:column;gap:10px;position:relative}
.plan-card.highlight{border-color:#0ea5a4;box-shadow:0 12px 26px rgba(20,184,166,.2)}
.plan-name{font-size:18px;font-weight:800;color:#0f172a}
.plan-price{font-size:30px;font-weight:800;color:#0f766e;line-height:1}
.plan-duration{font-size:13px;color:#64748b}
.plan-save{position:absolute;top:12px;right:12px;background:#dcfce7;color:#166534;font-weight:700;font-size:11px;padding:4px 8px;border-radius:999px}
.plan-features{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;font-size:13px;color:#334155}
.pay-note{font-size:12px;color:#475569}
</style>

<div class="pricing-wrap">
    <section class="pricing-hero">
        <h1>Planos e Assinatura</h1>
        <p>Acesso total ao SaaS somente com pagamento aprovado. Pagamento via Mercado Pago com PIX e cartao.</p>
    </section>

    <?php if (!empty($message)): ?>
        <div class="alert"><?= e((string) $message) ?></div>
    <?php endif; ?>

    <section class="subscription-status <?= e($statusTone) ?>">
        <div class="status-row">
            <span class="status-chip <?= e($statusTone) ?>">Sua assinatura: <?= e((string) $statusLabel) ?></span>
            <span class="muted">Plano: <strong><?= e($currentPlanType !== '' ? $currentPlanType : 'nenhum') ?></strong></span>
            <span class="muted">Valida ate: <strong><?= e($subscriptionEndDate !== '' ? date('d/m/Y', strtotime($subscriptionEndDate)) : '-') ?></strong></span>
            <span class="muted">Dias restantes: <strong><?= e((string) $daysRemaining) ?></strong></span>
        </div>

        <?php if ($currentStatus === 'active' && $daysRemaining > 0 && $daysRemaining < 7): ?>
            <div class="status-warning <?= $daysRemaining < 3 ? 'danger' : 'warn' ?>">
                <?= $daysRemaining < 3 ? 'Sua assinatura vence em poucos dias. Renove agora.' : 'Sua assinatura vence em breve. Planeje a renovacao.' ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="plans-grid">
        <?php foreach ($plans as $plan): ?>
            <?php
            $planCode = (string) ($plan['id'] ?? $plan['code'] ?? '');
            $planPrice = (float) ($plan['price'] ?? 0.0);
            $isCurrent = $currentPlanType !== '' && $planCode === $currentPlanType;
            ?>
            <article class="plan-card <?= $isCurrent ? 'highlight' : '' ?>">
                <?php if (!empty($plan['save'])): ?>
                    <span class="plan-save">Economia <?= e((string) $plan['save']) ?></span>
                <?php endif; ?>
                <div class="plan-name"><?= e((string) ($plan['name'] ?? 'Plano')) ?></div>
                <div class="plan-price"><?= e($priceFormatter($planPrice)) ?></div>
                <div class="plan-duration"><?= e((string) ($plan['duration'] ?? '')) ?></div>

                <ul class="plan-features">
                    <li>✓ Acesso total ao SaaS</li>
                    <li>✓ Todas as features</li>
                    <li>✓ Suporte por email</li>
                    <li>✓ Atualizacoes incluidas</li>
                </ul>

                <div class="pay-note">Pagamento: Cartao e PIX via Kirvano. Acesso liberado automaticamente.</div>

                <?php
                $kirvanoEnvKey = match ($planCode) {
                    'monthly'   => 'KIRVANO_CHECKOUT_MENSAL',
                    'quarterly' => 'KIRVANO_CHECKOUT_TRIMESTRAL',
                    'annual'    => 'KIRVANO_CHECKOUT_ANUAL',
                    default     => '',
                };
                $kirvanoLink = $kirvanoEnvKey !== '' ? trim((string) (getenv($kirvanoEnvKey) ?: '')) : '';
                ?>
                <?php if ($kirvanoLink !== ''): ?>
                    <a href="<?= e($kirvanoLink) ?>" target="_blank" rel="noopener noreferrer" class="btn-block" style="text-align:center;display:block;text-decoration:none">
                        <?= $isCurrent ? 'Renovar assinatura' : 'Assinar plano' ?>
                    </a>
                <?php else: ?>
                    <button type="button" class="btn-block" disabled style="opacity:.5;cursor:not-allowed">Checkout em breve</button>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>

    <?php if (trim((string) (getenv('KIRVANO_CHECKOUT_MENSAL') ?: '')) === ''): ?>
        <div class="alert">Kirvano ainda nao configurado. Defina KIRVANO_CHECKOUT_MENSAL, KIRVANO_CHECKOUT_TRIMESTRAL e KIRVANO_CHECKOUT_ANUAL no ambiente.</div>
    <?php endif; ?>
</div>
