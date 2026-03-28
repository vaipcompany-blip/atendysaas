<?php
/**
 * src/views/appointments/edit.php
 * Formulário de edição de consulta.
 * Variáveis esperadas: $appointment (array), $patients (array), $message (string|null)
 */
?>
<?php if (!empty($message)): ?>
    <div class="alert"><?= e((string) $message) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
    <a href="<?= e(base_url('route=appointments')) ?>" class="btn-secondary" style="display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 14px;border-radius:10px;color:#fff;text-decoration:none;font-size:13px;font-weight:600;">&larr; Voltar</a>
    <h1 class="page-title" style="margin:0;">Editar Consulta</h1>
</div>
<p class="muted" style="margin-bottom:18px;">Altere os dados da consulta. A data não pode ser retroativa.</p>

<div class="card">
    <form method="post" action="<?= e(base_url('route=appointments')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="appointment_id" value="<?= e((string) $appointment['id']) ?>">

        <div class="form-grid">
            <div class="field">
                <label>Paciente *</label>
                <select name="patient_id" required>
                    <option value="">Selecione o paciente</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= e((string) $p['id']) ?>" <?= (int) $p['id'] === (int) $appointment['patient_id'] ? 'selected' : '' ?>>
                            <?= e((string) $p['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Data e hora *</label>
                <input type="datetime-local"
                       name="data_hora"
                       value="<?= e(date('Y-m-d\TH:i', strtotime((string) $appointment['data_hora']))) ?>"
                       required>
            </div>

            <div class="field">
                <label>Procedimento *</label>
                <input type="text" name="procedimento" value="<?= e((string) $appointment['procedimento']) ?>" required maxlength="150" placeholder="Ex: Limpeza, Extração...">
            </div>

            <div class="field">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['agendada','confirmada','realizada','cancelada','faltou','reagendada'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $appointment['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field" style="grid-column:1/-1;">
                <label>Notas internas</label>
                <textarea name="notas" rows="3" placeholder="Observações sobre a consulta..." style="resize:vertical;"><?= e((string) ($appointment['notas'] ?? '')) ?></textarea>
            </div>

            <div class="field" style="justify-content:flex-end;align-self:flex-end;">
                <button type="submit" style="height:40px;padding:0 22px;">Salvar alterações</button>
            </div>
        </div>
    </form>
</div>

