<?php
// Helpers locais
$formaLabel = function(string $f): string {
    return [
        'dinheiro'       => 'Dinheiro',
        'pix'            => 'PIX',
        'cartao_credito' => 'Cartão Crédito',
        'cartao_debito'  => 'Cartão Débito',
        'convenio'       => 'Convênio',
        'outro'          => 'Outro',
    ][$f] ?? ucfirst($f);
};
$brl = fn(float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
$baseQ = http_build_query(array_filter([
    'route'          => 'financeiro',
    'periodo'        => $periodo,
    'data_inicio'    => $periodo === 'personalizado' ? $dataInicio : '',
    'data_fim'       => $periodo === 'personalizado' ? $dataFim    : '',
    'forma_pagamento'=> $formaFiltro,
    'status_pgto'    => $statusFiltro,
]));
?>

<h1 class="page-title">Financeiro</h1>
<p class="muted">Receita, pagamentos pendentes e evolução financeira do consultório.</p>

<!-- �"?�"? Filtros �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
<div class="card" style="margin-bottom:18px;">
    <form method="get" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <input type="hidden" name="route" value="financeiro">

        <div class="field" style="margin:0;min-width:160px;">
            <label style="font-size:12px;">Período</label>
            <select name="periodo" onchange="this.form.submit()" style="padding:7px 10px;border-radius:8px;border:1px solid var(--line);font-size:13px;">
                <option value="mes_atual"    <?= $periodo==='mes_atual'    ?'selected':'' ?>>Mês atual</option>
                <option value="mes_anterior" <?= $periodo==='mes_anterior' ?'selected':'' ?>>Mês anterior</option>
                <option value="trimestre"    <?= $periodo==='trimestre'    ?'selected':'' ?>>Últimos 3 meses</option>
                <option value="semestre"     <?= $periodo==='semestre'     ?'selected':'' ?>>Últimos 6 meses</option>
                <option value="ano"          <?= $periodo==='ano'          ?'selected':'' ?>>Este ano</option>
                <option value="personalizado"<?= $periodo==='personalizado'?'selected':'' ?>>Personalizado</option>
            </select>
        </div>

        <?php if ($periodo === 'personalizado'): ?>
        <div class="field" style="margin:0;">
            <label style="font-size:12px;">De</label>
            <input type="date" name="data_inicio" value="<?= e($dataInicio) ?>" style="padding:7px 10px;border-radius:8px;border:1px solid var(--line);font-size:13px;">
        </div>
        <div class="field" style="margin:0;">
            <label style="font-size:12px;">Até</label>
            <input type="date" name="data_fim" value="<?= e($dataFim) ?>" style="padding:7px 10px;border-radius:8px;border:1px solid var(--line);font-size:13px;">
        </div>
        <?php endif; ?>

        <div class="field" style="margin:0;min-width:140px;">
            <label style="font-size:12px;">Forma de pagamento</label>
            <select name="forma_pagamento" style="padding:7px 10px;border-radius:8px;border:1px solid var(--line);font-size:13px;">
                <option value="">Todas</option>
                <?php foreach (['dinheiro','pix','cartao_credito','cartao_debito','convenio','outro'] as $f): ?>
                    <option value="<?= e($f) ?>" <?= $formaFiltro===$f?'selected':'' ?>><?= e($formaLabel($f)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field" style="margin:0;min-width:130px;">
            <label style="font-size:12px;">Status</label>
            <select name="status_pgto" style="padding:7px 10px;border-radius:8px;border:1px solid var(--line);font-size:13px;">
                <option value="">Todos</option>
                <option value="pago"     <?= $statusFiltro==='pago'    ?'selected':'' ?>>Pago</option>
                <option value="pendente" <?= $statusFiltro==='pendente'?'selected':'' ?>>Pendente</option>
            </select>
        </div>

        <button type="submit" style="padding:7px 18px;border-radius:8px;background:var(--primary);color:#fff;border:none;font-size:13px;cursor:pointer;">Filtrar</button>
        <a href="<?= e(base_url('route=financeiro')) ?>" style="padding:7px 14px;border-radius:8px;border:1px solid var(--line);font-size:13px;text-decoration:none;color:var(--muted);">Limpar</a>
    </form>
</div>

<!-- �"?�"? KPI Cards �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
<div id="fin-kpi-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;margin-bottom:22px;">
    <div class="card" style="padding:16px 18px;border-left:4px solid #16a34a;">
        <div class="muted" style="font-size:12px;margin-bottom:4px;">Receita recebida</div>
        <div style="font-size:22px;font-weight:700;color:#16a34a;"><?= e($brl($kpis['receitaPaga'])) ?></div>
        <div class="muted" style="font-size:11px;"><?= e((string)$kpis['totalPagas']) ?> consultas pagas</div>
    </div>
    <div class="card" style="padding:16px 18px;border-left:4px solid #f59e0b;">
        <div class="muted" style="font-size:12px;margin-bottom:4px;">A receber (pendente)</div>
        <div style="font-size:22px;font-weight:700;color:#f59e0b;"><?= e($brl($kpis['receitaPendente'])) ?></div>
        <div class="muted" style="font-size:11px;"><?= e((string)$kpis['totalPendentes']) ?> consultas pendentes</div>
    </div>
    <div class="card" style="padding:16px 18px;border-left:4px solid #2563eb;">
        <div class="muted" style="font-size:12px;margin-bottom:4px;">Ticket médio</div>
        <div style="font-size:22px;font-weight:700;color:#2563eb;"><?= e($brl($kpis['ticketMedio'])) ?></div>
        <div class="muted" style="font-size:11px;">por consulta paga</div>
    </div>
    <div class="card" style="padding:16px 18px;border-left:4px solid #7c3aed;">
        <div class="muted" style="font-size:12px;margin-bottom:4px;">Taxa de recebimento</div>
        <div style="font-size:22px;font-weight:700;color:#7c3aed;"><?= e((string)$kpis['taxaRecebimento']) ?>%</div>
        <div class="muted" style="font-size:11px;"><?= e((string)$kpis['totalConsultas']) ?> consultas no período</div>
    </div>
</div>

<!-- �"?�"? Gráficos: evolução + por forma �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:22px;">

    <div class="card" style="padding:18px 20px;">
        <h3 class="card-title" style="margin-bottom:14px;">Evolução mensal (12 meses)</h3>
        <canvas id="finEvolucaoChart" height="100"></canvas>
    </div>

    <div class="card" style="padding:18px 20px;">
        <h3 class="card-title" style="margin-bottom:14px;">Por forma de pagamento</h3>
        <?php if (empty($receitaPorForma)): ?>
            <p class="muted" style="font-size:13px;">Nenhum pagamento registrado no período.</p>
        <?php else: ?>
            <canvas id="finFormaChart" height="180"></canvas>
            <div id="finFormaLegend" style="margin-top:12px;font-size:12px;display:flex;flex-direction:column;gap:5px;"></div>
        <?php endif; ?>
    </div>

</div>

<!-- �"?�"? Receita por procedimento �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
<?php if (!empty($receitaPorProcedimento)): ?>
<div class="card" style="padding:18px 20px;margin-bottom:22px;">
    <h3 class="card-title" style="margin-bottom:14px;">Receita por procedimento</h3>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="border-bottom:2px solid var(--line);">
                    <th style="text-align:left;padding:8px 10px;font-weight:600;">Procedimento</th>
                    <th style="text-align:center;padding:8px 10px;font-weight:600;">Qtd</th>
                    <th style="text-align:right;padding:8px 10px;font-weight:600;">Receita</th>
                    <th style="text-align:right;padding:8px 10px;font-weight:600;">Ticket médio</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receitaPorProcedimento as $i => $row): ?>
                <tr style="border-bottom:1px solid var(--line);<?= $i % 2 === 1 ? 'background:#f8fafc;' : '' ?>">
                    <td style="padding:8px 10px;"><?= e((string)$row['procedimento']) ?></td>
                    <td style="padding:8px 10px;text-align:center;"><?= e((string)$row['qtd']) ?></td>
                    <td style="padding:8px 10px;text-align:right;font-weight:600;color:#16a34a;"><?= e($brl((float)$row['receita'])) ?></td>
                    <td style="padding:8px 10px;text-align:right;color:#2563eb;"><?= e($brl((float)$row['ticket'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- �"?�"? Pendentes de pagamento �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
<?php if (!empty($pendentes)): ?>
<div class="card" style="padding:18px 20px;margin-bottom:22px;" id="fin-pendentes">
    <h3 class="card-title" style="margin-bottom:14px;">Pendentes de pagamento <span style="background:#fef3c7;color:#92400e;font-size:12px;padding:2px 8px;border-radius:20px;margin-left:6px;"><?= count($pendentes) ?></span></h3>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="border-bottom:2px solid var(--line);">
                    <th style="text-align:left;padding:7px 10px;">Paciente</th>
                    <th style="text-align:left;padding:7px 10px;">Procedimento</th>
                    <th style="text-align:left;padding:7px 10px;">Data</th>
                    <th style="text-align:right;padding:7px 10px;">Valor</th>
                    <th style="text-align:center;padding:7px 10px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendentes as $p): ?>
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:7px 10px;"><?= e((string)$p['paciente_nome']) ?></td>
                    <td style="padding:7px 10px;"><?= e((string)$p['procedimento']) ?></td>
                    <td style="padding:7px 10px;color:var(--muted);"><?= e(date('d/m/Y', strtotime((string)$p['data_hora']))) ?></td>
                    <td style="padding:7px 10px;text-align:right;font-weight:600;color:#f59e0b;"><?= e($brl((float)$p['valor_cobrado'])) ?></td>
                    <td style="padding:7px 10px;text-align:center;">
                        <button type="button"
                            onclick="openPgtoModal(<?= (int)$p['id'] ?>, '<?= e(addslashes((string)$p['paciente_nome'])) ?>', <?= (float)$p['valor_cobrado'] ?>, '<?= e((string)($p['forma_pagamento'] ?? '')) ?>')"
                            style="padding:4px 12px;border-radius:7px;background:#16a34a;color:#fff;border:none;font-size:12px;cursor:pointer;">
                            Registrar pagamento
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- �"?�"? Tabela de lançamentos �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
<div class="card" style="padding:18px 20px;" id="fin-lancamentos">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
        <h3 class="card-title" style="margin:0;">Lançamentos <span style="color:var(--muted);font-weight:500;font-size:14px;"><?= e($periodoLabel) ?> (<?= e($dataInicio) ?> até <?= e($dataFim) ?>)</span></h3>
        <span class="muted" style="font-size:12px;"><?= e((string)$totalRows) ?> registros</span>
    </div>
    <?php if (empty($lancamentos)): ?>
        <p class="muted">Nenhum lançamento encontrado para o período e filtros selecionados.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="border-bottom:2px solid var(--line);">
                    <th style="text-align:left;padding:7px 10px;">Data</th>
                    <th style="text-align:left;padding:7px 10px;">Paciente</th>
                    <th style="text-align:left;padding:7px 10px;">Procedimento</th>
                    <th style="text-align:right;padding:7px 10px;">Valor</th>
                    <th style="text-align:center;padding:7px 10px;">Forma</th>
                    <th style="text-align:center;padding:7px 10px;">Status</th>
                    <th style="text-align:center;padding:7px 10px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lancamentos as $i => $l): ?>
                <?php
                    $isPago   = (int)$l['pago'] === 1;
                    $hasValor = $l['valor_cobrado'] !== null;
                ?>
                <tr style="border-bottom:1px solid var(--line);<?= $i % 2 === 1 ? 'background:#f8fafc;' : '' ?>">
                    <td style="padding:7px 10px;color:var(--muted);"><?= e(date('d/m/Y', strtotime((string)$l['data_hora']))) ?></td>
                    <td style="padding:7px 10px;"><?= e((string)$l['paciente_nome']) ?></td>
                    <td style="padding:7px 10px;"><?= e((string)$l['procedimento']) ?></td>
                    <td style="padding:7px 10px;text-align:right;font-weight:600;color:<?= $isPago ? '#16a34a' : ($hasValor ? '#f59e0b' : 'var(--muted)') ?>;">
                        <?= $hasValor ? e($brl((float)$l['valor_cobrado'])) : '<span style="color:var(--muted)">-</span>' ?>
                    </td>
                    <td style="padding:7px 10px;text-align:center;">
                        <?= $l['forma_pagamento'] ? e($formaLabel((string)$l['forma_pagamento'])) : '<span style="color:var(--muted)">-</span>' ?>
                    </td>
                    <td style="padding:7px 10px;text-align:center;">
                        <?php if ($isPago): ?>
                            <span style="background:#dcfce7;color:#166534;padding:2px 9px;border-radius:20px;font-size:11px;">Pago</span>
                        <?php elseif ($hasValor): ?>
                            <span style="background:#fef3c7;color:#92400e;padding:2px 9px;border-radius:20px;font-size:11px;">⏳ Pendente</span>
                        <?php else: ?>
                            <span style="background:#f1f5f9;color:#64748b;padding:2px 9px;border-radius:20px;font-size:11px;">Sem valor</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:7px 10px;text-align:center;">
                        <button type="button"
                            onclick="openPgtoModal(<?= (int)$l['id'] ?>, '<?= e(addslashes((string)$l['paciente_nome'])) ?>', <?= (float)($l['valor_cobrado'] ?? 0) ?>, '<?= e((string)($l['forma_pagamento'] ?? '')) ?>', <?= $isPago ? 1 : 0 ?>, '<?= e((string)($l['data_pagamento'] ?? '')) ?>')"
                            style="padding:3px 10px;border-radius:7px;background:var(--primary-soft);color:var(--primary);border:1px solid #bfdbfe;font-size:11px;cursor:pointer;">
                            Editar
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;align-items:center;gap:8px;margin-top:16px;font-size:13px;">
        <?php if ($page > 1): ?>
            <a href="<?= e(base_url($baseQ . '&page=' . ($page - 1))) ?>"
               style="padding:5px 14px;border-radius:8px;border:1px solid var(--line);text-decoration:none;color:var(--text);">&larr; Anterior</a>
        <?php endif; ?>
        <span class="muted">Página <?= e((string)$page) ?> de <?= e((string)$totalPages) ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="<?= e(base_url($baseQ . '&page=' . ($page + 1))) ?>"
               style="padding:5px 14px;border-radius:8px;border:1px solid var(--line);text-decoration:none;color:var(--text);">Próxima &rarr;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- �"?�"? Modal de pagamento �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
<div id="pgto-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9999;padding:16px;">
    <div style="max-width:440px;margin:60px auto;background:#fff;border-radius:14px;box-shadow:0 18px 40px rgba(15,23,42,.25);overflow:hidden;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--line);background:#f8fafc;">
            <strong id="pgto-modal-title">Registrar pagamento</strong>
            <button type="button" onclick="closePgtoModal()" style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--muted);">x</button>
        </div>
        <form id="pgto-form" style="padding:18px;display:flex;flex-direction:column;gap:13px;">
            <input type="hidden" id="pgto-appt-id" name="appointment_id">
            <input type="hidden" id="pgto-csrf" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" value="update_pagamento">
            <div class="field" style="margin:0;">
                <label>Valor cobrado (R$)</label>
                <input type="number" id="pgto-valor" name="valor_cobrado" step="0.01" min="0" placeholder="0,00">
            </div>
            <div class="field" style="margin:0;">
                <label>Forma de pagamento</label>
                <select id="pgto-forma" name="forma_pagamento">
                    <option value="">- selecione -</option>
                    <option value="dinheiro">Dinheiro</option>
                    <option value="pix">PIX</option>
                    <option value="cartao_credito">Cartão Crédito</option>
                    <option value="cartao_debito">Cartão Débito</option>
                    <option value="convenio">Convênio</option>
                    <option value="outro">Outro</option>
                </select>
            </div>
            <div class="field" style="margin:0;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" id="pgto-pago" name="pago" value="1" onchange="toggleDataPgto(this)">
                    Marcar como pago
                </label>
            </div>
            <div class="field" id="pgto-data-field" style="margin:0;display:none;">
                <label>Data do pagamento</label>
                <input type="date" id="pgto-data" name="data_pagamento">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px;">
                <button type="button" onclick="closePgtoModal()" class="btn-secondary">Cancelar</button>
                <button type="button" onclick="submitPgto()" style="padding:8px 20px;border-radius:9px;background:var(--primary);color:#fff;border:none;cursor:pointer;font-weight:600;">Salvar</button>
            </div>
        </form>
        <div id="pgto-feedback" style="padding:0 18px 14px;font-size:13px;display:none;"></div>
    </div>
</div>

<!-- �"?�"? Scripts �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
// �"?�"? Evolução mensal �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
(function () {
    var data = <?= json_encode(array_values($evolucaoMensal), JSON_UNESCAPED_UNICODE) ?>;
    if (!data.length) return;
    var labels   = data.map(function(r){ return r.mes; });
    var receita  = data.map(function(r){ return parseFloat(r.receita); });
    var pendente = data.map(function(r){ return parseFloat(r.pendente); });
    var ctx = document.getElementById('finEvolucaoChart');
    if (!ctx) return;
    new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Recebido (R$)', data: receita,  backgroundColor: '#16a34a', borderRadius: 5 },
                { label: 'Pendente (R$)', data: pendente, backgroundColor: '#f59e0b', borderRadius: 5 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { stacked: false, grid: { display: false } },
                y: { stacked: false, ticks: { callback: function(v){ return 'R$' + v.toLocaleString('pt-BR'); } } }
            }
        }
    });
}());

// �"?�"? Por forma de pagamento �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
(function () {
    var data = <?= json_encode(array_values($receitaPorForma), JSON_UNESCAPED_UNICODE) ?>;
    var ctx = document.getElementById('finFormaChart');
    if (!ctx || !data.length) return;
    var colors = ['#2563eb','#16a34a','#f59e0b','#7c3aed','#dc2626','#0891b2'];
    var labels = data.map(function(r){ return r.label; });
    var totais  = data.map(function(r){ return parseFloat(r.total); });
    new Chart(ctx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{ data: totais, backgroundColor: colors.slice(0, data.length), borderWidth: 2 }]
        },
        options: {
            cutout: '62%',
            plugins: { legend: { display: false } }
        }
    });
    // legenda customizada
    var leg = document.getElementById('finFormaLegend');
    if (leg) {
        var total = totais.reduce(function(a,b){ return a+b; }, 0);
        data.forEach(function(r, i) {
            var pct = total > 0 ? ((parseFloat(r.total)/total)*100).toFixed(1) : '0';
            var line = document.createElement('div');
            line.style.cssText = 'display:flex;justify-content:space-between;';
            line.innerHTML = '<span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:' + colors[i] + ';margin-right:6px;"></span>' + r.label + '</span>'
                + '<span style="font-weight:600;">R$ ' + parseFloat(r.total).toLocaleString('pt-BR',{minimumFractionDigits:2}) + ' <span style="color:#94a3b8;">(' + pct + '%)</span></span>';
            leg.appendChild(line);
        });
    }
}());

// �"?�"? Modal de pagamento �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
function openPgtoModal(id, nome, valor, forma, pago, dataPgto) {
    pago = pago || 0; dataPgto = dataPgto || '';
    document.getElementById('pgto-appt-id').value = id;
    document.getElementById('pgto-modal-title').textContent = 'Pagamento - ' + nome;
    document.getElementById('pgto-valor').value  = valor > 0 ? valor : '';
    document.getElementById('pgto-forma').value  = forma || '';
    document.getElementById('pgto-pago').checked = pago == 1;
    document.getElementById('pgto-data').value   = dataPgto;
    document.getElementById('pgto-data-field').style.display = pago == 1 ? '' : 'none';
    document.getElementById('pgto-feedback').style.display = 'none';
    document.getElementById('pgto-modal').style.display = '';
}

function closePgtoModal() {
    document.getElementById('pgto-modal').style.display = 'none';
}

function toggleDataPgto(cb) {
    document.getElementById('pgto-data-field').style.display = cb.checked ? '' : 'none';
}

function submitPgto() {
    var form  = document.getElementById('pgto-form');
    var data  = new FormData(form);
    var btn   = form.querySelector('button[onclick="submitPgto()"]');
    var fb    = document.getElementById('pgto-feedback');

    // pago checkbox
    if (!document.getElementById('pgto-pago').checked) {
        data.set('pago', '0');
    }

    btn.disabled = true; btn.textContent = 'Salvando...';
    fb.style.display = 'none';

    fetch('<?= e(base_url('route=financeiro')) ?>', {
        method: 'POST',
        body: data
    })
    .then(function(r){ return r.json(); })
    .then(function(res) {
        fb.style.cssText = 'padding:0 18px 14px;font-size:13px;color:' + (res.success ? '#16a34a' : '#dc2626') + ';display:block;';
        fb.textContent = res.message;
        if (res.success) {
            setTimeout(function(){ window.location.reload(); }, 900);
        } else {
            btn.disabled = false; btn.textContent = 'Salvar';
        }
    })
    .catch(function() {
        fb.style.cssText = 'padding:0 18px 14px;font-size:13px;color:#dc2626;display:block;';
        fb.textContent = 'Erro de comunicação.';
        btn.disabled = false; btn.textContent = 'Salvar';
    });
}

// Fechar modal ao clicar fora
document.getElementById('pgto-modal').addEventListener('click', function(e){
    if (e.target === this) closePgtoModal();
});
</script>

