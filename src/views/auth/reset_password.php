<div class="card" style="max-width:560px;margin:40px auto;">
    <h1 class="page-title" style="font-size:24px;">Redefinir senha</h1>
    <p class="muted" style="margin-bottom:14px;">Crie uma nova senha para acessar sua conta.</p>

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

    <form method="post" action="<?= e(base_url('route=reset_password')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e((string) $token) ?>">

        <div class="field" style="grid-column:1 / -1;">
            <label>Nova senha</label>
            <input type="password" name="password" minlength="8" placeholder="Mínimo 8 caracteres" required>
        </div>

        <div class="field" style="grid-column:1 / -1;">
            <label>Confirmar nova senha</label>
            <input type="password" name="password_confirm" minlength="8" placeholder="Repita a senha" required>
        </div>

        <div class="row" style="justify-content:space-between; grid-column:1 / -1; margin-top:6px;">
            <a class="btn-secondary" style="display:inline-flex;align-items:center;justify-content:center;padding:0 14px;height:40px;text-decoration:none;color:#fff;border-radius:10px;" href="<?= e(base_url('route=login')) ?>">Cancelar</a>
            <button type="submit">Salvar nova senha</button>
        </div>
    </form>
</div>

