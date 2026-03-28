<?php
$invite = $invite ?? [];
$token = $token ?? '';
$error = $error ?? null;
$message = $message ?? null;
?>

<div class="card" style="max-width:560px;margin:40px auto;">
    <h1 class="page-title" style="font-size:24px;">Aceitar convite da equipe</h1>
    <p class="muted" style="margin-bottom:14px;">
        Você foi convidado para a clínica <strong><?= e((string) ($invite['nome_consultorio'] ?? 'Atendy')) ?></strong>.
    </p>
    <?php if (!empty($invite['expires_at'])): ?>
        <p class="muted" style="margin-top:-6px;margin-bottom:14px;">
            Este convite expira em <?= e(date('d/m/Y H:i', strtotime((string) $invite['expires_at']))) ?>.
        </p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
            <?= e((string) $error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="alert">
            <?= e((string) $message) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('route=team_accept')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e((string) $token) ?>">

        <div class="field" style="grid-column:1 / -1;">
            <label>E-mail</label>
            <input type="email" value="<?= e((string) ($invite['email'] ?? '')) ?>" disabled>
        </div>

        <div class="field" style="grid-column:1 / -1;">
            <label>Nome completo</label>
            <input type="text" name="nome_completo" value="<?= e((string) ($invite['nome_completo'] ?? '')) ?>" required>
        </div>

        <div class="field" style="grid-column:1 / -1;">
            <label>Senha</label>
            <input type="password" name="password" minlength="8" placeholder="Mínimo 8 caracteres" required>
        </div>

        <div class="field" style="grid-column:1 / -1;">
            <label>Confirmar senha</label>
            <input type="password" name="password_confirm" minlength="8" placeholder="Repita a senha" required>
        </div>

        <div class="row" style="justify-content:space-between; grid-column:1 / -1; margin-top:6px;">
            <a class="btn-secondary" style="display:inline-flex;align-items:center;justify-content:center;padding:0 14px;height:40px;text-decoration:none;color:#fff;border-radius:10px;" href="<?= e(base_url('route=login')) ?>">Cancelar</a>
            <button type="submit">Ativar acesso</button>
        </div>
    </form>
</div>


