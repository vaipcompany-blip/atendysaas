<div class="card" style="max-width:560px;margin:40px auto;">
    <h2 class="page-title" style="font-size:26px;">Criar conta no Atendy</h2>
    <p class="muted">Cadastre sua clínica para começar agora.</p>

    <?php if (!empty($error)): ?>
        <div class="alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;"><?= e((string) $error) ?></div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="alert"><?= e((string) $message) ?></div>
    <?php endif; ?>

    <?php
    $legalVersions = $legalVersions ?? ['terms_version' => 'v1.0', 'privacy_version' => 'v1.0'];
    $legalLinks = $legalLinks ?? ['terms_url' => base_url('route=legal&doc=terms'), 'privacy_url' => base_url('route=legal&doc=privacy')];
    $prefillEmail = mb_strtolower(trim((string) ($_GET['email'] ?? '')), 'UTF-8');
    if ($prefillEmail !== '' && !filter_var($prefillEmail, FILTER_VALIDATE_EMAIL)) {
        $prefillEmail = '';
    }
    ?>

    <form method="post" action="<?= e(base_url('route=register')) ?>" class="form-grid">
        <?= csrf_field() ?>

        <div class="field">
            <label>Nome da clínica *</label>
            <input type="text" name="nome_consultorio" required maxlength="150" placeholder="Ex: Clínica Odonto Prime">
        </div>

        <div class="field">
            <label>E-mail *</label>
            <input type="email" name="email" required maxlength="150" placeholder="voce@clinica.com" value="<?= e($prefillEmail) ?>">
        </div>

        <div class="field">
            <label>CPF do responsável *</label>
            <input type="text" name="cpf" required maxlength="14" placeholder="Somente números ou formato com pontos">
        </div>

        <div class="field">
            <label>Telefone *</label>
            <input type="text" name="telefone" required maxlength="20" placeholder="Ex: 11999998888">
        </div>

        <div class="field" style="grid-column:1/-1;">
            <label>Endereço</label>
            <input type="text" name="endereco" maxlength="255" placeholder="Rua, número, bairro, cidade">
        </div>

        <div class="field">
            <label>Senha *</label>
            <input type="password" name="password" required minlength="8" placeholder="Mínimo 8 caracteres">
        </div>

        <div class="field">
            <label>Confirmar senha *</label>
            <input type="password" name="password_confirm" required minlength="8" placeholder="Repita a senha">
        </div>

        <div class="field" style="grid-column:1/-1;">
            <label style="display:flex; align-items:flex-start; gap:8px; font-weight:500; line-height:1.5;">
                <input type="checkbox" name="accept_legal" value="1" required style="margin-top:3px; height:16px; width:16px;">
                <span>
                    Li e aceito os
                    <a href="<?= e((string) ($legalLinks['terms_url'] ?? base_url('route=legal&doc=terms'))) ?>" target="_blank" rel="noopener">Termos de Uso</a>
                    (<?= e((string) ($legalVersions['terms_version'] ?? 'v1.0')) ?>)
                    e a
                    <a href="<?= e((string) ($legalLinks['privacy_url'] ?? base_url('route=legal&doc=privacy'))) ?>" target="_blank" rel="noopener">Política de Privacidade</a>
                    (<?= e((string) ($legalVersions['privacy_version'] ?? 'v1.0')) ?>).
                </span>
            </label>
        </div>

        <div class="field" style="justify-content:flex-end;align-self:flex-end;">
            <button type="submit" style="height:40px;padding:0 24px;">Criar conta</button>
        </div>
    </form>

    <div style="margin-top:12px;">
        <a href="<?= e(base_url('route=login')) ?>" class="muted" style="text-decoration:none;">Já tenho conta, entrar</a>
    </div>
</div>


