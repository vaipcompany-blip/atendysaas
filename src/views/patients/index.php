<style>
.badge-lead{background:#fef9c3;color:#854d0e;border:1px solid #fde047}
.badge-ativo{background:#dcfce7;color:#166534;border:1px solid #86efac}
.badge-arquivado{background:#f1f5f9;color:#475569;border:1px solid #cbd5e1}
.badge-default{background:#eaf1ff;color:#1d4ed8;border:1px solid #93c5fd}
.status-badge{display:inline-flex;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700}
</style>
<h1 class="page-title">Pacientes</h1>

<?php if (!empty($message)): ?>
    <div class="alert"><?= e((string) $message) ?></div>
<?php endif; ?>

<div class="card">
    <h3 class="card-title">Novo paciente</h3>
    <form method="post" action="<?= e(base_url('route=patients')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="field">
            <label>Nome</label>
            <input type="text" name="nome" placeholder="Nome" required>
        </div>
        <div class="field">
            <label>WhatsApp</label>
            <input type="text" name="whatsapp" placeholder="WhatsApp (11 dígitos)" required>
        </div>
        <div class="field">
            <label>E-mail</label>
            <input type="email" name="email" placeholder="E-mail">
        </div>
        <div class="field">
            <label>CPF</label>
            <input type="text" name="cpf" placeholder="CPF" required>
        </div>
        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <button type="submit">Salvar</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="row" style="justify-content:space-between; align-items:center; margin-bottom:12px;">
        <form method="get" action="<?= e(base_url()) ?>" class="row" style="margin:0;">
            <input type="hidden" name="route" value="patients">
            <input type="text" name="search" value="<?= e((string) $search) ?>" placeholder="Buscar por nome ou WhatsApp">
            <button type="submit">Buscar</button>
        </form>

        <a href="<?= e(base_url('route=patients&action=export_csv&search=' . urlencode((string) $search))) ?>"
           class="btn-secondary"
           style="display:inline-flex;align-items:center;justify-content:center;height:40px;padding:0 12px;border-radius:10px;color:#fff;text-decoration:none;white-space:nowrap;">
            Exportar CSV
        </a>

        <a href="<?= e(base_url('route=patients&action=export_pdf&search=' . urlencode((string) $search))) ?>"
           target="_blank"
           class="btn-secondary"
           style="display:inline-flex;align-items:center;justify-content:center;height:40px;padding:0 12px;border-radius:10px;color:#fff;text-decoration:none;white-space:nowrap;">
            Exportar PDF
        </a>
    </div>

    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>WhatsApp</th>
                <th>E-mail</th>
                <th>CPF</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($patients as $patient): ?>
                <tr>
                    <td><?= e((string) $patient['nome']) ?></td>
                    <td><?= e((string) $patient['whatsapp']) ?></td>
                    <td><?= e((string) ($patient['email'] ?? '-')) ?></td>
                    <td><?= e((string) $patient['cpf']) ?></td>
                    <td>
                        <?php
                        $pStatus = (string) ($patient['status'] ?? 'ativo');
                        $badgeClass = match($pStatus) {
                            'lead'     => 'status-badge badge-lead',
                            'ativo'    => 'status-badge badge-ativo',
                            'arquivado'=> 'status-badge badge-arquivado',
                            default    => 'status-badge badge-default',
                        };
                        ?>
                        <span class="<?= e($badgeClass) ?>"><?= e(ucfirst($pStatus)) ?></span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a href="<?= e(base_url('route=patients&action=edit&patient_id=' . $patient['id'])) ?>"
                           class="btn-secondary"
                           style="display:inline-flex;align-items:center;height:32px;padding:0 10px;border-radius:8px;color:#fff;text-decoration:none;font-size:12px;font-weight:600;">Editar</a>
                        <form method="post" action="<?= e(base_url('route=patients')) ?>" class="inline" onsubmit="return confirm('Arquivar <?= e(addslashes((string) $patient['nome'])) ?>? O paciente ficará inativo mas seus dados serão preservados.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="archive">
                            <input type="hidden" name="patient_id" value="<?= e((string) $patient['id']) ?>">
                            <button type="submit" class="btn-danger" style="height:32px;padding:0 10px;font-size:12px;">Arquivar</button>
                        </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

