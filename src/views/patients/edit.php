<?php
/**
 * src/views/patients/edit.php
 * Formulário de edição de paciente.
 * Variáveis esperadas: $patient (array), $message (string|null)
 */
?>
<?php if (!empty($message)): ?>
    <div class="alert"><?= e((string) $message) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
    <a href="<?= e(base_url('route=patients')) ?>" class="btn-secondary" style="display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 14px;border-radius:10px;color:#fff;text-decoration:none;font-size:13px;font-weight:600;">Voltar</a>
    <h1 class="page-title" style="margin:0;">Editar Paciente</h1>
</div>
<p class="muted" style="margin-bottom:18px;">Atualize os dados do paciente. O CPF não pode ser alterado.</p>

<div class="card">
    <form method="post" action="<?= e(base_url('route=patients')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="patient_id" value="<?= e((string) $patient['id']) ?>">

        <div class="form-grid">
            <div class="field">
                <label>Nome completo *</label>
                <input type="text" name="nome" value="<?= e((string) $patient['nome']) ?>" required maxlength="150" placeholder="Nome do paciente">
            </div>

            <div class="field">
                <label>WhatsApp (11 dígitos com DDD) *</label>
                <input type="text" name="whatsapp" value="<?= e((string) $patient['whatsapp']) ?>" required maxlength="20" placeholder="Ex: 11999998888">
            </div>

            <div class="field">
                <label>E-mail</label>
                <input type="email" name="email" value="<?= e((string) ($patient['email'] ?? '')) ?>" maxlength="150" placeholder="email@exemplo.com">
            </div>

            <div class="field">
                <label>CPF <span class="muted">(não editável)</span></label>
                <input type="text" value="<?= e((string) $patient['cpf']) ?>" disabled style="background:#f8fafc;color:#94a3b8;cursor:not-allowed;">
            </div>

            <div class="field">
                <label>Telefone fixo</label>
                <input type="text" name="telefone" value="<?= e((string) ($patient['telefone'] ?? '')) ?>" maxlength="20" placeholder="Ex: 1133334444">
            </div>

            <div class="field">
                <label>Data de nascimento</label>
                <input type="date" name="data_nascimento" value="<?= e((string) ($patient['data_nascimento'] ?? '')) ?>">
            </div>

            <div class="field" style="grid-column:1/-1;">
                <label>Endereço</label>
                <input type="text" name="endereco" value="<?= e((string) ($patient['endereco'] ?? '')) ?>" maxlength="255" placeholder="Rua, número, bairro, cidade">
            </div>

            <div class="field">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['ativo' => 'Ativo', 'lead' => 'Lead', 'arquivado' => 'Arquivado'] as $val => $label): ?>
                        <option value="<?= e($val) ?>" <?= $patient['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field" style="justify-content:flex-end;align-self:flex-end;">
                <button type="submit" style="height:40px;padding:0 22px;">Salvar alterações</button>
            </div>
        </div>
    </form>
</div>

