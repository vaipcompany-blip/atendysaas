<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório de Consultas - <?= e((string) ($user['nome_consultorio'] ?? 'Atendy')) ?></title>
    <style>
        body{font-family:Inter,Segoe UI,Arial,sans-serif;color:#0f172a;margin:24px}
        h1{margin:0 0 4px;font-size:24px}
        .muted{color:#64748b;font-size:12px}
        .top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:16px}
        .actions{display:flex;gap:8px}
        .btn{height:34px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;cursor:pointer;font-size:12px}
        .btn-primary{background:#2563eb;color:#fff;border-color:#2563eb}
        .meta{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px;margin-bottom:12px}
        table{width:100%;border-collapse:collapse}
        th,td{border:1px solid #e2e8f0;padding:8px 10px;font-size:12px;text-align:left}
        th{background:#f1f5f9}
        tfoot td{font-weight:700;background:#f8fafc}
        @media print {
            body{margin:10mm}
            .actions{display:none}
            .meta{break-inside:avoid}
            tr{break-inside:avoid}
        }
    </style>
</head>
<body>
    <div class="top">
        <div>
            <h1>Relatório de Consultas</h1>
            <div class="muted"><?= e((string) ($user['nome_consultorio'] ?? 'Clínica')) ?></div>
        </div>
        <div class="actions">
            <button class="btn btn-primary" onclick="window.print()">Imprimir / Salvar PDF</button>
            <button class="btn" onclick="window.close()">Fechar</button>
        </div>
    </div>

    <div class="meta">
        <div><strong>Gerado em:</strong> <?= e((string) $generatedAt) ?></div>
        <div><strong>Período:</strong> <?= e((string) $periodLabel) ?> (<?= e($rangeStart->format('d/m/Y')) ?> até <?= e($rangeEnd->format('d/m/Y')) ?>)</div>
        <div><strong>Total de registros:</strong> <?= e((string) count($appointments)) ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Paciente</th>
                <th>Data/Hora</th>
                <th>Status</th>
                <th>Procedimento</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="4" style="text-align:center;color:#64748b;">Nenhuma consulta encontrada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?= e((string) ($appointment['paciente_nome'] ?? '')) ?></td>
                        <td><?= isset($appointment['data_hora']) ? e(date('d/m/Y H:i', strtotime((string) $appointment['data_hora']))) : '' ?></td>
                        <td><?= e(ucfirst((string) ($appointment['status'] ?? ''))) ?></td>
                        <td><?= e((string) ($appointment['procedimento'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Total: <?= e((string) count($appointments)) ?> consulta(s)</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>


