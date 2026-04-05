<?php
$email = trim((string) ($email ?? ''));
$error = $error ?? null;
$message = $message ?? null;
$legalVersions = $legalVersions ?? ['terms_version' => 'v1.0', 'privacy_version' => 'v1.0'];
$legalLinks = $legalLinks ?? ['terms_url' => base_url('route=legal&doc=terms'), 'privacy_url' => base_url('route=legal&doc=privacy')];
?>

<style>
.purchase-register-shell{position:relative;max-width:620px;margin:10px auto 30px}
.purchase-register-bg{position:fixed;inset:0;z-index:-1;background:radial-gradient(circle at 12% 18%,#89d2ff 0,#5ea8f7 28%,#355ddf 56%,#2a3178 100%)}
.purchase-register-card{background:#fff;border-radius:18px;overflow:hidden;border:1px solid #dbe2ee;box-shadow:0 24px 42px rgba(15,23,42,.25)}
.purchase-register-top{background:linear-gradient(96deg,#0d5bde 0%,#2667e8 100%);color:#eff6ff;padding:20px 24px 24px;text-align:center}
.purchase-register-brand{font-size:29px;font-weight:800;letter-spacing:-.01em;margin:0}
.purchase-register-sub{margin:6px 0 0;font-size:15px;color:#dbeafe}
.purchase-register-body{padding:24px}
.purchase-steps{display:flex;justify-content:center;align-items:center;gap:10px;margin-top:-2px;margin-bottom:16px}
.purchase-step{width:42px;height:42px;border-radius:999px;display:flex;align-items:center;justify-content:center;font-weight:700;background:#e2e8f0;color:#64748b}
.purchase-step.active{background:#3b82f6;color:#fff;box-shadow:0 8px 16px rgba(59,130,246,.35)}
.purchase-title{font-size:40px;margin:0;text-align:center;line-height:1.1;letter-spacing:-.02em;color:#0f172a}
.purchase-lead{margin:10px 0 16px;text-align:center;color:#475569;font-size:16px}
.purchase-feature-box{border:1px solid #e2e8f0;background:linear-gradient(180deg,#f8fafc,#f1f5f9);border-radius:14px;padding:14px 14px 8px}
.purchase-feature-box h3{margin:0 0 10px;font-size:28px;color:#0f172a}
.purchase-feature-list{list-style:none;padding:0;margin:0;display:grid;gap:8px}
.purchase-feature-list li{display:flex;align-items:center;gap:8px;color:#334155;font-weight:500}
.purchase-check{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:999px;background:#10b981;color:#ecfdf5;font-size:14px;font-weight:700}
.purchase-form{margin-top:16px;display:grid;gap:12px}
.purchase-form .field input{background:#fff;border:1px solid #cbd5e1;height:46px}
.purchase-terms{font-size:14px;color:#334155;display:flex;align-items:flex-start;gap:8px;line-height:1.45}
.purchase-submit{height:48px;border-radius:11px;background:linear-gradient(95deg,#2d7fff,#2c4dd9)!important;font-size:26px;font-weight:700;letter-spacing:.01em}
.purchase-bottom{text-align:center;color:#64748b;font-size:13px;margin-top:8px}

@media (max-width:740px){
    .purchase-register-shell{margin-top:0}
    .purchase-register-body{padding:16px}
    .purchase-title{font-size:33px}
    .purchase-feature-box h3{font-size:22px}
}
</style>

<div class="purchase-register-bg" aria-hidden="true"></div>

<div class="purchase-register-shell">
    <section class="purchase-register-card">
        <header class="purchase-register-top">
            <h1 class="purchase-register-brand">Atendy</h1>
            <p class="purchase-register-sub">Plataforma de gestão para clínicas e consultórios</p>
        </header>

        <div class="purchase-register-body">
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

            <div class="purchase-steps" aria-label="Etapas">
                <span class="purchase-step active">1</span>
                <span class="purchase-step">2</span>
            </div>

            <h2 class="purchase-title">Bem-vindo ao Atendy</h2>
            <p class="purchase-lead">Vamos começar criando sua conta de administrador.</p>

            <div class="purchase-feature-box">
                <h3>O que você pode fazer com o Atendy:</h3>
                <ul class="purchase-feature-list">
                    <li><span class="purchase-check">✓</span>Organizar agenda de consultas e retornos</li>
                    <li><span class="purchase-check">✓</span>Automatizar confirmações via WhatsApp</li>
                    <li><span class="purchase-check">✓</span>Controlar pacientes, histórico e financeiro</li>
                    <li><span class="purchase-check">✓</span>Acompanhar métricas e evolução da clínica</li>
                </ul>
            </div>

            <form method="post" action="<?= e(base_url('route=register')) ?>" class="purchase-form">
                <?= csrf_field() ?>

                <div class="field">
                    <label>Nome da clínica *</label>
                    <input type="text" name="nome_consultorio" required maxlength="150" placeholder="Ex: Clínica Sorriso Leve">
                </div>

                <div class="field">
                    <label>E-mail *</label>
                    <input type="email" name="email" required maxlength="150" placeholder="seu@email.com" value="<?= e($email) ?>">
                </div>

                <div class="field">
                    <label>Telefone *</label>
                    <input type="text" name="telefone" required maxlength="20" placeholder="(11) 99999-9999">
                </div>

                <div class="field">
                    <label>CPF do responsável *</label>
                    <input type="text" name="cpf" required maxlength="14" placeholder="Somente números ou com pontuação">
                </div>

                <div class="field">
                    <label>Senha *</label>
                    <input type="password" name="password" required minlength="8" placeholder="Mínimo 8 caracteres">
                </div>

                <div class="field">
                    <label>Confirmar senha *</label>
                    <input type="password" name="password_confirm" required minlength="8" placeholder="Digite a senha novamente">
                </div>

                <label class="purchase-terms">
                    <input type="checkbox" name="accept_legal" value="1" required style="margin-top:3px;">
                    <span>
                        Aceito os
                        <a href="<?= e((string) ($legalLinks['terms_url'] ?? base_url('route=legal&doc=terms'))) ?>" target="_blank" rel="noopener">termos de uso</a>
                        (<?= e((string) ($legalVersions['terms_version'] ?? 'v1.0')) ?>)
                        e política de privacidade
                        (<?= e((string) ($legalVersions['privacy_version'] ?? 'v1.0')) ?>).
                    </span>
                </label>

                <button type="submit" class="purchase-submit">Criar Minha Conta</button>
            </form>

            <div class="purchase-bottom">
                Já tem conta? <a href="<?= e(base_url('route=login')) ?>">Faça login aqui</a>
            </div>
        </div>
    </section>
</div>
