<?php
$summary = $summary ?? [];
$statusRows = $statusRows ?? [];
$procedureRows = $procedureRows ?? [];
$dailyRows = $dailyRows ?? [];
?>

<style>
@media print {
    .no-print { display:none !important; }
    .card { box-shadow:none !important; }
}
.rep-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.rep-title{margin:0;font-size:26px;color:#0f172a}
.rep-sub{margin:4px 0 0;color:#475569;font-size:13px}
.rep-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.rep-box{border:1px solid #dbe3ef;border-radius:10px;padding:10px;background:#fff}
.rep-box .k{font-size:12px;color:#64748b}
.rep-box .v{font-size:22px;font-weight:800;color:#1e3a8a}
@media(max-width:900px){.rep-grid{grid-template-columns:1fr}}
</style>

<div class="card">
    <div class="rep-head">
        <div>
            <h1 class="rep-title">Relatório Gerencial</h1>
            <p class="rep-sub"><?= e((string) ($user['nome_consultorio'] ?? 'Clínica')) ?> · Período <?= e($from->format('d/m/Y')) ?> até <?= e($to->format('d/m/Y')) ?></p>
            <p class="rep-sub">Gerado em <?= e((string) $generatedAt) ?></p>
        </div>
        <button class="no-print" onclick="window.print()">Imprimir / Salvar PDF</button>
    </div>

    <div class="rep-grid" style="margin-bottom:12px;">
        <div class="rep-box"><div class="k">Consultas</div><div class="v"><?= (int) ($summary['appointments_total'] ?? 0) ?></div></div>
        <div class="rep-box"><div class="k">Taxa de confirmação</div><div class="v"><?= e((string) ($summary['confirmation_rate'] ?? 0)) ?>%</div></div>
        <div class="rep-box"><div class="k">Receita recebida</div><div class="v">R$ <?= e(number_format((float) ($summary['paid_revenue'] ?? 0), 2, ',', '.')) ?></div></div>
    </div>

    <h3 class="card-title">Status</h3>
    <div class="table-wrap" style="margin-bottom:12px;">
        <table>
            <thead><tr><th>Status</th><th>Total</th></tr></thead>
            <tbody>
                <?php foreach ($statusRows as $row): ?>
                    <tr><td><?= e(ucfirst((string) $row['status'])) ?></td><td><?= (int) $row['total'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3 class="card-title">Procedimentos</h3>
    <div class="table-wrap" style="margin-bottom:12px;">
        <table>
            <thead><tr><th>Procedimento</th><th>Total</th><th>Receita</th></tr></thead>
            <tbody>
                <?php foreach ($procedureRows as $row): ?>
                    <tr>
                        <td><?= e((string) $row['procedimento']) ?></td>
                        <td><?= (int) $row['total'] ?></td>
                        <td>R$ <?= e(number_format((float) $row['paid_revenue'], 2, ',', '.')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3 class="card-title">Evolução diária</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Dia</th><th>Consultas</th><th>Confirmadas/Realizadas</th><th>Receita</th></tr></thead>
            <tbody>
                <?php foreach ($dailyRows as $row): ?>
                    <tr>
                        <td><?= e((string) $row['day']) ?></td>
                        <td><?= (int) $row['appointments_total'] ?></td>
                        <td><?= (int) $row['confirmed_total'] ?></td>
                        <td>R$ <?= e(number_format((float) $row['paid_revenue'], 2, ',', '.')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

