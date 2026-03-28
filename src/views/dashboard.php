<?php
$weekLabels  = json_encode(array_column($weeklyData, 'week'), JSON_UNESCAPED_UNICODE);
$weekTotals  = json_encode(array_column($weeklyData, 'total'));
$msgLabels   = json_encode(array_column($dailyMessages, 'day'), JSON_UNESCAPED_UNICODE);
$msgSent     = json_encode(array_column($dailyMessages, 'sent'));
$msgReceived = json_encode(array_column($dailyMessages, 'received'));

$statusLabelList = ['Agendada','Confirmada','Realizada','Cancelada','Faltou','Reagendada'];
$statusValueList = [
    (int) ($statusDistribution['agendada'] ?? 0),
    (int) ($statusDistribution['confirmada'] ?? 0),
    (int) ($statusDistribution['realizada'] ?? 0),
    (int) ($statusDistribution['cancelada'] ?? 0),
    (int) ($statusDistribution['faltou'] ?? 0),
    (int) ($statusDistribution['reagendada'] ?? 0),
];
$statusLabels = json_encode($statusLabelList, JSON_UNESCAPED_UNICODE);
$statusValues = json_encode($statusValueList);
$statusColors = json_encode(['#3b82f6','#22c55e','#8b5cf6','#ef4444','#f97316','#a855f7']);

$deliveryComplement = max(0, 100 - (float) $deliveryRate);
$deliveryGaugeValues = json_encode([(float) $deliveryRate, (float) $deliveryComplement]);

$leadPipelineData = $leadPipeline ?? [
    'leads' => 0,
    'with_appointment' => 0,
    'with_confirmation' => 0,
    'schedule_rate' => 0.0,
    'confirmation_rate' => 0.0,
];

$leadPipelineLabels = json_encode(['Contatos', 'Com agendamento', 'Com confirmação'], JSON_UNESCAPED_UNICODE);
$leadPipelineValues = json_encode([
    (int) ($leadPipelineData['leads'] ?? 0),
    (int) ($leadPipelineData['with_appointment'] ?? 0),
    (int) ($leadPipelineData['with_confirmation'] ?? 0),
]);

$monthlyGoalData = $monthlyConversionGoal ?? [
    'month_label' => date('m/Y'),
    'leads' => 0,
    'confirmed' => 0,
    'conversion_rate' => 0.0,
    'target_rate' => 60.0,
    'achievement_rate' => 0.0,
];
$monthlyGoalProgress = min(100.0, (float) ($monthlyGoalData['achievement_rate'] ?? 0.0));

$kpiDeltaBadge = static function (float $delta): array {
    if ($delta > 0) {
        return ['arrow' => '↑', 'tone' => 'up', 'label' => 'Acima'];
    }
    if ($delta < 0) {
        return ['arrow' => '↓', 'tone' => 'down', 'label' => 'Abaixo'];
    }
    return ['arrow' => '•', 'tone' => 'neutral', 'label' => 'Estável'];
};

$confirmationTarget = 80.0;
$noShowTarget = 10.0;
$deliveryTarget = 90.0;

$confirmationDelta = round((float) $confirmationRate - $confirmationTarget, 1);
$noShowDelta = round($noShowTarget - (float) $noShowRate, 1);
$deliveryDelta = round((float) $deliveryRate - $deliveryTarget, 1);
$monthlyConversionDelta = round((float) ($monthlyGoalData['conversion_rate'] ?? 0.0) - (float) ($monthlyGoalData['target_rate'] ?? 60.0), 1);

$confirmationDeltaMeta = $kpiDeltaBadge($confirmationDelta);
$noShowDeltaMeta = $kpiDeltaBadge($noShowDelta);
$deliveryDeltaMeta = $kpiDeltaBadge($deliveryDelta);
$monthlyConversionDeltaMeta = $kpiDeltaBadge($monthlyConversionDelta);

$patientPerformanceData = $patientPerformance ?? [
    'patients_with_appointments' => 0,
    'returning_patients' => 0,
    'recurrence_rate' => 0.0,
    'avg_appointments_per_patient' => 0.0,
    'avg_revenue_per_patient' => 0.0,
    'top_patients' => [],
];
?>

<style>
.dash-wrap{display:flex;flex-direction:column;gap:16px}
.hero{background:linear-gradient(120deg,#2563eb 0%,#1d4ed8 58%,#3b82f6 100%);color:#fff;border-radius:20px;padding:22px 24px;box-shadow:0 12px 30px rgba(37,99,235,.28);display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
.hero h1{margin:0;font-size:28px;line-height:1.15;letter-spacing:-.02em}
.hero p{margin:6px 0 0;color:rgba(255,255,255,.84)}
.period-bar{display:flex;gap:8px;flex-wrap:wrap}
.period-btn{padding:7px 13px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;border:1px solid rgba(255,255,255,.35);color:#fff;background:rgba(255,255,255,.12);backdrop-filter:blur(4px);transition:.16s}
.period-btn:hover{background:rgba(255,255,255,.2)}
.period-btn.active{background:#fff;color:#1d4ed8;border-color:#fff}

.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.kpi{background:#fff;border:1px solid #e4ebf5;border-radius:16px;padding:14px 15px;box-shadow:0 8px 20px rgba(15,23,42,.05);transition:transform .18s ease, box-shadow .18s ease, opacity .3s ease}
.kpi:hover{transform:translateY(-3px);box-shadow:0 14px 28px rgba(15,23,42,.09)}
.kpi-label{font-size:12px;color:#64748b;font-weight:600;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.kpi-value{font-size:30px;font-weight:800;line-height:1;color:#1e3a8a}
.kpi-sub{font-size:12px;color:#94a3b8;margin-top:6px}
.kpi-delta{display:inline-flex;align-items:center;gap:6px;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;margin-top:8px}
.kpi-delta.up{background:#dcfce7;color:#166534;border:1px solid #86efac}
.kpi-delta.down{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
.kpi-delta.neutral{background:#f1f5f9;color:#475569;border:1px solid #cbd5e1}

.grid-2{display:grid;grid-template-columns:1.7fr 1fr;gap:14px}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
.panel{background:#fff;border:1px solid #e4ebf5;border-radius:16px;padding:16px;box-shadow:0 8px 20px rgba(15,23,42,.05);transition:transform .18s ease, box-shadow .18s ease, opacity .3s ease}
.panel:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(15,23,42,.08)}
.panel-title{margin:0 0 12px;font-size:15px;font-weight:700;color:#0f172a}
.panel-sub{color:#94a3b8;font-size:12px;font-weight:500}

.reveal{opacity:0;transform:translateY(8px)}
.reveal.is-visible{opacity:1;transform:translateY(0)}

.insight{margin-top:10px;padding:10px 12px;border-radius:10px;background:#f8fbff;border:1px solid #dbeafe;color:#334155;font-size:13px}

.upcoming-list{display:flex;flex-direction:column;gap:8px;max-height:320px;overflow:auto;padding-right:2px}
.upcoming-item{display:flex;gap:10px;align-items:flex-start;border:1px solid #eef2f7;background:#fbfdff;border-radius:12px;padding:10px}
.u-time{min-width:74px;font-weight:700;color:#1d4ed8;font-size:12px}
.u-name{font-weight:700;color:#0f172a;font-size:13px}
.u-proc{color:#64748b;font-size:12px}
.u-badge{font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;background:#eaf1ff;color:#1d4ed8}

.goal-track{width:100%;height:10px;border-radius:999px;background:#e2e8f0;overflow:hidden;margin-top:8px}
.goal-fill{height:100%;background:linear-gradient(90deg,#16a34a,#22c55e)}
.goal-meta{font-size:12px;color:#475569;margin-top:8px;line-height:1.5}
.perf-kpi-row{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:10px;margin-top:10px}
.perf-kpi{border:1px solid #e6edf8;background:#f8fbff;border-radius:12px;padding:10px}
.perf-kpi .label{font-size:11px;color:#64748b;text-transform:uppercase;font-weight:700;letter-spacing:.4px}
.perf-kpi .value{font-size:22px;color:#1e3a8a;font-weight:800;margin-top:3px}
.top-list{display:flex;flex-direction:column;gap:8px;margin-top:10px}
.top-item{display:grid;grid-template-columns:1fr auto auto auto;gap:10px;align-items:center;border:1px solid #e8eef8;border-radius:12px;padding:10px;background:#fff}
.top-item .name{font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.top-pill{font-size:11px;padding:3px 8px;border-radius:999px;background:#eaf1ff;color:#1d4ed8;font-weight:700}

@media(max-width:1080px){
  .grid-2,.grid-3{grid-template-columns:1fr}
  .hero h1{font-size:24px}
    .perf-kpi-row{grid-template-columns:1fr 1fr}
    .top-item{grid-template-columns:1fr 1fr}
}
</style>

<div class="dash-wrap">
    <section class="hero">
        <div>
            <h1>Painel Inteligente</h1>
            <p><?= e((string) ($user['nome_consultorio'] ?? 'Clínica')) ?> · Período <?= e(date('d/m/Y', strtotime($dateFrom))) ?> a <?= e(date('d/m/Y', strtotime($dateTo))) ?></p>
        </div>
        <div class="period-bar">
            <?php foreach ($periods as $key => $label): ?>
                <a href="<?= e(base_url('route=dashboard&period=' . $key)) ?>" class="period-btn <?= $period === $key ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="kpi-grid">
        <div class="kpi"><div class="kpi-label">Pacientes</div><div class="kpi-value"><?= e((string) $totalPatients) ?></div><div class="kpi-sub">+<?= e((string) $newLeads) ?> leads no período</div></div>
        <div class="kpi"><div class="kpi-label">Consultas</div><div class="kpi-value"><?= e((string) $totalAppointments) ?></div><div class="kpi-sub"><?= e((string) $confirmed) ?> confirmadas</div></div>
        <div class="kpi"><div class="kpi-label">Confirmação</div><div class="kpi-value"><?= e((string) $confirmationRate) ?>%</div><div class="kpi-sub">Meta clínica · 80%</div><div class="kpi-delta <?= e((string) $confirmationDeltaMeta['tone']) ?>"><span><?= e((string) $confirmationDeltaMeta['arrow']) ?></span><span><?= e((string) $confirmationDeltaMeta['label']) ?> · <?= e((string) $confirmationDelta) ?> p.p. em relação à meta</span></div></div>
        <div class="kpi"><div class="kpi-label">Taxa de Falta</div><div class="kpi-value"><?= e((string) $noShowRate) ?>%</div><div class="kpi-sub">Quanto menor, melhor</div><div class="kpi-delta <?= e((string) $noShowDeltaMeta['tone']) ?>"><span><?= e((string) $noShowDeltaMeta['arrow']) ?></span><span><?= e((string) $noShowDeltaMeta['label']) ?> · <?= e((string) $noShowDelta) ?> p.p. em relação à meta</span></div></div>
        <div class="kpi"><div class="kpi-label">Msgs Enviadas</div><div class="kpi-value"><?= e((string) $messagesSent) ?></div><div class="kpi-sub"><?= e((string) $messagesReceived) ?> recebidas</div></div>
        <div class="kpi"><div class="kpi-label">Entrega/Leitura</div><div class="kpi-value"><?= e((string) $deliveryRate) ?>%</div><div class="kpi-sub">Desempenho do WhatsApp</div><div class="kpi-delta <?= e((string) $deliveryDeltaMeta['tone']) ?>"><span><?= e((string) $deliveryDeltaMeta['arrow']) ?></span><span><?= e((string) $deliveryDeltaMeta['label']) ?> · <?= e((string) $deliveryDelta) ?> p.p. em relação à meta</span></div></div>
    </section>

    <section class="grid-2">
        <div class="panel">
            <h3 class="panel-title">Evolução de Consultas <span class="panel-sub">(semanal)</span></h3>
            <?php if (!empty($weeklyData)): ?>
                <canvas id="chartWeekly" height="130"></canvas>
            <?php else: ?>
                <div class="insight">Sem consultas registradas no período selecionado.</div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h3 class="panel-title">Status das Consultas <span class="panel-sub">(circular)</span></h3>
            <?php if (array_sum($statusValueList) > 0): ?>
                <canvas id="chartStatus" height="190"></canvas>
                <div class="insight" id="statusInsight">Clique em uma fatia para ver detalhes rápidos.</div>
            <?php else: ?>
                <div class="insight">Sem dados de status no período.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid-3">
        <div class="panel" style="grid-column:span 2;">
            <h3 class="panel-title">Mensagens por dia <span class="panel-sub">(interativo)</span></h3>
            <?php if (!empty($dailyMessages)): ?>
                <canvas id="chartMessages" height="110"></canvas>
            <?php else: ?>
                <div class="insight">Nenhuma mensagem no período.</div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h3 class="panel-title">Saúde de Entrega <span class="panel-sub">(circular)</span></h3>
            <canvas id="chartDelivery" height="190"></canvas>
            <div class="insight">Entrega/leitura atual: <strong><?= e((string) $deliveryRate) ?>%</strong></div>
        </div>
    </section>

    <section class="grid-2">
        <div class="panel">
            <h3 class="panel-title">Funil de Contatos <span class="panel-sub">(comercial)</span></h3>
            <?php if ((int) ($leadPipelineData['leads'] ?? 0) > 0): ?>
                <canvas id="chartLeadPipeline" height="120"></canvas>
            <?php else: ?>
                <div class="insight">Sem leads novos no período para análise de funil.</div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h3 class="panel-title">Conversão de Contatos <span class="panel-sub">(resumo)</span></h3>
            <div class="insight" id="leadPipelineInsight">
                <strong>Contatos:</strong> <?= e((string) ((int) ($leadPipelineData['leads'] ?? 0))) ?><br>
                <strong>Com agendamento:</strong> <?= e((string) ((int) ($leadPipelineData['with_appointment'] ?? 0))) ?> (<?= e((string) ((float) ($leadPipelineData['schedule_rate'] ?? 0.0))) ?>%)<br>
                <strong>Com confirmação:</strong> <?= e((string) ((int) ($leadPipelineData['with_confirmation'] ?? 0))) ?> (<?= e((string) ((float) ($leadPipelineData['confirmation_rate'] ?? 0.0))) ?>%)
            </div>
        </div>
    </section>

    <section class="grid-2">
        <div class="panel">
            <h3 class="panel-title">Meta mensal de conversão <span class="panel-sub">(<?= e((string) ($monthlyGoalData['month_label'] ?? date('m/Y'))) ?>)</span></h3>
            <div class="goal-meta" id="monthlyGoalInsight">
                Conversão atual: <strong><?= e((string) ((float) ($monthlyGoalData['conversion_rate'] ?? 0.0))) ?>%</strong>
                de uma meta de <strong><?= e((string) ((float) ($monthlyGoalData['target_rate'] ?? 60.0))) ?>%</strong>.
            </div>
            <div class="kpi-delta <?= e((string) $monthlyConversionDeltaMeta['tone']) ?>" style="margin-top:8px;">
                <span><?= e((string) $monthlyConversionDeltaMeta['arrow']) ?></span>
                <span><?= e((string) $monthlyConversionDeltaMeta['label']) ?> · <?= e((string) $monthlyConversionDelta) ?> p.p. em relação à meta</span>
            </div>
            <div class="goal-track" aria-label="Progresso da meta mensal de conversão">
                <div class="goal-fill" style="width:<?= e((string) $monthlyGoalProgress) ?>%;"></div>
            </div>
            <div class="goal-meta">
                Contatos do mês: <strong><?= e((string) ((int) ($monthlyGoalData['leads'] ?? 0))) ?></strong> ·
                Confirmados/realizados: <strong><?= e((string) ((int) ($monthlyGoalData['confirmed'] ?? 0))) ?></strong> ·
                Atingimento: <strong><?= e((string) ((float) ($monthlyGoalData['achievement_rate'] ?? 0.0))) ?>%</strong>
            </div>
        </div>

        <div class="panel">
            <h3 class="panel-title">Próximas Consultas <span class="panel-sub">(hoje e amanhã)</span></h3>
            <?php if (!empty($upcomingAppointments)): ?>
                <div class="upcoming-list">
                    <?php foreach ($upcomingAppointments as $ap): ?>
                        <div class="upcoming-item">
                            <div class="u-time"><?= e(date('d/m H:i', strtotime((string) $ap['data_hora']))) ?></div>
                            <div style="flex:1;min-width:0;">
                                <div class="u-name"><?= e((string) $ap['paciente_nome']) ?></div>
                                <div class="u-proc"><?= e((string) $ap['procedimento']) ?></div>
                            </div>
                            <span class="u-badge"><?= e(ucfirst((string) $ap['status'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="insight">Nenhuma consulta agendada para hoje ou amanhã.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid-2">
        <div class="panel">
            <h3 class="panel-title">Performance de Pacientes <span class="panel-sub">(retenção e produtividade)</span></h3>
            <div class="perf-kpi-row">
                <div class="perf-kpi">
                    <div class="label">Pacientes atendidos</div>
                    <div class="value"><?= e((string) ((int) ($patientPerformanceData['patients_with_appointments'] ?? 0))) ?></div>
                </div>
                <div class="perf-kpi">
                    <div class="label">Recorrentes</div>
                    <div class="value"><?= e((string) ((int) ($patientPerformanceData['returning_patients'] ?? 0))) ?></div>
                </div>
                <div class="perf-kpi">
                    <div class="label">Taxa de recorrência</div>
                    <div class="value"><?= e((string) ((float) ($patientPerformanceData['recurrence_rate'] ?? 0.0))) ?>%</div>
                </div>
                <div class="perf-kpi">
                    <div class="label">Média por paciente</div>
                    <div class="value"><?= e((string) ((float) ($patientPerformanceData['avg_appointments_per_patient'] ?? 0.0))) ?></div>
                </div>
            </div>
            <div class="goal-meta">
                Receita média por paciente no período: <strong>R$ <?= e(number_format((float) ($patientPerformanceData['avg_revenue_per_patient'] ?? 0.0), 2, ',', '.')) ?></strong>
            </div>
        </div>

        <div class="panel">
            <h3 class="panel-title">Pacientes em destaque <span class="panel-sub">(por volume no período)</span></h3>
            <?php $topPatients = $patientPerformanceData['top_patients'] ?? []; ?>
            <?php if (!empty($topPatients)): ?>
                <div class="top-list">
                    <?php foreach ($topPatients as $row): ?>
                        <div class="top-item">
                            <div class="name"><?= e((string) ($row['patient_name'] ?? 'Paciente')) ?></div>
                            <span class="top-pill"><?= e((string) ((int) ($row['appointments_count'] ?? 0))) ?> consultas</span>
                            <span class="top-pill"><?= e((string) ((int) ($row['confirmed_count'] ?? 0))) ?> conf.</span>
                            <span class="top-pill">R$ <?= e(number_format((float) ($row['paid_total'] ?? 0), 2, ',', '.')) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="insight">Ainda sem dados suficientes para ranking de pacientes neste período.</div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "Plus Jakarta Sans, Inter, Segoe UI, Roboto, Arial, sans-serif";
Chart.defaults.color = '#64748b';

(function () {
    var nodes = document.querySelectorAll('.kpi, .panel');
    if (!nodes.length) return;

    for (var i = 0; i < nodes.length; i += 1) {
        nodes[i].classList.add('reveal');
    }

    if (!('IntersectionObserver' in window)) {
        for (var j = 0; j < nodes.length; j += 1) {
            nodes[j].classList.add('is-visible');
        }
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    nodes.forEach(function (node) { observer.observe(node); });
}());

(function () {
    var el = document.getElementById('chartWeekly');
    if (!el) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: <?= $weekLabels ?>,
            datasets: [{
                data: <?= $weekTotals ?>,
                borderRadius: 9,
                borderSkipped: false,
                backgroundColor: 'rgba(37,99,235,.18)',
                borderColor: '#2563eb',
                borderWidth: 1.8,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#edf2f8' } }
            }
        }
    });
}());

(function () {
    var el = document.getElementById('chartStatus');
    if (!el) return;

    var labels = <?= $statusLabels ?>;
    var values = <?= $statusValues ?>;
    var colors = <?= $statusColors ?>;
    var insight = document.getElementById('statusInsight');

    var chart = new Chart(el, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 10,
            }]
        },
        options: {
            cutout: '64%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 11, padding: 10 } }
            },
            onClick: function (evt, elements) {
                if (!elements.length || !insight) return;
                var i = elements[0].index;
                var v = values[i] || 0;
                insight.innerHTML = '<strong>' + labels[i] + ':</strong> ' + v + ' registro(s) no período selecionado.';
            }
        }
    });

    if (insight && values.length) {
        var maxIndex = values.indexOf(Math.max.apply(null, values));
        if (maxIndex >= 0) {
            insight.innerHTML = '<strong>Destaque:</strong> ' + labels[maxIndex] + ' é o status mais recorrente agora.';
        }
    }
}());

(function () {
    var el = document.getElementById('chartMessages');
    if (!el) return;
    new Chart(el, {
        type: 'line',
        data: {
            labels: <?= $msgLabels ?>,
            datasets: [
                {
                    label: 'Enviadas',
                    data: <?= $msgSent ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,.08)',
                    fill: true,
                    pointRadius: 2.8,
                    tension: .34,
                },
                {
                    label: 'Recebidas',
                    data: <?= $msgReceived ?>,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,.08)',
                    fill: true,
                    pointRadius: 2.8,
                    tension: .34,
                }
            ]
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { boxWidth: 10, padding: 14 } } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#edf2f8' } }
            }
        }
    });
}());

(function () {
    var el = document.getElementById('chartDelivery');
    if (!el) return;
    new Chart(el, {
        type: 'doughnut',
        data: {
            labels: ['Entrega/Leitura', 'Restante'],
            datasets: [{
                data: <?= $deliveryGaugeValues ?>,
                backgroundColor: ['#2563eb', '#e8eef7'],
                borderWidth: 0,
            }]
        },
        options: {
            cutout: '76%',
            plugins: { legend: { display: false }, tooltip: { enabled: true } }
        }
    });
}());

(function () {
    var el = document.getElementById('chartLeadPipeline');
    if (!el) return;

    new Chart(el, {
        type: 'bar',
        data: {
            labels: <?= $leadPipelineLabels ?>,
            datasets: [{
                data: <?= $leadPipelineValues ?>,
                borderRadius: 10,
                borderSkipped: false,
                backgroundColor: ['rgba(37,99,235,.20)','rgba(22,163,74,.20)','rgba(14,165,233,.20)'],
                borderColor: ['#2563eb','#16a34a','#0ea5e9'],
                borderWidth: 1.8,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#edf2f8' } }
            }
        }
    });
}());
</script>


