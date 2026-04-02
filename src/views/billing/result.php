<?php
$status = $status ?? 'pending';
$subscription = $subscription ?? [];
$planType = (string) ($subscription['plan_type'] ?? '-');
$endDate = (string) ($subscription['end_date'] ?? '');
$daysRemaining = (int) ($subscription['days_remaining'] ?? 0);

$title = 'Pagamento em processamento';
$message = 'Seu pagamento esta sendo processado. Isso pode levar alguns minutos para PIX.';
$tone = '#92400e';
$bg = '#fffbeb';
$border = '#fcd34d';

if ($status === 'success') {
    $title = 'Pagamento realizado com sucesso';
    $message = 'A confirmacao pode levar alguns instantes. Assim que aprovado, o acesso sera liberado automaticamente.';
    $tone = '#166534';
    $bg = '#f0fdf4';
    $border = '#86efac';
}

if ($status === 'failure') {
    $title = 'Pagamento nao aprovado';
    $message = 'Nao se preocupe. Tente novamente com outro metodo de pagamento.';
    $tone = '#991b1b';
    $bg = '#fef2f2';
    $border = '#fca5a5';
}
?>

<div class="card" style="max-width:760px;margin:0 auto;border-color:<?= e($border) ?>;background:<?= e($bg) ?>;">
    <h2 class="card-title" style="color:<?= e($tone) ?>;"><?= e($title) ?></h2>
    <p style="margin-top:0;color:#334155;"><?= e($message) ?></p>

    <div class="muted" style="line-height:1.8;">
        <div><strong>Plano:</strong> <?= e($planType !== '' ? $planType : '-') ?></div>
        <div><strong>Expiracao prevista:</strong> <?= e($endDate !== '' ? date('d/m/Y', strtotime($endDate)) : '-') ?></div>
        <div><strong>Dias de acesso:</strong> <?= e((string) $daysRemaining) ?></div>
    </div>

    <div class="row" style="margin-top:16px;">
        <a href="<?= e(base_url('route=dashboard')) ?>" style="display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:10px;background:#0f766e;color:#fff;text-decoration:none;font-weight:700;">Ir para o Dashboard</a>
        <a href="<?= e(base_url('route=pricing')) ?>" style="display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:10px;background:#334155;color:#fff;text-decoration:none;font-weight:700;">Voltar aos Planos</a>
    </div>
</div>

<?php if ($status === 'success'): ?>
<script>
setTimeout(function () {
    window.location.href = '<?= e(base_url('route=dashboard')) ?>';
}, 5000);
</script>
<?php endif; ?>
