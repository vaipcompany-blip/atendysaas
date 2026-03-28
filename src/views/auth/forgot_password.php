<div class="card" style="max-width:560px;margin:40px auto;">
    <h1 class="page-title" style="font-size:24px;">Recuperar senha</h1>
    <p class="muted" style="margin-bottom:14px;">Informe seu e-mail ou CPF para gerar um link de recuperação.</p>

    <?php if (!empty($error)): ?>
        <div class="alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
            <?= e((string) $error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="alert" style="word-break:break-all;">
            <?= e((string) $message) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('route=forgot_password')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <div class="field" style="grid-column:1 / -1;">
            <label>E-mail ou CPF</label>
            <input type="text" name="login" placeholder="exemplo@clinica.com ou 11122233344" required>
        </div>

        <div class="row" style="justify-content:space-between; grid-column:1 / -1; margin-top:6px;">
            <a class="btn-secondary" style="display:inline-flex;align-items:center;justify-content:center;padding:0 14px;height:40px;text-decoration:none;color:#fff;border-radius:10px;" href="<?= e(base_url('route=login')) ?>">Voltar ao login</a>
            <button type="submit">Gerar link de recuperação</button>
        </div>
    </form>
</div>

