<h1 class="page-title">WhatsApp</h1>
<p class="muted">Módulo para envio/recebimento no modo simulado ou API em nuvem, com automações de confirmação e lembretes.</p>

<?php if (!empty($message)): ?>
    <div class="alert"><?= e((string) $message) ?></div>
<?php endif; ?>

<style>
.wa-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}

.wa-summary-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    padding: 10px 12px;
}

.wa-summary-label {
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: 5px;
}

.wa-summary-value {
    font-size: 22px;
    color: #1e293b;
    font-weight: 800;
    line-height: 1;
}

.wa-chart-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.wa-chart-card {
    min-height: 320px;
}

.wa-chart-wrap {
    position: relative;
    height: 240px;
    width: 100%;
}

a.btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 40px;
    padding: 0 12px;
    border-radius: 10px;
    border: 1px solid #334155;
    background: #334155;
    color: #ffffff !important;
    text-decoration: none;
    font-weight: 600;
}

a.btn-secondary:hover {
    background: #1f2937;
    border-color: #1f2937;
    color: #ffffff !important;
}

@media (max-width: 980px) {
    .wa-chart-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php $summaryData = $summary ?? []; ?>
<?php
    $trendRows = $dailyTrend ?? [];
    $trendLabels = json_encode(array_column($trendRows, 'day'), JSON_UNESCAPED_UNICODE);
    $trendOutbound = json_encode(array_column($trendRows, 'outbound'));
    $trendInbound = json_encode(array_column($trendRows, 'inbound'));
    $statusData = $statusBreakdown ?? [];
    $statusMap = [
        'queued' => 'Na fila',
        'sent' => 'Enviada',
        'delivered' => 'Entregue',
        'read' => 'Lida',
        'failed' => 'Falhou',
        'received' => 'Recebida',
    ];
    $statusChartHumanLabels = array_map(static function (string $key) use ($statusMap): string {
        return $statusMap[$key] ?? ucfirst($key);
    }, array_map('strval', array_keys($statusData)));
    $statusChartLabels = json_encode($statusChartHumanLabels, JSON_UNESCAPED_UNICODE);
    $statusChartValues = json_encode(array_values($statusData));
    $statusChartColors = json_encode([
        '#2563eb', '#16a34a', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#64748b', '#f97316'
    ]);
?>
<div class="card">
    <h3 class="card-title">Resumo do período filtrado</h3>
    <div class="wa-summary-grid">
        <div class="wa-summary-card">
            <div class="wa-summary-label">Mensagens totais</div>
            <div class="wa-summary-value"><?= e((string) ((int) ($summaryData['total'] ?? 0))) ?></div>
        </div>
        <div class="wa-summary-card">
            <div class="wa-summary-label">Saída</div>
            <div class="wa-summary-value"><?= e((string) ((int) ($summaryData['outbound_total'] ?? 0))) ?></div>
        </div>
        <div class="wa-summary-card">
            <div class="wa-summary-label">Entrada</div>
            <div class="wa-summary-value"><?= e((string) ((int) ($summaryData['inbound_total'] ?? 0))) ?></div>
        </div>
        <div class="wa-summary-card">
            <div class="wa-summary-label">Entrega/leitura</div>
            <div class="wa-summary-value"><?= e((string) ((float) ($summaryData['delivery_rate'] ?? 0.0))) ?>%</div>
        </div>
        <div class="wa-summary-card">
            <div class="wa-summary-label">Falhas</div>
            <div class="wa-summary-value"><?= e((string) ((int) ($summaryData['failed_total'] ?? 0))) ?></div>
        </div>
        <div class="wa-summary-card">
            <div class="wa-summary-label">Pacientes únicos</div>
            <div class="wa-summary-value"><?= e((string) ((int) ($summaryData['unique_patients'] ?? 0))) ?></div>
        </div>
    </div>
</div>

<div class="wa-chart-grid">
    <div class="card wa-chart-card">
        <h3 class="card-title">Tendência diária (entrada x saída)</h3>
        <?php if (!empty($trendRows)): ?>
            <div class="wa-chart-wrap"><canvas id="waTrendChart"></canvas></div>
        <?php else: ?>
            <p class="muted">Sem dados suficientes para montar tendência diária no filtro atual.</p>
        <?php endif; ?>
    </div>

    <div class="card wa-chart-card">
        <h3 class="card-title">Distribuição por status</h3>
        <?php if (!empty($statusData)): ?>
            <div class="wa-chart-wrap"><canvas id="waStatusChart"></canvas></div>
            <p id="waStatusInsight" class="muted" style="margin-top:8px;">
                <?= e((string) (($statusInsight['message'] ?? 'Sem dados de status no período filtrado.'))) ?>
            </p>
        <?php else: ?>
            <p id="waStatusInsight" class="muted">Sem dados de status para o filtro atual.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3 class="card-title">Executar automações</h3>
    <p class="muted">Confirmação 24h, lembretes 12h/2h e follow-up de faltas/cancelamentos/inatividade.</p>
    <form method="post" action="<?= e(base_url('route=whatsapp')) ?>" class="row">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="run_automations">
        <button type="submit">Rodar tudo</button>
    </form>
    <div class="row" style="margin-top:8px;">
        <form method="post" action="<?= e(base_url('route=whatsapp')) ?>" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_reminders">
            <button type="submit" class="btn-secondary">Rodar lembretes 12h/2h</button>
        </form>
        <form method="post" action="<?= e(base_url('route=whatsapp')) ?>" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_followups">
            <button type="submit" class="btn-secondary">Rodar follow-up</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    var el = document.getElementById('waTrendChart');
    if (!el || typeof Chart === 'undefined') {
        return;
    }

    new Chart(el, {
        type: 'line',
        data: {
            labels: <?= $trendLabels ?>,
            datasets: [
                {
                    label: 'Saída',
                    data: <?= $trendOutbound ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,.10)',
                    fill: true,
                    tension: .32,
                    pointRadius: 2.5,
                },
                {
                    label: 'Entrada',
                    data: <?= $trendInbound ?>,
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22,163,74,.10)',
                    fill: true,
                    tension: .32,
                    pointRadius: 2.5,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
}());

(function () {
    var el = document.getElementById('waStatusChart');
    if (!el || typeof Chart === 'undefined') {
        return;
    }

    var labels = <?= $statusChartLabels ?>;
    var values = <?= $statusChartValues ?>;
    var colors = <?= $statusChartColors ?>;

    new Chart(el, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderColor: '#ffffff',
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: { position: 'bottom' },
            }
        }
    });
}());
</script>

<div class="card">
    <h3 class="card-title">Enviar mensagem manual (simulada)</h3>
    <form method="post" action="<?= e(base_url('route=whatsapp')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send_manual">
        <div class="field">
            <label>Paciente</label>
            <select name="patient_id" required>
                <option value="">Selecione</option>
                <?php foreach ($patients as $patient): ?>
                    <option value="<?= e((string) $patient['id']) ?>"><?= e((string) $patient['nome']) ?> (<?= e((string) $patient['whatsapp']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Mensagem</label>
            <input type="text" name="text" placeholder="Ex: Olá, sua consulta está confirmada?" required>
        </div>
        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <button type="submit">Enviar</button>
        </div>
    </form>
</div>

<div class="card">
    <h3 class="card-title">Simular mensagem recebida do paciente</h3>
    <form method="post" action="<?= e(base_url('route=whatsapp')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="receive_simulated">
        <div class="field">
            <label>Paciente</label>
            <select name="patient_id" required>
                <option value="">Selecione</option>
                <?php foreach ($patients as $patient): ?>
                    <option value="<?= e((string) $patient['id']) ?>"><?= e((string) $patient['nome']) ?> (<?= e((string) $patient['whatsapp']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Mensagem recebida</label>
            <input type="text" name="text" placeholder="Ex: Sim, confirmo" required>
        </div>
        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <button type="submit" class="btn-secondary">Processar resposta</button>
        </div>
    </form>
</div>

<div class="card">
    <h3 class="card-title">Histórico recente de mensagens</h3>
    <?php
        $pg = $pagination ?? ['page' => 1, 'totalPages' => 1, 'total' => 0];
        $currentPage = (int) ($pg['page'] ?? 1);
        $totalPages = (int) ($pg['totalPages'] ?? 1);
        $totalRows = (int) ($pg['total'] ?? 0);

        $baseFilterQuery =
            'route=whatsapp'
            . '&patient_id=' . (int) ($filters['patient_id'] ?? 0)
            . '&direction=' . urlencode((string) ($filters['direction'] ?? ''))
            . '&status=' . urlencode((string) ($filters['status'] ?? ''))
            . '&date_from=' . urlencode((string) ($filters['date_from'] ?? ''))
            . '&date_to=' . urlencode((string) ($filters['date_to'] ?? ''))
            . '&q=' . urlencode((string) ($filters['q'] ?? ''))
            . '&sort_by=' . urlencode((string) ($filters['sort_by'] ?? 'timestamp'))
            . '&sort_dir=' . urlencode((string) ($filters['sort_dir'] ?? 'desc'));
    ?>
    <form method="get" action="<?= e(base_url()) ?>" class="form-grid" style="margin-bottom:12px;">
        <input type="hidden" name="route" value="whatsapp">

        <div class="field">
            <label>Paciente</label>
            <select name="patient_id">
                <option value="0">Todos</option>
                <?php foreach ($patients as $patient): ?>
                    <option value="<?= e((string) $patient['id']) ?>" <?= ((int) ($filters['patient_id'] ?? 0) === (int) $patient['id']) ? 'selected' : '' ?>>
                        <?= e((string) $patient['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label>Direção</label>
            <select name="direction">
                <option value="" <?= (($filters['direction'] ?? '') === '') ? 'selected' : '' ?>>Todas</option>
                <option value="outbound" <?= (($filters['direction'] ?? '') === 'outbound') ? 'selected' : '' ?>>Saída</option>
                <option value="inbound" <?= (($filters['direction'] ?? '') === 'inbound') ? 'selected' : '' ?>>Entrada</option>
            </select>
        </div>

        <div class="field">
            <label>Status</label>
            <select name="status">
                <?php $statusFilter = (string) ($filters['status'] ?? ''); ?>
                <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Todos</option>
                <option value="queued" <?= $statusFilter === 'queued' ? 'selected' : '' ?>>Na fila</option>
                <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Enviada</option>
                <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Entregue</option>
                <option value="read" <?= $statusFilter === 'read' ? 'selected' : '' ?>>Lida</option>
                <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Falhou</option>
                <option value="received" <?= $statusFilter === 'received' ? 'selected' : '' ?>>Recebida</option>
            </select>
        </div>

        <div class="field">
            <label>Data inicial</label>
            <input type="date" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
        </div>

        <div class="field">
            <label>Data final</label>
            <input type="date" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
        </div>

        <div class="field">
            <label>Ordenar por</label>
            <select name="sort_by">
                <?php $sortBy = (string) ($filters['sort_by'] ?? 'timestamp'); ?>
                <option value="timestamp" <?= $sortBy === 'timestamp' ? 'selected' : '' ?>>Data/Hora</option>
                <option value="patient" <?= $sortBy === 'patient' ? 'selected' : '' ?>>Paciente</option>
                <option value="direction" <?= $sortBy === 'direction' ? 'selected' : '' ?>>Direção</option>
                <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>Status</option>
            </select>
        </div>

        <div class="field">
            <label>Direção</label>
            <select name="sort_dir">
                <?php $sortDir = (string) ($filters['sort_dir'] ?? 'desc'); ?>
                <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Decrescente</option>
                <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Crescente</option>
            </select>
        </div>

        <div class="field" style="grid-column:1/-1;">
            <label>Busca por texto/paciente</label>
            <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Ex: confirmar, Maria, reagendar...">
        </div>

        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <div class="row">
                <a class="btn-secondary" href="<?= e(base_url('route=whatsapp')) ?>">Limpar</a>
                <button type="submit">Filtrar</button>
                <a class="btn-secondary" href="<?= e(base_url('route=whatsapp&action=export_csv&patient_id=' . (int) ($filters['patient_id'] ?? 0) . '&direction=' . urlencode((string) ($filters['direction'] ?? '')) . '&status=' . urlencode((string) ($filters['status'] ?? '')) . '&date_from=' . urlencode((string) ($filters['date_from'] ?? '')) . '&date_to=' . urlencode((string) ($filters['date_to'] ?? '')) . '&q=' . urlencode((string) ($filters['q'] ?? '')) . '&sort_by=' . urlencode((string) ($filters['sort_by'] ?? 'timestamp')) . '&sort_dir=' . urlencode((string) ($filters['sort_dir'] ?? 'desc')))) ?>">Exportar CSV</a>
            </div>
        </div>
    </form>

    <div class="row" style="justify-content:space-between; margin:4px 0 10px;">
        <span class="muted">Total filtrado: <?= e((string) $totalRows) ?> mensagem(ns)</span>
        <?php if ($totalPages > 1): ?>
            <div class="row">
                <?php if ($currentPage > 1): ?>
                    <a class="btn-secondary" href="<?= e(base_url($baseFilterQuery . '&page=' . ($currentPage - 1))) ?>">&larr; Anterior</a>
                <?php endif; ?>
                <span class="chip">Página <?= e((string) $currentPage) ?> de <?= e((string) $totalPages) ?></span>
                <?php if ($currentPage < $totalPages): ?>
                    <a class="btn-secondary" href="<?= e(base_url($baseFilterQuery . '&page=' . ($currentPage + 1))) ?>">Próxima &rarr;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Paciente</th>
                    <th>Direção</th>
                    <th>Mensagem</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                    <tr>
                        <td colspan="5" class="muted">Nenhuma mensagem encontrada com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td><?= e((string) $msg['timestamp']) ?></td>
                            <td><?= e((string) ($msg['paciente_nome'] ?? '-')) ?></td>
                            <td>
                                <span class="chip">
                                    <?= e((string) (($msg['direction'] ?? '') === 'outbound' ? 'Saída' : (($msg['direction'] ?? '') === 'inbound' ? 'Entrada' : (string) ($msg['direction'] ?? '-')))) ?>
                                </span>
                            </td>
                            <td><?= e((string) $msg['texto']) ?></td>
                            <td><?= e((string) ($statusMap[(string) ($msg['status'] ?? '')] ?? (string) ($msg['status'] ?? '-'))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

