<div class="card" style="max-width:440px;margin:48px auto;">
    <h2 class="page-title" style="font-size:26px;">Entrar no Atendy</h2>
    <p class="muted">Use e-mail ou CPF e sua senha.</p>

    <?php if (!empty($error)): ?>
        <div class="alert"><?= e((string) $error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('route=login')) ?>">
        <?= csrf_field() ?>
        <div class="field" style="margin-bottom:10px;">
            <label>Login (E-mail ou CPF)</label>
            <input type="text" name="login" required>
        </div>
        <div class="field" style="margin-bottom:12px;">
            <label>Senha</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn-block">Entrar</button>
    </form>

    <div style="margin-top:10px; text-align:right;">
        <a href="<?= e(base_url('route=forgot_password')) ?>" class="muted" style="font-size:12px; text-decoration:none;">Esqueci minha senha</a>
    </div>

    <div style="margin-top:8px; text-align:right;">
        <a href="<?= e(base_url('route=register')) ?>" class="muted" style="font-size:12px; text-decoration:none;">Não tenho conta, cadastrar clínica</a>
    </div>

    <p class="muted" style="margin-top:12px;">Primeiro acesso? Cadastre a clínica para criar o ambiente.</p>
</div>


