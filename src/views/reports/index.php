<?php
$summary = $summary ?? [
    'appointments_total' => 0,
    'appointments_confirmed' => 0,
    'confirmation_rate' => 0,
    'unique_patients' => 0,
    'paid_revenue' => 0,
];
$statusRows = $statusRows ?? [];
$procedureRows = $procedureRows ?? [];
$dailyRows = $dailyRows ?? [];
$preset = (string) ($preset ?? '');
$compareEnabled = (bool) ($compareEnabled ?? false);
$comparison = $comparison ?? null;

$reportsQueryBase = 'route=reports&from=' . $from->format('Y-m-d') . '&to=' . $to->format('Y-m-d');
$reportsCompareParam = $compareEnabled ? '&compare=1' : '';

$statusLabels = json_encode(array_map(static fn(array $r): string => ucfirst((string) ($r['status'] ?? '')), $statusRows), JSON_UNESCAPED_UNICODE);
$statusValues = json_encode(array_map(static fn(array $r): int => (int) ($r['total'] ?? 0), $statusRows));

$procedureLabels = json_encode(array_map(static fn(array $r): string => (string) ($r['procedimento'] ?? 'Sem nome'), $procedureRows), JSON_UNESCAPED_UNICODE);
$procedureValues = json_encode(array_map(static fn(array $r): int => (int) ($r['total'] ?? 0), $procedureRows));

$dailyLabels = json_encode(array_map(static fn(array $r): string => (string) ($r['day'] ?? ''), $dailyRows), JSON_UNESCAPED_UNICODE);
$dailyAppointments = json_encode(array_map(static fn(array $r): int => (int) ($r['appointments_total'] ?? 0), $dailyRows));
$dailyConfirmed = json_encode(array_map(static fn(array $r): int => (int) ($r['confirmed_total'] ?? 0), $dailyRows));
$dailyRevenue = json_encode(array_map(static fn(array $r): float => (float) ($r['paid_revenue'] ?? 0), $dailyRows));

$cmpMeta = static function (float $value): array {
    if ($value > 0) {
        return ['arrow' => '+', 'tone' => 'up', 'label' => 'Acima'];
    }
    if ($value < 0) {
        return ['arrow' => '-', 'tone' => 'down', 'label' => 'Abaixo'];
    }
    return ['arrow' => '=', 'tone' => 'neutral', 'label' => 'Estável'];
};
?>

<style>
.cmp-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700}
.cmp-badge.up{background:#dcfce7;color:#166534;border:1px solid #86efac}
.cmp-badge.down{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
.cmp-badge.neutral{background:#f1f5f9;color:#475569;border:1px solid #cbd5e1}
.cmp-delta-value{font-size:24px;font-weight:800;line-height:1;color:#1e3a8a}
.reports-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.reports-chart-wrap{position:relative;height:230px;margin-bottom:12px}
@media (max-width: 980px){
    .reports-grid-2{grid-template-columns:1fr}
}
</style>

<h1 class="page-title">Relatórios</h1>
<p class="muted" style="margin:0 0 14px;">Análises consolidadas de consultas, receita e produtividade.</p>

<div class="card">
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
        <a href="<?= e(base_url('route=reports&preset=7d' . ($compareEnabled ? '&compare=1' : ''))) ?>" class="period-tab <?= $preset === '7d' ? 'active' : '' ?>" style="height:34px;padding:0 12px;border-radius:10px;border:1px solid #cbd5e1;background:<?= $preset === '7d' ? '#2563eb' : '#fff' ?>;color:<?= $preset === '7d' ? '#fff' : '#334155' ?>;text-decoration:none;display:inline-flex;align-items:center;font-size:13px;font-weight:600;">Ultimos 7 dias</a>
        <a href="<?= e(base_url('route=reports&preset=30d' . ($compareEnabled ? '&compare=1' : ''))) ?>" class="period-tab <?= $preset === '30d' ? 'active' : '' ?>" style="height:34px;padding:0 12px;border-radius:10px;border:1px solid #cbd5e1;background:<?= $preset === '30d' ? '#2563eb' : '#fff' ?>;color:<?= $preset === '30d' ? '#fff' : '#334155' ?>;text-decoration:none;display:inline-flex;align-items:center;font-size:13px;font-weight:600;">Ultimos 30 dias</a>
        <a href="<?= e(base_url('route=reports&preset=month' . ($compareEnabled ? '&compare=1' : ''))) ?>" class="period-tab <?= $preset === 'month' ? 'active' : '' ?>" style="height:34px;padding:0 12px;border-radius:10px;border:1px solid #cbd5e1;background:<?= $preset === 'month' ? '#2563eb' : '#fff' ?>;color:<?= $preset === 'month' ? '#fff' : '#334155' ?>;text-decoration:none;display:inline-flex;align-items:center;font-size:13px;font-weight:600;">Mês atual</a>
        <a href="<?= e(base_url($reportsQueryBase . ($compareEnabled ? '' : '&compare=1'))) ?>" class="period-tab" style="height:34px;padding:0 12px;border-radius:10px;border:1px solid #cbd5e1;background:<?= $compareEnabled ? '#16a34a' : '#fff' ?>;color:<?= $compareEnabled ? '#fff' : '#334155' ?>;text-decoration:none;display:inline-flex;align-items:center;font-size:13px;font-weight:600;">Comparar período anterior</a>
        <?php if ($compareEnabled): ?>
            <a href="<?= e(base_url($reportsQueryBase)) ?>" class="period-tab" style="height:34px;padding:0 12px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;color:#334155;text-decoration:none;display:inline-flex;align-items:center;font-size:13px;font-weight:600;">Remover comparação</a>
        <?php endif; ?>
    </div>

    <form method="get" action="<?= e(base_url()) ?>" class="form-grid">
        <input type="hidden" name="route" value="reports">
        <input type="hidden" name="preset" value="<?= e($preset) ?>">
        <input type="hidden" name="compare" value="<?= $compareEnabled ? '1' : '0' ?>">
        <div class="field">
            <label>De</label>
            <input type="date" name="from" value="<?= e($from->format('Y-m-d')) ?>">
        </div>
        <div class="field">
            <label>Até</label>
            <input type="date" name="to" value="<?= e($to->format('Y-m-d')) ?>">
        </div>
        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <button type="submit">Aplicar filtro</button>
        </div>
        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <a href="<?= e(base_url('route=reports&action=export_csv&from=' . $from->format('Y-m-d') . '&to=' . $to->format('Y-m-d') . '&preset=' . urlencode($preset))) ?>" class="btn-secondary" style="display:inline-flex;align-items:center;justify-content:center;height:40px;text-decoration:none;color:#fff;">Exportar CSV</a>
        </div>
        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <a href="<?= e(base_url('route=reports&action=export_pdf&from=' . $from->format('Y-m-d') . '&to=' . $to->format('Y-m-d') . '&preset=' . urlencode($preset))) ?>" target="_blank" class="btn-secondary" style="display:inline-flex;align-items:center;justify-content:center;height:40px;text-decoration:none;color:#fff;">Exportar PDF</a>
        </div>
    </form>
</div>

<?php if ($compareEnabled && is_array($comparison)): ?>
<div class="card">
    <h3 class="card-title">Comparação com período anterior</h3>
    <div class="stats-grid">
        <?php $cmp = $cmpMeta((float) ($comparison['appointments_total']['delta'] ?? 0)); ?>
        <div class="stat-card"><div class="stat-label">Consultas</div><div class="cmp-delta-value"><?= e((string) ($comparison['appointments_total']['delta'] ?? 0)) ?></div><div class="cmp-badge <?= e((string) $cmp['tone']) ?>"><span><?= e((string) $cmp['arrow']) ?></span><span><?= e((string) $cmp['label']) ?> · <?= e((string) ($comparison['appointments_total']['delta_pct'] ?? 0)) ?>% em relação ao período anterior</span></div></div>

        <?php $cmp = $cmpMeta((float) ($comparison['appointments_confirmed']['delta'] ?? 0)); ?>
        <div class="stat-card"><div class="stat-label">Confirmadas/Realizadas</div><div class="cmp-delta-value"><?= e((string) ($comparison['appointments_confirmed']['delta'] ?? 0)) ?></div><div class="cmp-badge <?= e((string) $cmp['tone']) ?>"><span><?= e((string) $cmp['arrow']) ?></span><span><?= e((string) $cmp['label']) ?> · <?= e((string) ($comparison['appointments_confirmed']['delta_pct'] ?? 0)) ?>% em relação ao período anterior</span></div></div>

        <?php $cmp = $cmpMeta((float) ($comparison['confirmation_rate']['delta'] ?? 0)); ?>
        <div class="stat-card"><div class="stat-label">Taxa de confirmação (p.p.)</div><div class="cmp-delta-value"><?= e((string) ($comparison['confirmation_rate']['delta'] ?? 0)) ?></div><div class="cmp-badge <?= e((string) $cmp['tone']) ?>"><span><?= e((string) $cmp['arrow']) ?></span><span><?= e((string) $cmp['label']) ?> · <?= e((string) ($comparison['confirmation_rate']['delta_pct'] ?? 0)) ?>% em relação ao período anterior</span></div></div>

        <?php $cmp = $cmpMeta((float) ($comparison['unique_patients']['delta'] ?? 0)); ?>
        <div class="stat-card"><div class="stat-label">Pacientes únicos</div><div class="cmp-delta-value"><?= e((string) ($comparison['unique_patients']['delta'] ?? 0)) ?></div><div class="cmp-badge <?= e((string) $cmp['tone']) ?>"><span><?= e((string) $cmp['arrow']) ?></span><span><?= e((string) $cmp['label']) ?> · <?= e((string) ($comparison['unique_patients']['delta_pct'] ?? 0)) ?>% em relação ao período anterior</span></div></div>

        <?php $cmp = $cmpMeta((float) ($comparison['paid_revenue']['delta'] ?? 0)); ?>
        <div class="stat-card"><div class="stat-label">Receita (R$)</div><div class="cmp-delta-value"><?= e((string) ($comparison['paid_revenue']['delta'] ?? 0)) ?></div><div class="cmp-badge <?= e((string) $cmp['tone']) ?>"><span><?= e((string) $cmp['arrow']) ?></span><span><?= e((string) $cmp['label']) ?> · <?= e((string) ($comparison['paid_revenue']['delta_pct'] ?? 0)) ?>% em relação ao período anterior</span></div></div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Consultas no período</div>
            <div class="stat-value"><?= (int) $summary['appointments_total'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Confirmadas/Realizadas</div>
            <div class="stat-value"><?= (int) $summary['appointments_confirmed'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Taxa de confirmação</div>
            <div class="stat-value"><?= e((string) $summary['confirmation_rate']) ?>%</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pacientes únicos</div>
            <div class="stat-value"><?= (int) $summary['unique_patients'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Receita recebida</div>
            <div class="stat-value">R$ <?= e(number_format((float) $summary['paid_revenue'], 2, ',', '.')) ?></div>
        </div>
    </div>
</div>

<div class="reports-grid-2">
<div class="card">
    <h3 class="card-title">Status das consultas</h3>
    <?php if (!empty($statusRows)): ?>
        <div class="reports-chart-wrap"><canvas id="reportsStatusChart"></canvas></div>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Status</th><th>Total</th></tr>
            </thead>
            <tbody>
                <?php if (empty($statusRows)): ?>
                    <tr><td colspan="2" class="muted">Sem dados no período.</td></tr>
                <?php else: ?>
                    <?php foreach ($statusRows as $row): ?>
                        <tr>
                            <td><?= e(ucfirst((string) $row['status'])) ?></td>
                            <td><?= (int) $row['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 class="card-title">Procedimentos principais</h3>
    <?php if (!empty($procedureRows)): ?>
        <div class="reports-chart-wrap"><canvas id="reportsProcedureChart"></canvas></div>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Procedimento</th><th>Total</th><th>Receita recebida</th></tr>
            </thead>
            <tbody>
                <?php if (empty($procedureRows)): ?>
                    <tr><td colspan="3" class="muted">Sem dados no período.</td></tr>
                <?php else: ?>
                    <?php foreach ($procedureRows as $row): ?>
                        <tr>
                            <td><?= e((string) $row['procedimento']) ?></td>
                            <td><?= (int) $row['total'] ?></td>
                            <td>R$ <?= e(number_format((float) $row['paid_revenue'], 2, ',', '.')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<div class="card">
    <h3 class="card-title">Evolução diária</h3>
    <?php if (!empty($dailyRows)): ?>
        <div class="reports-chart-wrap"><canvas id="reportsDailyChart"></canvas></div>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Dia</th><th>Consultas</th><th>Confirmadas/Realizadas</th><th>Receita recebida</th></tr>
            </thead>
            <tbody>
                <?php if (empty($dailyRows)): ?>
                    <tr><td colspan="4" class="muted">Sem dados no período.</td></tr>
                <?php else: ?>
                    <?php foreach ($dailyRows as $row): ?>
                        <tr>
                            <td><?= e((string) $row['day']) ?></td>
                            <td><?= (int) $row['appointments_total'] ?></td>
                            <td><?= (int) $row['confirmed_total'] ?></td>
                            <td>R$ <?= e(number_format((float) $row['paid_revenue'], 2, ',', '.')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = 'Plus Jakarta Sans, Inter, Segoe UI, Roboto, Arial, sans-serif';
Chart.defaults.color = '#64748b';

(function () {
    var el = document.getElementById('reportsStatusChart');
    if (!el) return;

    new Chart(el, {
        type: 'doughnut',
        data: {
            labels: <?= $statusLabels ?>,
            datasets: [{
                data: <?= $statusValues ?>,
                backgroundColor: ['#3b82f6','#22c55e','#8b5cf6','#ef4444','#f97316','#a855f7'],
                borderColor: '#fff',
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: { legend: { position: 'bottom' } }
        }
    });
}());

(function () {
    var el = document.getElementById('reportsProcedureChart');
    if (!el) return;

    new Chart(el, {
        type: 'bar',
        data: {
            labels: <?= $procedureLabels ?>,
            datasets: [{
                label: 'Consultas',
                data: <?= $procedureValues ?>,
                backgroundColor: 'rgba(37,99,235,.18)',
                borderColor: '#2563eb',
                borderWidth: 1.5,
                borderRadius: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
}());

(function () {
    var el = document.getElementById('reportsDailyChart');
    if (!el) return;

    new Chart(el, {
        type: 'line',
        data: {
            labels: <?= $dailyLabels ?>,
            datasets: [
                {
                    label: 'Consultas',
                    data: <?= $dailyAppointments ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,.08)',
                    fill: true,
                    tension: .3,
                },
                {
                    label: 'Confirmadas/Realizadas',
                    data: <?= $dailyConfirmed ?>,
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22,163,74,.08)',
                    fill: true,
                    tension: .3,
                },
                {
                    label: 'Receita (R$)',
                    data: <?= $dailyRevenue ?>,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,.08)',
                    yAxisID: 'y1',
                    fill: false,
                    tension: .3,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
}());
</script>

