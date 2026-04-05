<style>
.at-login-shell{min-height:calc(100vh - 120px);display:flex;align-items:center;justify-content:center;padding:16px 0 26px}
.at-login-frame{width:min(1140px,100%);display:grid;grid-template-columns:1fr 1fr;border-radius:20px;overflow:hidden;border:1px solid rgba(153,173,202,.38);box-shadow:0 24px 48px rgba(2,6,23,.35)}
.at-login-pane-left{background:#f8fbff;padding:42px 36px}
.at-login-brand{font-size:58px;line-height:1;font-weight:800;letter-spacing:-.03em;color:#0f172a;margin:0 0 8px}
.at-login-brand span{color:#2563eb}
.at-login-subtitle{margin:0 0 30px;color:#64748b;font-size:17px}
.at-login-title{margin:0 0 18px;color:#0f172a;font-size:42px;line-height:1.02;letter-spacing:-.02em}
.at-login-form{display:flex;flex-direction:column;gap:10px}
.at-login-form .field{gap:8px}
.at-login-form .field label{font-size:14px;color:#334155;font-weight:700}
.at-login-form .field input{height:48px;border-radius:11px;border:1px solid #bfcae0;background:#f1f5ff;font-size:17px}
.at-login-form .field input:focus{border-color:#3b82f6;box-shadow:0 0 0 4px rgba(59,130,246,.18)}
.at-login-actions{display:flex;justify-content:flex-end;margin-top:4px}
.at-login-actions a{font-size:14px;color:#1d4ed8;text-decoration:none;font-weight:600}
.at-login-actions a:hover{text-decoration:underline}
.at-login-submit{margin-top:10px;height:50px;border-radius:12px;background:linear-gradient(95deg,#335df2,#1d4ed8)!important;font-size:21px;font-weight:800;letter-spacing:.01em}
.at-login-pane-right{position:relative;background:radial-gradient(circle at 16% 22%,rgba(56,189,248,.28),transparent 40%),linear-gradient(135deg,#1d4ed8 0%,#1e3a8a 50%,#0f2a6b 100%);color:#eff6ff;padding:46px 40px;display:flex;flex-direction:column;justify-content:center;overflow:hidden}
.at-login-pane-right:before{content:'';position:absolute;inset:auto -60px -70px auto;width:220px;height:220px;background:radial-gradient(circle,#93c5fd 0%,rgba(147,197,253,0) 70%);opacity:.32}
.at-login-pane-right:after{content:'';position:absolute;top:-50px;left:-60px;width:210px;height:210px;border-radius:999px;background:radial-gradient(circle,#38bdf8 0%,rgba(56,189,248,0) 72%);opacity:.26}
.at-login-panel-content{position:relative;z-index:2}
.at-login-kicker{display:inline-flex;align-items:center;gap:7px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;font-weight:700;color:#dbeafe;border:1px solid rgba(219,234,254,.4);padding:6px 10px;border-radius:999px}
.at-login-panel-title{margin:16px 0 10px;font-size:46px;line-height:1.02;letter-spacing:-.02em}
.at-login-panel-text{margin:0;color:#dbeafe;font-size:18px;line-height:1.55;max-width:460px}
.at-login-metrics{margin-top:26px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.at-login-metric{border:1px solid rgba(219,234,254,.26);background:rgba(15,23,42,.24);border-radius:12px;padding:10px}
.at-login-metric strong{display:block;font-size:19px;color:#fff}
.at-login-metric span{font-size:12px;color:#bfdbfe}

@media (max-width:1020px){
    .at-login-frame{grid-template-columns:1fr}
    .at-login-pane-left{padding:28px 22px}
    .at-login-pane-right{padding:30px 22px}
    .at-login-brand{font-size:44px}
    .at-login-title{font-size:33px}
    .at-login-panel-title{font-size:34px}
    .at-login-panel-text{font-size:16px}
}

@media (max-width:640px){
    .at-login-shell{min-height:calc(100vh - 95px);align-items:flex-start}
    .at-login-brand{font-size:37px}
    .at-login-title{font-size:29px}
    .at-login-form .field input{height:44px;font-size:16px}
    .at-login-submit{height:46px;font-size:18px}
    .at-login-metrics{grid-template-columns:1fr}
}
</style>

<div class="at-login-shell">
    <section class="at-login-frame" aria-label="Acesso ao Atendy">
        <div class="at-login-pane-left">
            <h1 class="at-login-brand">At<span>endy</span></h1>
            <p class="at-login-subtitle">Gestão clínica com automação e controle da operação em um só lugar.</p>
            <h2 class="at-login-title">Entrar no painel</h2>

            <?php if (!empty($error)): ?>
                <div class="alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;margin-bottom:12px;"><?= e((string) $error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(base_url('route=login')) ?>" class="at-login-form">
                <?= csrf_field() ?>
                <div class="field">
                    <label>Endereço de e-mail ou CPF</label>
                    <input type="text" name="login" required placeholder="seu@email.com">
                </div>
                <div class="field">
                    <label>Senha</label>
                    <input type="password" name="password" required placeholder="Digite sua senha">
                </div>

                <div class="at-login-actions">
                    <a href="<?= e(base_url('route=forgot_password')) ?>">Esqueci minha senha</a>
                </div>

                <button type="submit" class="at-login-submit">Entrar</button>
            </form>
        </div>

        <aside class="at-login-pane-right" aria-label="Resumo da plataforma">
            <div class="at-login-panel-content">
                <span class="at-login-kicker">Atendy Platform</span>
                <h3 class="at-login-panel-title">Prático, inteligente e feito para clínicas</h3>
                <p class="at-login-panel-text">
                    Organize consultas, pacientes, mensagens e financeiro com uma experiência simples.
                    Tudo conectado para você ganhar tempo no dia a dia da sua equipe.
                </p>

                <div class="at-login-metrics">
                    <div class="at-login-metric">
                        <strong>Agenda</strong>
                        <span>Consultas e retornos</span>
                    </div>
                    <div class="at-login-metric">
                        <strong>WhatsApp</strong>
                        <span>Automação de confirmação</span>
                    </div>
                    <div class="at-login-metric">
                        <strong>Financeiro</strong>
                        <span>Fluxo e indicadores</span>
                    </div>
                </div>
            </div>
        </aside>
    </section>
</div>


