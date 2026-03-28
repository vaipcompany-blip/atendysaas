<?php
$subscription = $subscription ?? [];
$plans = $plans ?? [];
$message = $message ?? null;
$statusLabel = $statusLabel ?? '';

$priceFormatter = function (int $cents, string $currency): string {
    $amount = $cents / 100;
    if (strtoupper($currency) === 'BRL') {
        return 'R$ ' . number_format($amount, 2, ',', '.');
    }

    return strtoupper($currency) . ' ' . number_format($amount, 2, '.', ',');
};

$currentPlanCode = (string) ($subscription['plan_code'] ?? '');
$currentStatus = (string) ($subscription['status'] ?? 'past_due');
?>

<h1 class="page-title">Assinatura</h1>
<p class="muted">Gerencie o plano da clínica e o status de acesso comercial do ambiente.</p>

<?php if (!empty($message)): ?>
    <div class="alert"><?= e((string) $message) ?></div>
<?php endif; ?>

<div class="card">
    <h3 class="card-title">Assinatura atual</h3>
    <div class="row" style="margin-bottom:8px;">
        <span class="chip">Status: <?= e((string) $statusLabel) ?></span>
        <span class="muted">Plano: <strong><?= e((string) ($subscription['plan_name'] ?? 'N/A')) ?></strong></span>
    </div>

    <div class="muted">
        <div>Início: <?= e((string) ($subscription['started_at'] ?? '-')) ?></div>
        <div>Trial até: <?= e((string) ($subscription['trial_ends_at'] ?? '-')) ?></div>
        <div>Período atual até: <?= e((string) ($subscription['current_period_end'] ?? '-')) ?></div>
    </div>

    <?php if ($currentStatus !== 'canceled'): ?>
        <form method="post" action="<?= e(base_url('route=billing')) ?>" style="margin-top:12px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel_subscription">
            <button type="submit" class="btn-danger">Cancelar assinatura</button>
        </form>
    <?php endif; ?>
</div>

<div class="stats-grid">
    <?php foreach ($plans as $plan): ?>
        <?php
        $planCode = (string) ($plan['code'] ?? '');
        $isCurrent = $planCode === $currentPlanCode;
        ?>
        <div class="stat-card" style="border-color:<?= $isCurrent ? '#93c5fd' : '#dbeafe' ?>;">
            <div class="stat-label"><?= e((string) ($plan['name'] ?? 'Plano')) ?></div>
            <div class="stat-value" style="font-size:22px;"><?= e($priceFormatter((int) ($plan['price_cents'] ?? 0), (string) ($plan['currency'] ?? 'BRL'))) ?></div>
            <div class="muted" style="margin-top:6px;"><?= e((string) ($plan['description'] ?? '')) ?></div>
            <div class="muted" style="margin-top:8px;">Equipe: até <?= e((string) ($plan['max_team_members'] ?? 1)) ?> membros</div>
            <div class="muted">Mensagens/mês: <?= e((string) ($plan['max_monthly_messages'] ?? 0)) ?></div>

            <form method="post" action="<?= e(base_url('route=billing')) ?>" style="margin-top:10px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="activate_plan">
                <input type="hidden" name="plan_code" value="<?= e($planCode) ?>">
                <button type="submit" <?= $isCurrent ? 'disabled' : '' ?>><?= $isCurrent ? 'Plano atual' : 'Ativar plano' ?></button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
