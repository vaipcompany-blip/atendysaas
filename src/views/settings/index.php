<h1 class="page-title">Configurações</h1>
<p class="muted">Conecte seu WhatsApp em poucos passos e comece a falar com seus pacientes automaticamente.</p>

<?php if (!empty($message)): ?>
    <div class="alert"><?= e((string) $message) ?></div>
<?php endif; ?>

<div class="card settings-quick-nav">
    <h3 class="card-title">Índice rápido</h3>
    <div class="settings-nav-links">
        <a href="#config-conexao">Conexão</a>
        <a href="#config-tutorial">Tutorial</a>
        <a href="#config-campos">Campos obrigatórios</a>
        <a href="#config-webhook">Dados para Meta</a>
        <a href="#config-teste">Teste final</a>
        <a href="#config-modelos">Modelos automáticos</a>
        <a href="#config-seguranca">Segurança</a>
        <a href="#config-monitoramento">Monitoramento</a>
    </div>
</div>

<div class="card" id="config-conexao">
    <h3 class="card-title">1) Status da sua conexão</h3>
    <div class="row">
        <span class="chip"><?= $isCloudReady ? 'OK. Pronto para envio real' : 'Configuração pendente' ?></span>
        <span class="muted">Modo: API em nuvem do WhatsApp</span>
    </div>
    <div style="margin-top:10px;" class="muted">
        <div><?= ($checklist['phone_number_id'] ?? false) ? 'OK.' : 'Pendente' ?> ID do número de telefone</div>
        <div><?= ($checklist['access_token'] ?? false) ? 'OK.' : 'Pendente' ?> Token de acesso</div>
        <div><?= ($checklist['verify_token'] ?? false) ? 'OK.' : 'Pendente' ?> Token de verificação gerado</div>
    </div>
</div>

<div class="card" id="config-tutorial">
    <h3 class="card-title">2) Tutorial passo a passo (cliente final)</h3>
    <div class="row" style="margin-bottom:10px;">
        <button type="button" class="btn-secondary" onclick="openHelpModal()">Onde encontro esses dados na Meta?</button>
        <button type="button" onclick="startTour()">Iniciar tour guiado (30s)</button>
    </div>
    <ol class="muted" style="margin:0 0 0 18px; line-height:1.9;">
        <li>Abra o <strong>Meta Developers</strong> e entre no seu aplicativo do WhatsApp.</li>
        <li>Copie o <strong>ID do Número de Telefone</strong> e cole no campo abaixo.</li>
        <li>Copie o <strong>Token de Acesso</strong> e cole no campo abaixo.</li>
        <li>No Meta, vá em <strong>Webhook</strong> e use a URL e o token desta tela.</li>
        <li>Clique em <strong>Salvar configurações</strong>.</li>
        <li>Informe seu número no campo de teste e clique em <strong>Salvar e testar envio</strong>.</li>
        <li>Se a mensagem chegar no seu WhatsApp, a conexão está concluída.</li>
    </ol>
</div>

<div id="meta-help-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:9999; padding:16px;">
    <div style="max-width:760px; margin:30px auto; background:#fff; border-radius:14px; border:1px solid #e2e8f0; box-shadow:0 18px 40px rgba(15,23,42,.25); overflow:hidden;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
            <strong>Guia rápido: onde pegar os dados na Meta</strong>
            <button type="button" class="btn-secondary" onclick="closeHelpModal()">Fechar</button>
        </div>

        <div style="padding:14px 16px; max-height:70vh; overflow:auto;" class="muted">
            <p style="margin-top:0;">Siga exatamente estes passos (leva ~3 minutos):</p>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:10px;">
                <strong>Passo 1 - Entrar no painel Meta</strong>
                <div>1. Acesse <code>developers.facebook.com</code>.</div>
                <div>2. Clique em <strong>Meus Apps</strong>.</div>
                <div>3. Abra o aplicativo conectado ao WhatsApp.</div>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:10px;">
                <strong>Passo 2 - Copiar o ID do Número de Telefone</strong>
                <div>1. No menu lateral, entre em <strong>WhatsApp &gt; Configuração de API</strong>.</div>
                <div>2. Procure o campo <strong>ID do Número de Telefone</strong>.</div>
                <div>3. Copie o valor e cole no campo correspondente do Atendy.</div>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:10px;">
                <strong>Passo 3 - Copiar o Token de Acesso</strong>
                <div>1. Na mesma tela de configuração, localize o token temporário de acesso ou seu token permanente.</div>
                <div>2. Copie o token completo.</div>
                <div>3. Cole no campo <strong>Token de Acesso</strong> do Atendy.</div>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:10px;">
                <strong>Passo 4 - Configurar webhook</strong>
                <div>1. Na Meta, vá em <strong>WhatsApp &gt; Configuração</strong> (ou Webhooks).</div>
                <div>2. Cole a <strong>URL de retorno</strong> e o <strong>Token de Verificação</strong> mostrados no Atendy.</div>
                <div>3. Assine os eventos: <code>messages</code> e <code>message_status</code>.</div>
            </div>

            <div style="background:#ecfeff; border:1px solid #a5f3fc; border-radius:10px; padding:12px;">
                <strong>Dica importante</strong>
                <div>Se der erro de permissão ao enviar, normalmente é token expirado. Gere um novo token e teste novamente.</div>
            </div>
        </div>
    </div>
</div>

<div class="card" id="config-campos">
    <h3 class="card-title">3) Preencha somente estes campos</h3>
    <form method="post" action="<?= e(base_url('route=settings')) ?>" class="form-grid" id="connection-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="whatsapp_mode" value="cloud">
        <input type="hidden" name="whatsapp_api_url" value="<?= e((string) ($settings['whatsapp_api_url'] ?? 'https://graph.facebook.com/v20.0')) ?>">
        <input type="hidden" name="whatsapp_default_country" value="<?= e((string) ($settings['whatsapp_default_country'] ?? '55')) ?>">
        <input type="hidden" name="whatsapp_verify_token" value="<?= e((string) ($settings['whatsapp_verify_token'] ?? '')) ?>">

        <div class="field" id="tour-step-1">
            <label>ID do número de telefone (copie da Meta)</label>
            <input type="text" name="whatsapp_phone_number_id" value="<?= e((string) ($settings['whatsapp_phone_number_id'] ?? '')) ?>" placeholder="Ex: 123456789012345" required>
        </div>

        <div class="field" id="tour-step-2">
            <label>Token de acesso (copie da Meta)</label>
            <input type="password" name="token_whatsapp" value="" placeholder="<?= !empty($settings['token_whatsapp']) ? 'Token já salvo. Preencha apenas para substituir.' : 'Cole aqui seu token' ?>" autocomplete="new-password">
            <?php if (!empty($settings['token_whatsapp'])): ?>
                <small class="muted">Token salvo com segurança. Deixe em branco para manter o valor atual.</small>
            <?php endif; ?>
        </div>

        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <button type="submit">Salvar configurações</button>
        </div>
    </form>
</div>

<div class="card" id="tour-step-3">
    <a id="config-webhook"></a>
    <h3 class="card-title">4) Copie estes dados no Meta Developers</h3>
    <p class="muted">No cadastro do webhook da Meta, use exatamente os valores abaixo:</p>
    <div class="row" style="align-items:flex-start;">
        <strong style="min-width:120px;">URL de retorno:</strong>
        <code id="callback-url" style="word-break:break-all;"><?= e((string) $webhookUrl) ?></code>
        <button type="button" class="btn-secondary" onclick="copyText('<?= e((string) $webhookUrl) ?>', this)">Copiar URL</button>
    </div>
    <div class="row" style="margin-top:8px; align-items:flex-start;">
        <strong style="min-width:120px;">Token de verificação:</strong>
        <code id="verify-token" style="word-break:break-all;"><?= e((string) ($settings['whatsapp_verify_token'] ?? '')) ?></code>
        <button type="button" class="btn-secondary" onclick="copyText('<?= e((string) ($settings['whatsapp_verify_token'] ?? '')) ?>', this)">Copiar token</button>
    </div>
    <p class="muted" style="margin-top:8px;">Campos para assinar no webhook: <code>messages</code> e <code>message_status</code>.</p>
    <p class="muted" style="margin-top:6px;">Dica: depois de colar esses dados na Meta, clique em verificar/salvar dentro da plataforma da Meta.</p>
</div>

<div class="card" id="tour-step-4">
    <a id="config-teste"></a>
    <h3 class="card-title">5) Teste final (1 clique)</h3>
    <form method="post" action="<?= e(base_url('route=settings')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="test_connection">
        <input type="hidden" name="whatsapp_mode" value="cloud">
        <input type="hidden" name="whatsapp_api_url" value="<?= e((string) ($settings['whatsapp_api_url'] ?? 'https://graph.facebook.com/v20.0')) ?>">
        <input type="hidden" name="whatsapp_phone_number_id" value="<?= e((string) ($settings['whatsapp_phone_number_id'] ?? '')) ?>">
        <input type="hidden" name="whatsapp_verify_token" value="<?= e((string) ($settings['whatsapp_verify_token'] ?? '')) ?>">
        <input type="hidden" name="whatsapp_default_country" value="<?= e((string) ($settings['whatsapp_default_country'] ?? '55')) ?>">

        <div class="field">
            <label>Seu número para teste (com DDD)</label>
            <input type="text" name="test_phone" placeholder="Ex: 11999998888" required>
        </div>

        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <button type="submit">Salvar e testar envio</button>
        </div>
    </form>
</div>

<div class="card" id="config-modelos">
    <h3 class="card-title">6) Expediente e templates de automação</h3>
    <p class="muted">
        Defina seus horários de atendimento e personalize textos automáticos com variáveis dinâmicas:<br>
        <code>{{nome}}</code>, <code>{{data_hora}}</code>, <code>{{procedimento}}</code>, <code>{{status}}</code>,
        <code>{{clinica}}</code>, <code>{{telefone_clinica}}</code>, <code>{{endereco_clinica}}</code>, <code>{{email_clinica}}</code>.<br>
        Atalhos também aceitos: <code>{{telefone}}</code> e <code>{{endereco}}</code>.
    </p>
    <p class="muted" style="font-size:12px; margin-top:8px;">
        Pré-visualização em tempo real usando dados de exemplo: <code>Maria Oliveira</code>, <code><?= e(date('d/m/Y H:i', strtotime('+1 day 14:30'))) ?></code>,
        <code>Avaliação odontológica</code>, status <code>confirmada</code>.
    </p>

    <div class="template-token-toolbar" id="template-token-toolbar">
        <strong>Inserção rápida de variáveis:</strong>
        <button type="button" class="btn-secondary token-chip" data-template-token="{{nome}}">{{nome}}</button>
        <button type="button" class="btn-secondary token-chip" data-template-token="{{data_hora}}">{{data_hora}}</button>
        <button type="button" class="btn-secondary token-chip" data-template-token="{{procedimento}}">{{procedimento}}</button>
        <button type="button" class="btn-secondary token-chip" data-template-token="{{status}}">{{status}}</button>
        <button type="button" class="btn-secondary token-chip" data-template-token="{{clinica}}">{{clinica}}</button>
        <button type="button" class="btn-secondary token-chip" data-template-token="{{telefone_clinica}}">{{telefone_clinica}}</button>
        <button type="button" class="btn-secondary token-chip" data-template-token="{{endereco_clinica}}">{{endereco_clinica}}</button>
        <button type="button" class="btn-secondary token-chip" data-template-token="{{email_clinica}}">{{email_clinica}}</button>
        <button type="button" class="btn-secondary" id="normalize-active-template-btn">Normalizar campo ativo</button>
        <button type="button" class="btn-secondary" id="normalize-all-templates-btn">Normalizar todos</button>
        <span class="muted" id="template-token-hint">Clique em um campo de template e depois em uma variável. Atalho: Ctrl+Space.</span>
        <div id="template-token-tooltip" class="template-token-tooltip" style="display:none;"></div>
    </div>

    <div id="template-shortcut-picker" class="template-shortcut-picker" style="display:none;" role="dialog" aria-label="Seletor de variáveis">
        <div class="template-shortcut-header">
            <strong>Inserir variável</strong>
            <span class="muted" style="font-size:11px;">Enter para inserir | Esc para fechar</span>
        </div>
        <input type="text" id="template-shortcut-search" placeholder="Buscar variável..." autocomplete="off">
        <div id="template-shortcut-list" class="template-shortcut-list"></div>
    </div>

    <form method="post" action="<?= e(base_url('route=settings')) ?>" class="form-grid" id="templates-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div id="templates-invalid-alert" class="template-invalid-alert" style="display:none; grid-column:1/-1;"></div>

        <div class="field">
            <label>Horário de abertura</label>
            <input type="time" name="horario_abertura" value="<?= e(substr((string) ($settings['horario_abertura'] ?? '08:00:00'), 0, 5)) ?>" required>
        </div>

        <div class="field">
            <label>Horário de fechamento</label>
            <input type="time" name="horario_fechamento" value="<?= e(substr((string) ($settings['horario_fechamento'] ?? '18:00:00'), 0, 5)) ?>" required>
        </div>

        <div class="field">
            <label>Duração da consulta (min)</label>
            <input type="number" name="duracao_consulta" min="10" max="240" step="5" value="<?= e((string) ($settings['duracao_consulta'] ?? 60)) ?>" required>
        </div>

        <div class="field">
            <label>Intervalo entre consultas (min)</label>
            <input type="number" name="intervalo" min="0" max="120" step="5" value="<?= e((string) ($settings['intervalo'] ?? 10)) ?>" required>
        </div>

        <div class="field">
            <label>Meta mensal de conversão (%)</label>
            <input type="number" name="meta_conversao_mensal" min="0.1" max="200" step="0.5" value="<?= e((string) ($settings['meta_conversao_mensal'] ?? 60.0)) ?>" required>
            <div class="muted" style="font-size:12px; margin-top:4px;">Percentual-alvo de leads convertidos por mês</div>
        </div>

        <div class="field" style="grid-column:1/-1;">
            <label>Template de confirmação (24h)</label>
            <textarea name="mensagem_confirmacao" rows="2" style="resize:vertical;" required data-template-preview="1" data-template-key="mensagem_confirmacao"><?= e((string) ($settings['mensagem_confirmacao'] ?? 'Olá {{nome}}! Sua consulta será em {{data_hora}}. Responda SIM para confirmar.')) ?></textarea>
            <div class="template-meta">
                <span class="template-char-count" data-char-count-for="mensagem_confirmacao"></span>
                <span class="template-token-check" data-token-check-for="mensagem_confirmacao"></span>
            </div>
            <div class="template-preview" data-preview-for="mensagem_confirmacao"></div>
        </div>

        <div class="field" style="grid-column:1/-1;">
            <label>Template lembrete 12h</label>
            <textarea name="template_lembrete_12h" rows="2" style="resize:vertical;" required data-template-preview="1" data-template-key="template_lembrete_12h"><?= e((string) ($settings['template_lembrete_12h'] ?? 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 12 horas. Data: {{data_hora}}')) ?></textarea>
            <div class="template-meta">
                <span class="template-char-count" data-char-count-for="template_lembrete_12h"></span>
                <span class="template-token-check" data-token-check-for="template_lembrete_12h"></span>
            </div>
            <div class="template-preview" data-preview-for="template_lembrete_12h"></div>
        </div>

        <div class="field" style="grid-column:1/-1;">
            <label>Template lembrete 2h</label>
            <textarea name="template_lembrete_2h" rows="2" style="resize:vertical;" required data-template-preview="1" data-template-key="template_lembrete_2h"><?= e((string) ($settings['template_lembrete_2h'] ?? 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 2 horas. Data: {{data_hora}}')) ?></textarea>
            <div class="template-meta">
                <span class="template-char-count" data-char-count-for="template_lembrete_2h"></span>
                <span class="template-token-check" data-token-check-for="template_lembrete_2h"></span>
            </div>
            <div class="template-preview" data-preview-for="template_lembrete_2h"></div>
        </div>

        <div class="field" style="grid-column:1/-1;">
            <label>Template follow-up de falta</label>
            <textarea name="template_followup_falta" rows="2" style="resize:vertical;" required data-template-preview="1" data-template-key="template_followup_falta"><?= e((string) ($settings['template_followup_falta'] ?? 'Oi {{nome}}! Sentimos sua falta na consulta. Quer reagendar?')) ?></textarea>
            <div class="template-meta">
                <span class="template-char-count" data-char-count-for="template_followup_falta"></span>
                <span class="template-token-check" data-token-check-for="template_followup_falta"></span>
            </div>
            <div class="template-preview" data-preview-for="template_followup_falta"></div>
        </div>

        <div class="field" style="grid-column:1/-1;">
            <label>Template follow-up de cancelamento</label>
            <textarea name="template_followup_cancelamento" rows="2" style="resize:vertical;" required data-template-preview="1" data-template-key="template_followup_cancelamento"><?= e((string) ($settings['template_followup_cancelamento'] ?? 'Olá {{nome}}! Podemos te ajudar a remarcar sua consulta?')) ?></textarea>
            <div class="template-meta">
                <span class="template-char-count" data-char-count-for="template_followup_cancelamento"></span>
                <span class="template-token-check" data-token-check-for="template_followup_cancelamento"></span>
            </div>
            <div class="template-preview" data-preview-for="template_followup_cancelamento"></div>
        </div>

        <div class="field" style="grid-column:1/-1;">
            <label>Template follow-up de inatividade</label>
            <textarea name="template_followup_inatividade" rows="2" style="resize:vertical;" required data-template-preview="1" data-template-key="template_followup_inatividade"><?= e((string) ($settings['template_followup_inatividade'] ?? 'Oi {{nome}}! Faz um tempo que você não agenda consulta. Quer ver horários disponíveis?')) ?></textarea>
            <div class="template-meta">
                <span class="template-char-count" data-char-count-for="template_followup_inatividade"></span>
                <span class="template-token-check" data-token-check-for="template_followup_inatividade"></span>
            </div>
            <div class="template-preview" data-preview-for="template_followup_inatividade"></div>
        </div>

        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <button type="submit">Salvar expediente e templates</button>
        </div>
    </form>
</div>

<div class="card" id="config-respostas-automaticas">
    <h3 class="card-title">7) Respostas automáticas personalizadas</h3>
    <p class="muted">
        Quando um lead enviar uma palavra-chave exata, o sistema responde automaticamente com o texto que você cadastrar aqui.<br>
        Use <strong>__welcome__</strong> como palavra-chave para personalizar a mensagem de boas-vindas enviada ao primeiro contato.<br>
        As respostas do dentista têm <strong>prioridade</strong> sobre os textos padrão do sistema.
    </p>

    <?php if (!empty($autoReplies)): ?>
    <table style="width:100%; border-collapse:collapse; margin-bottom:16px; font-size:13px;">
        <thead>
            <tr style="background:#f1f5f9; text-align:left;">
                <th style="padding:8px 10px; border-bottom:1px solid #e2e8f0;">Palavra-chave</th>
                <th style="padding:8px 10px; border-bottom:1px solid #e2e8f0;">Resposta automática</th>
                <th style="padding:8px 10px; border-bottom:1px solid #e2e8f0; width:90px;">Ação</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($autoReplies as $ar): ?>
            <tr style="border-bottom:1px solid #f1f5f9; vertical-align:top; <?= ((int)$ar['is_active']) === 0 ? 'opacity:.45;' : '' ?>">
                <td style="padding:8px 10px;">
                    <code><?= e((string) $ar['keyword']) ?></code>
                </td>
                <td style="padding:8px 10px; color:#334155; line-height:1.5;"><?= nl2br(e((string) $ar['reply'])) ?></td>
                <td style="padding:8px 10px;">
                    <form method="post" action="<?= e(base_url('route=settings')) ?>" onsubmit="return confirm('Remover esta resposta?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_auto_reply">
                        <input type="hidden" name="reply_id" value="<?= (int) $ar['id'] ?>">
                        <button type="submit" class="btn-secondary" style="color:#dc2626; border-color:#dc2626;">Remover</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="muted" style="margin-bottom:14px;">Nenhuma resposta personalizada cadastrada ainda. O sistema usará os textos padrão.</p>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('route=settings')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_auto_reply">

        <div class="field">
            <label>Palavra-chave (o que o paciente digita)</label>
            <input type="text" name="keyword" placeholder="Ex: 1  /  agendar  /  __welcome__" maxlength="100" required>
            <span class="muted" style="font-size:12px; margin-top:4px;">Sem acentos e em minúsculo (o sistema normaliza automaticamente).</span>
        </div>

        <div class="field">
            <label>Resposta automática</label>
            <textarea name="reply" rows="3" placeholder="Digite o texto que será enviado quando o paciente mandar essa palavra..." required style="resize:vertical;"></textarea>
        </div>

        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <button type="submit">Adicionar resposta</button>
        </div>
    </form>
</div>

<div class="card" id="config-feriados">
    <h3 class="card-title">8) Feriados e datas indisponíveis</h3>
    <p class="muted">Bloqueie o dia inteiro ou apenas uma faixa de horário. Isso impede criação/reagendamento manual nesses períodos.</p>

    <form method="post" action="<?= e(base_url('route=settings')) ?>" class="form-grid" style="margin-bottom:12px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_blocked_date">

        <div class="field">
            <label>Data bloqueada</label>
            <input type="date" name="blocked_date" required>
        </div>

        <div class="field">
            <label>Motivo (opcional)</label>
            <input type="text" name="reason" maxlength="150" placeholder="Ex: Feriado nacional / Recesso da clínica">
        </div>

        <div class="field">
            <label>Início (opcional)</label>
            <input type="time" name="start_time" step="60">
        </div>

        <div class="field">
            <label>Fim (opcional)</label>
            <input type="time" name="end_time" step="60">
        </div>

        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <button type="submit">Adicionar bloqueio</button>
        </div>
    </form>

    <?php if (!empty($blockedDates)): ?>
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="background:#f1f5f9; text-align:left;">
                    <th style="padding:8px 10px; border-bottom:1px solid #e2e8f0;">Data</th>
                    <th style="padding:8px 10px; border-bottom:1px solid #e2e8f0;">Faixa</th>
                    <th style="padding:8px 10px; border-bottom:1px solid #e2e8f0;">Motivo</th>
                    <th style="padding:8px 10px; border-bottom:1px solid #e2e8f0; width:90px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($blockedDates as $bd): ?>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:8px 10px;"><?= e(date('d/m/Y', strtotime((string) $bd['blocked_date']))) ?></td>
                        <td style="padding:8px 10px; color:#334155;">
                            <?php if (!empty($bd['start_time']) && !empty($bd['end_time'])): ?>
                                <?= e(substr((string) $bd['start_time'], 0, 5)) ?> - <?= e(substr((string) $bd['end_time'], 0, 5)) ?>
                            <?php else: ?>
                                Dia inteiro
                            <?php endif; ?>
                        </td>
                        <td style="padding:8px 10px; color:#334155;"><?= e((string) ($bd['reason'] ?? '-')) ?></td>
                        <td style="padding:8px 10px;">
                            <form method="post" action="<?= e(base_url('route=settings')) ?>" onsubmit="return confirm('Remover este bloqueio?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_blocked_date">
                                <input type="hidden" name="blocked_date_id" value="<?= (int) $bd['id'] ?>">
                                <button type="submit" class="btn-secondary" style="color:#dc2626; border-color:#dc2626;">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">Nenhuma data bloqueada cadastrada.</p>
    <?php endif; ?>
</div>

<div class="card" id="config-seguranca">
    <h3 class="card-title">9) Segurança da conta</h3>
    <p class="muted">Altere sua senha de acesso ao sistema com segurança.</p>

    <form method="post" action="<?= e(base_url('route=settings')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">

        <div class="field">
            <label>Senha atual</label>
            <input type="password" name="current_password" placeholder="Digite sua senha atual" required>
        </div>

        <div class="field">
            <label>Nova senha</label>
            <input type="password" name="new_password" minlength="8" placeholder="Mínimo 8 caracteres" required>
        </div>

        <div class="field">
            <label>Confirmar nova senha</label>
            <input type="password" name="new_password_confirm" minlength="8" placeholder="Repita a nova senha" required>
        </div>

        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <button type="submit">Atualizar senha</button>
        </div>
    </form>

    <hr style="margin:14px 0; border:none; border-top:1px solid #e2e8f0;">

    <div class="row" style="justify-content:space-between; align-items:center;">
        <div>
            <strong>Encerrar sessões ativas</strong>
            <div class="muted">Desconecta dispositivos/sessões antigas e mantém apenas a sessão atual.</div>
        </div>
        <form method="post" action="<?= e(base_url('route=settings')) ?>" onsubmit="return confirm('Encerrar todas as sessões ativas agora?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="logout_all_sessions">
            <button type="submit" class="btn-secondary">Encerrar sessões</button>
        </form>
    </div>
</div>

<div class="card" id="config-monitoramento">
    <h3 class="card-title">10) Monitoramento de segurança</h3>
    <p class="muted">Histórico recente de tentativas de login e eventos críticos da sua conta.</p>

    <div class="row" style="align-items:flex-start; gap:14px;">
        <div style="flex:1; min-width:320px;">
            <strong style="display:block; margin-bottom:8px;">Tentativas de login (últimas 20)</strong>
            <div class="table-wrap" style="max-height:260px; overflow:auto;">
                <table style="min-width:620px;">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Login</th>
                            <th>IP</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentLoginAttempts)): ?>
                            <tr><td colspan="4" class="muted">Sem tentativas registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentLoginAttempts as $attempt): ?>
                                <tr>
                                    <td><?= e(date('d/m/Y H:i', strtotime((string) $attempt['attempted_at']))) ?></td>
                                    <td><?= e((string) $attempt['login_identifier']) ?></td>
                                    <td><?= e((string) ($attempt['ip_address'] ?? '-')) ?></td>
                                    <td>
                                        <?php if ((int) ($attempt['success'] ?? 0) === 1): ?>
                                            <span class="chip" style="background:#dcfce7;color:#166534;">Sucesso</span>
                                        <?php else: ?>
                                            <span class="chip" style="background:#fee2e2;color:#991b1b;">Falha</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="flex:1; min-width:320px;">
            <strong style="display:block; margin-bottom:8px;">Eventos de segurança (últimos 20)</strong>
            <div class="table-wrap" style="max-height:260px; overflow:auto;">
                <table style="min-width:620px;">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Evento</th>
                            <th>IP</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSecurityEvents)): ?>
                            <tr><td colspan="4" class="muted">Sem eventos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentSecurityEvents as $event): ?>
                                <tr>
                                    <td><?= e(date('d/m/Y H:i', strtotime((string) $event['created_at']))) ?></td>
                                    <td><code><?= e((string) $event['event_type']) ?></code></td>
                                    <td><?= e((string) ($event['ip_address'] ?? '-')) ?></td>
                                    <td><?= e((string) ($event['details'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card" id="config-backup">
    <h3 class="card-title">11) Backup operacional</h3>
    <p class="muted">Gere uma cópia SQL do banco de dados para contingência operacional. O arquivo também fica salvo em <code>storage/backups</code>.</p>

    <div class="row" style="justify-content:space-between; align-items:center; margin-bottom:14px;">
        <div>
            <strong>Gerar backup do banco</strong>
            <div class="muted">Executa <code>mysqldump</code> usando as credenciais do ambiente atual.</div>
        </div>
        <form method="post" action="<?= e(base_url('route=settings')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_backup">
            <button type="submit">Gerar e baixar backup</button>
        </form>
    </div>

    <strong style="display:block; margin-bottom:8px;">Últimos backups</strong>
    <div class="table-wrap" style="max-height:240px; overflow:auto;">
        <table style="min-width:620px;">
            <thead>
                <tr>
                    <th>Arquivo</th>
                    <th>Data/Hora</th>
                    <th>Tamanho</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentBackups)): ?>
                    <tr><td colspan="3" class="muted">Nenhum backup gerado ainda.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentBackups as $backup): ?>
                        <tr>
                            <td><code><?= e((string) $backup['file_name']) ?></code></td>
                            <td><?= e(date('d/m/Y H:i', strtotime((string) $backup['modified_at']))) ?></td>
                            <td><?= e(number_format(((int) ($backup['size'] ?? 0)) / 1024, 1, ',', '.')) ?> KB</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" id="config-observabilidade">
    <h3 class="card-title">12) Observabilidade básica</h3>
    <p class="muted">Resumo operacional do sistema e últimos erros registrados em <code>storage/logs/app.log</code>.</p>

    <?php
    $observability = $observability ?? [];
    $observabilityStatus = $observabilityStatus ?? ['status' => 'ok', 'alerts' => []];
    $jobs24h = (array) ($observability['jobs_24h'] ?? []);
    $automation24h = (array) ($observability['automation_24h'] ?? []);
    $appErrors24h = (array) ($observability['app_errors_24h'] ?? []);
    $statusColor = ((string) ($observabilityStatus['status'] ?? 'ok')) === 'warn' ? '#92400e' : '#166534';
    $statusBg = ((string) ($observabilityStatus['status'] ?? 'ok')) === 'warn' ? '#fef3c7' : '#dcfce7';
    ?>

    <?php $healthStatus = (string) (($healthReport['status'] ?? 'fail')); ?>
    <div class="row" style="margin-bottom:12px;">
        <span class="chip" style="background:<?= $healthStatus === 'ok' ? '#dcfce7' : ($healthStatus === 'warn' ? '#fef3c7' : '#fee2e2') ?>; color:<?= $healthStatus === 'ok' ? '#166534' : ($healthStatus === 'warn' ? '#92400e' : '#991b1b') ?>;">
            Saúde do sistema: <?= e((string) (($healthStatus === 'ok') ? 'OK' : (($healthStatus === 'warn') ? 'ALERTA' : 'FALHA'))) ?>
        </span>
        <span class="chip" style="background:<?= e($statusBg) ?>; color:<?= e($statusColor) ?>;">
            Operação: <?= e((string) (((string) ($observabilityStatus['status'] ?? 'ok')) === 'warn' ? 'ALERTA' : 'OK')) ?>
        </span>
        <span class="muted">Gerado em <?= e(date('d/m/Y H:i', strtotime((string) ($healthReport['generated_at'] ?? 'now')))) ?></span>
    </div>

    <?php if (!empty($observabilityStatus['alerts'])): ?>
        <div class="alert" style="margin-bottom:12px;">
            <?php foreach ((array) $observabilityStatus['alerts'] as $alert): ?>
                <div>- <?= e((string) $alert) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid" style="margin-bottom:14px;">
        <div class="stat-card">
            <div class="stat-label">Jobs 24h</div>
            <div class="stat-value"><?= e((string) ($jobs24h['total'] ?? 0)) ?></div>
            <div class="muted">ok: <?= e((string) ($jobs24h['success'] ?? 0)) ?> | falha: <?= e((string) ($jobs24h['failed'] ?? 0)) ?> | ignorado: <?= e((string) ($jobs24h['skipped'] ?? 0)) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Automações 24h</div>
            <div class="stat-value"><?= e((string) ($automation24h['total'] ?? 0)) ?></div>
            <div class="muted">enviadas: <?= e((string) ($automation24h['sent'] ?? 0)) ?> | erros: <?= e((string) ($automation24h['errors'] ?? 0)) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Erros do registro (24h)</div>
            <div class="stat-value"><?= e((string) ($appErrors24h['total'] ?? 0)) ?></div>
            <div class="muted">principais eventos abaixo</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Último sucesso</div>
            <div class="stat-value" style="font-size:18px;"><?= e(!empty($jobs24h['latest_success_at']) ? date('d/m H:i', strtotime((string) $jobs24h['latest_success_at'])) : '-') ?></div>
            <div class="muted">fluxo de automações</div>
        </div>
    </div>

    <div class="table-wrap" style="margin-bottom:14px; overflow:auto;">
        <table style="min-width:620px;">
            <thead>
                <tr>
                    <th>Verificação</th>
                    <th>Status</th>
                    <th>Resumo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($healthReport['checks'] ?? []) as $checkName => $checkData): ?>
                    <tr>
                        <td><code><?= e((string) $checkName) ?></code></td>
                        <td><?= e(strtoupper((string) ($checkData['status'] ?? 'fail'))) ?></td>
                        <td><?= e(json_encode($checkData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <strong style="display:block; margin-bottom:8px;">Últimos erros da aplicação</strong>
    <div class="table-wrap" style="max-height:260px; overflow:auto;">
        <table style="min-width:700px;">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Mensagem</th>
                    <th>Contexto</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentAppErrors)): ?>
                    <tr><td colspan="3" class="muted">Nenhum erro recente registrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentAppErrors as $error): ?>
                        <tr>
                            <td><?= e(date('d/m/Y H:i', strtotime((string) ($error['timestamp'] ?? 'now')))) ?></td>
                            <td><?= e((string) ($error['message'] ?? '-')) ?></td>
                            <td><?= e(json_encode((array) ($error['context'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <strong style="display:block; margin:12px 0 8px;">Execuções recentes de automação</strong>
    <div class="table-wrap" style="max-height:240px; overflow:auto; margin-bottom:10px;">
        <table style="min-width:760px;">
            <thead>
                <tr>
                    <th>Início</th>
                    <th>Status</th>
                    <th>Tipo</th>
                    <th>Simulação</th>
                    <th>Erro</th>
                </tr>
            </thead>
            <tbody>
                <?php $recentRuns = (array) ($observability['recent_job_runs'] ?? []); ?>
                <?php if (empty($recentRuns)): ?>
                    <tr><td colspan="5" class="muted">Sem execuções recentes.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentRuns as $run): ?>
                        <tr>
                            <td><?= e(date('d/m/Y H:i', strtotime((string) ($run['started_at'] ?? 'now')))) ?></td>
                            <td><code><?= e((string) ($run['status'] ?? '-')) ?></code></td>
                            <td><?= e((string) ($run['job_type'] ?? '-')) ?></td>
                            <td><?= ((int) ($run['dry_run'] ?? 0)) === 1 ? 'sim' : 'não' ?></td>
                            <td><?= e((string) ($run['error_message'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <strong style="display:block; margin:12px 0 8px;">Principais erros por mensagem (24h)</strong>
    <div class="table-wrap" style="max-height:200px; overflow:auto;">
        <table style="min-width:640px;">
            <thead>
                <tr>
                    <th>Mensagem</th>
                    <th>Ocorrências</th>
                </tr>
            </thead>
            <tbody>
                <?php $topErrors = (array) ($appErrors24h['by_message'] ?? []); ?>
                <?php if (empty($topErrors)): ?>
                    <tr><td colspan="2" class="muted">Nenhum erro mapeado nas últimas 24h.</td></tr>
                <?php else: ?>
                    <?php foreach ($topErrors as $errorRow): ?>
                        <tr>
                            <td><?= e((string) ($errorRow['message'] ?? '-')) ?></td>
                            <td><?= e((string) ($errorRow['count'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" id="calendar-integration">
    <a id="config-calendario"></a>
    <h3 class="card-title">13) Integração com Calendário Externo</h3>
    <p class="muted">
        Sincronize suas consultas automaticamente com <strong>Google Agenda</strong>, <strong>Calendário Apple</strong>,
        <strong>Outlook</strong> e qualquer aplicativo que suporte o formato <strong>iCal (RFC 5545)</strong>.<br>
        Gere um link secreto de sincronização - o calendário externo atualiza sozinho a cada intervalo configurado no aplicativo.
    </p>

    <div id="cal-token-section">
        <?php if (!empty($settings['calendar_token'])): ?>
        <!-- Token já existe -->
        <?php
            $calFeedUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . (dirname($_SERVER['SCRIPT_NAME'] ?? '/') !== '/' ? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') : '')
                . '/index.php?route=calendar_feed&token=' . urlencode((string)$settings['calendar_token']);
            $calWebcalUrl  = 'webcal://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . (dirname($_SERVER['SCRIPT_NAME'] ?? '/') !== '/' ? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') : '')
                . '/index.php?route=calendar_feed&token=' . urlencode((string)$settings['calendar_token']);
            $calGoogleUrl  = 'https://www.google.com/calendar/render?cid=' . urlencode($calWebcalUrl);
            $calExportUrl  = base_url('route=calendar&action=export');
        ?>
        <div style="background:#f0fdf4; border:1px solid #86efac; border-radius:10px; padding:14px; margin-bottom:14px;">
            <strong style="display:block; margin-bottom:6px; color:#166534;">OK. Sincronização ativa</strong>
            <div class="muted" style="margin-bottom:10px; font-size:12px;">
                Cole o link abaixo no seu aplicativo de calendário, ou use os botões de integração rápida.
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <input id="cal-feed-url" type="text" readonly
                    value="<?= e($calFeedUrl) ?>"
                    style="flex:1; min-width:200px; font-size:12px; font-family:monospace; background:#fff; border:1px solid #86efac; border-radius:8px; padding:7px 10px;">
                <button type="button" class="btn-secondary" onclick="copyCal()">Copiar link</button>
            </div>
        </div>

        <div class="row" style="flex-wrap:wrap; gap:10px; margin-bottom:14px;">
            <a href="<?= e($calGoogleUrl) ?>" target="_blank" rel="noopener"
               style="display:inline-flex; align-items:center; gap:6px; height:38px; padding:0 14px; background:#4285f4; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; cursor:pointer;">
                Adicionar no Google Agenda
            </a>
            <a href="<?= e($calWebcalUrl) ?>"
               style="display:inline-flex; align-items:center; gap:6px; height:38px; padding:0 14px; background:#0ea5e9; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; cursor:pointer;">
                Abrir no Apple/Outlook
            </a>
            <a href="<?= e($calExportUrl) ?>"
               style="display:inline-flex; align-items:center; gap:6px; height:38px; padding:0 14px; background:#64748b; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; cursor:pointer;">
                Baixar .ics (cópia instantânea)
            </a>
        </div>

        <div style="border-top:1px solid #e2e8f0; padding-top:12px;">
            <strong style="font-size:13px;">Revogar link de sincronização</strong>
            <p class="muted" style="margin:4px 0 10px; font-size:12px;">
                Revogar invalida o link atual imediatamente. Qualquer aplicativo que usava esse link deixará de receber atualizações.<br>
                Um novo link poderá ser gerado a qualquer momento.
            </p>
            <button type="button" id="cal-revoke-btn"
                    onclick="calGenerateToken('revoke')"
                    style="height:36px; padding:0 14px; background:#fff; color:#dc2626; border:1.5px solid #dc2626; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
                Revogar link
            </button>
        </div>

        <?php else: ?>
        <!-- Nenhum token -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:14px; text-align:center;">
            <div style="font-size:32px; margin-bottom:8px;">CAL</div>
            <strong>Nenhum link de sincronização ativo</strong>
            <p class="muted" style="margin:6px 0 12px; font-size:13px;">
                Gere um link secreto para conectar seu Google Agenda, Calendário Apple ou Outlook às suas consultas em tempo real.
            </p>
            <button type="button" id="cal-generate-btn"
                    onclick="calGenerateToken('generate')"
                    style="height:40px; padding:0 20px; font-size:14px; font-weight:600;">
                Gerar link de sincronização
            </button>
        </div>

        <div class="row" style="justify-content:center;">
            <a href="<?= e(base_url('route=calendar&action=export')) ?>"
               style="display:inline-flex; align-items:center; gap:6px; height:36px; padding:0 14px; background:#64748b; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; cursor:pointer;">
                Baixar .ics agora (cópia instantânea)
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div id="cal-feedback" style="display:none; margin-top:12px; padding:10px 12px; border-radius:8px; font-size:13px;"></div>
</div>

<div class="card" id="config-lgpd">
    <h3 class="card-title">14) Conformidade LGPD</h3>
    <?php
    $legalCompliance = $legalCompliance ?? ['up_to_date' => false, 'latest_consent' => null, 'required_versions' => ['terms_version' => 'v1.0', 'privacy_version' => 'v1.0']];
    $legalLinks = $legalLinks ?? ['terms_url' => base_url('route=legal&doc=terms'), 'privacy_url' => base_url('route=legal&doc=privacy')];
    $latestConsent = $legalCompliance['latest_consent'] ?? null;
    $requiredVersions = $legalCompliance['required_versions'] ?? ['terms_version' => 'v1.0', 'privacy_version' => 'v1.0'];
    ?>

    <div class="row" style="margin-bottom:10px;">
        <span class="chip" style="background:<?= !empty($legalCompliance['up_to_date']) ? '#dcfce7' : '#fef3c7' ?>; color:<?= !empty($legalCompliance['up_to_date']) ? '#166534' : '#92400e' ?>;">
            <?= !empty($legalCompliance['up_to_date']) ? 'Conformidade em dia' : 'Aceite pendente de atualização' ?>
        </span>
        <span class="muted">Termos: <?= e((string) ($requiredVersions['terms_version'] ?? 'v1.0')) ?> · Privacidade: <?= e((string) ($requiredVersions['privacy_version'] ?? 'v1.0')) ?></span>
    </div>

    <p class="muted" style="margin-bottom:12px;">
        Consulte os documentos públicos:
        <a href="<?= e((string) ($legalLinks['terms_url'] ?? base_url('route=legal&doc=terms'))) ?>" target="_blank" rel="noopener">Termos de Uso</a>
        e
        <a href="<?= e((string) ($legalLinks['privacy_url'] ?? base_url('route=legal&doc=privacy'))) ?>" target="_blank" rel="noopener">Política de Privacidade</a>.
    </p>

    <?php if (is_array($latestConsent)): ?>
        <div class="table-wrap" style="margin-bottom:12px;">
            <table style="min-width:620px;">
                <thead>
                    <tr>
                        <th>Último aceite</th>
                        <th>Termos</th>
                        <th>Privacidade</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) ($latestConsent['accepted_at'] ?? 'now')))) ?></td>
                        <td><?= e((string) ($latestConsent['terms_version'] ?? '-')) ?></td>
                        <td><?= e((string) ($latestConsent['privacy_version'] ?? '-')) ?></td>
                        <td><?= e((string) ($latestConsent['accepted_ip'] ?? '-')) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert" style="margin-bottom:12px;">Nenhum aceite legal registrado para este ambiente ainda.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('route=settings')) ?>" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="accept_legal">
        <button type="submit">Registrar aceite atual</button>
    </form>
</div>

<style>
.settings-quick-nav {
    border: 1px solid #bfd0e4;
    background: linear-gradient(180deg, #e6edf7, #dde6f2);
}

.settings-nav-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 8px;
}

.settings-nav-links a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    border-radius: 9px;
    border: 1px solid #b8c7da;
    background: #edf3fb;
    color: #1e3a8a;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
}

.settings-nav-links a:hover {
    background: #dbe7f7;
}

.tour-highlight {
    position: relative;
    z-index: 10001;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, .35), 0 8px 24px rgba(15, 23, 42, .2);
    border-radius: 10px;
    background: #fff;
}

#tour-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, .45);
    z-index: 10000;
}

#tour-popover {
    display: none;
    position: fixed;
    z-index: 10002;
    width: min(360px, calc(100vw - 24px));
    background: #fff;
    border: 1px solid #dbeafe;
    border-radius: 12px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, .28);
    padding: 12px;
}

#tour-popover .tour-title {
    font-weight: 700;
    margin-bottom: 6px;
}

#tour-popover .tour-text {
    color: #475569;
    font-size: 13px;
    margin-bottom: 10px;
}

#tour-popover .tour-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

#tour-popover .tour-actions button {
    height: 34px;
    padding: 6px 10px;
}

.template-preview {
    border: 1px solid #dbeafe;
    background: #f8fbff;
    color: #1e293b;
    border-radius: 10px;
    padding: 10px 12px;
    margin-top: 8px;
    font-size: 13px;
    line-height: 1.45;
    white-space: pre-wrap;
}

.template-preview.is-loading {
    color: #64748b;
    border-style: dashed;
}

.template-preview.is-error {
    border-color: #fecaca;
    background: #fff7f7;
    color: #991b1b;
}

.template-meta {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
}

.template-char-count {
    font-size: 12px;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 2px 9px;
}

.template-char-count.is-warn {
    color: #92400e;
    background: #fffbeb;
    border-color: #fcd34d;
}

.template-char-count.is-danger {
    color: #991b1b;
    background: #fef2f2;
    border-color: #fca5a5;
}

.template-token-check {
    font-size: 12px;
    color: #166534;
    background: #f0fdf4;
    border: 1px solid #86efac;
    border-radius: 999px;
    padding: 2px 9px;
}

.template-token-check.is-warn {
    color: #991b1b;
    background: #fef2f2;
    border-color: #fca5a5;
}

.template-invalid-alert {
    border: 1px solid #fca5a5;
    background: #fff1f2;
    color: #9f1239;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 13px;
    line-height: 1.4;
}

textarea.template-has-invalid-token {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, .12);
}

.template-token-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 10px;
    padding: 10px;
    margin: 10px 0 14px;
}

.template-token-toolbar .token-chip {
    min-height: 30px;
    padding: 5px 10px;
    font-size: 12px;
}

.template-token-toolbar .token-chip.is-active {
    border-color: #2563eb;
    color: #2563eb;
    background: #eff6ff;
}

.template-token-tooltip {
    width: 100%;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1e3a8a;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 12px;
    line-height: 1.4;
}

.template-shortcut-picker {
    position: fixed;
    z-index: 10020;
    width: min(360px, calc(100vw - 24px));
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, .25);
    padding: 10px;
}

.template-shortcut-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

#template-shortcut-search {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 7px 9px;
    margin-bottom: 8px;
    font-size: 12px;
}

.template-shortcut-list {
    max-height: 220px;
    overflow: auto;
    display: grid;
    gap: 6px;
}

.template-shortcut-item {
    width: 100%;
    text-align: left;
    border: 1px solid #dbeafe;
    background: #f8fbff;
    color: #1e293b;
    border-radius: 8px;
    padding: 6px 8px;
    cursor: pointer;
}

.template-shortcut-item.is-selected {
    border-color: #2563eb;
    background: #eff6ff;
    color: #1d4ed8;
}

.template-shortcut-item .token {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
}

.template-shortcut-item .desc {
    display: block;
    font-size: 11px;
    opacity: .85;
}
</style>

<div id="tour-overlay" onclick="endTour()"></div>
<div id="tour-popover">
    <div class="tour-title" id="tour-title"></div>
    <div class="tour-text" id="tour-text"></div>
    <div class="tour-actions">
        <button type="button" class="btn-secondary" id="tour-prev" onclick="prevTourStep()">Voltar</button>
        <button type="button" id="tour-next" onclick="nextTourStep()">Próximo</button>
        <button type="button" class="btn-secondary" onclick="endTour()">Fechar</button>
    </div>
</div>

<script>
function copyText(text, button) {
    if (!navigator.clipboard) {
        return;
    }

    navigator.clipboard.writeText(text).then(function () {
        const original = button.textContent;
        button.textContent = 'Copiado!';
        setTimeout(function () {
            button.textContent = original;
        }, 1200);
    });
}

function openHelpModal() {
    var modal = document.getElementById('meta-help-modal');
    if (modal) {
        modal.style.display = 'block';
    }
}

function closeHelpModal() {
    var modal = document.getElementById('meta-help-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

window.addEventListener('click', function (event) {
    var modal = document.getElementById('meta-help-modal');
    if (modal && event.target === modal) {
        closeHelpModal();
    }
});

var tourIndex = -1;
var tourSteps = [
    {
        selector: '#tour-step-1',
        title: 'Passo 1 de 4',
        text: 'Cole aqui o ID do número de telefone que você copiou da Meta.'
    },
    {
        selector: '#tour-step-2',
        title: 'Passo 2 de 4',
        text: 'Cole aqui o token de acesso da Meta.'
    },
    {
        selector: '#tour-step-3',
        title: 'Passo 3 de 4',
        text: 'Copie a URL de retorno e o token de verificação para configurar o webhook na Meta.'
    },
    {
        selector: '#tour-step-4',
        title: 'Passo 4 de 4',
        text: 'Informe seu número e clique em "Salvar e testar envio". Se chegar mensagem, está tudo pronto.'
    }
];

function startTour() {
    tourIndex = 0;
    document.getElementById('tour-overlay').style.display = 'block';
    document.getElementById('tour-popover').style.display = 'block';
    renderTourStep();
}

function renderTourStep() {
    clearTourHighlight();

    var step = tourSteps[tourIndex];
    var element = document.querySelector(step.selector);
    if (!element) {
        endTour();
        return;
    }

    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    element.classList.add('tour-highlight');

    var popover = document.getElementById('tour-popover');
    document.getElementById('tour-title').textContent = step.title;
    document.getElementById('tour-text').textContent = step.text;

    var rect = element.getBoundingClientRect();
    var top = rect.bottom + 10;
    var left = rect.left;

    if (top + popover.offsetHeight > window.innerHeight - 8) {
        top = Math.max(8, rect.top - popover.offsetHeight - 10);
    }
    if (left + popover.offsetWidth > window.innerWidth - 8) {
        left = Math.max(8, window.innerWidth - popover.offsetWidth - 8);
    }

    popover.style.top = top + 'px';
    popover.style.left = left + 'px';

    document.getElementById('tour-prev').style.display = tourIndex === 0 ? 'none' : 'inline-flex';
    document.getElementById('tour-next').textContent = tourIndex === (tourSteps.length - 1) ? 'Concluir' : 'Próximo';
}

function nextTourStep() {
    if (tourIndex >= tourSteps.length - 1) {
        endTour();
        return;
    }

    tourIndex += 1;
    renderTourStep();
}

function prevTourStep() {
    if (tourIndex <= 0) {
        return;
    }

    tourIndex -= 1;
    renderTourStep();
}

function endTour() {
    tourIndex = -1;
    clearTourHighlight();
    var overlay = document.getElementById('tour-overlay');
    var popover = document.getElementById('tour-popover');
    if (overlay) {
        overlay.style.display = 'none';
    }
    if (popover) {
        popover.style.display = 'none';
    }
}

function clearTourHighlight() {
    var highlighted = document.querySelectorAll('.tour-highlight');
    for (var i = 0; i < highlighted.length; i += 1) {
        highlighted[i].classList.remove('tour-highlight');
    }
}

var templatePreviewUrl = '<?= e(base_url('route=settings')) ?>';
var templatesForm = document.getElementById('templates-form');
var previewTokenField = templatesForm ? templatesForm.querySelector('input[name="csrf_token"]') : null;
var templatePreviewToken = previewTokenField ? previewTokenField.value : '';
var templatePreviewTimers = {};
var templatePreviewRequestSeq = {};
var activeTemplateTextarea = null;
var templateWarnThreshold = 280;
var templateDangerThreshold = 500;
var templateValidationState = {};
var templateShortcutItems = [];
var templateShortcutSelectedIndex = 0;
var templateTokenHelp = {
    '{{nome}}': { label: 'Nome do paciente', example: 'Maria Oliveira' },
    '{{data_hora}}': { label: 'Data e hora da consulta', example: '25/03/2026 14:30' },
    '{{procedimento}}': { label: 'Procedimento da consulta', example: 'Avaliação odontológica' },
    '{{status}}': { label: 'Status da consulta', example: 'confirmada' },
    '{{clinica}}': { label: 'Nome da clínica', example: 'Clínica Sorriso Feliz' },
    '{{telefone_clinica}}': { label: 'Telefone da clínica', example: '(11) 98888-7777' },
    '{{telefone}}': { label: 'Atalho do telefone da clínica', example: '(11) 98888-7777' },
    '{{endereco_clinica}}': { label: 'Endereço da clínica', example: 'Rua das Flores, 100' },
    '{{endereco}}': { label: 'Atalho do endereço da clínica', example: 'Rua das Flores, 100' },
    '{{email_clinica}}': { label: 'E-mail da clínica', example: 'contato@clinica.com' }
};
var allowedTemplateTokens = [
    '{{nome}}',
    '{{data_hora}}',
    '{{procedimento}}',
    '{{status}}',
    '{{clinica}}',
    '{{telefone_clinica}}',
    '{{telefone}}',
    '{{endereco_clinica}}',
    '{{endereco}}',
    '{{email_clinica}}'
];

function normalizeTemplateToken(token) {
    return (token || '').toLowerCase().replace(/\s+/g, '');
}

function findCanonicalTemplateToken(token) {
    var normalized = normalizeTemplateToken(token);
    for (var i = 0; i < allowedTemplateTokens.length; i += 1) {
        if (normalizeTemplateToken(allowedTemplateTokens[i]) === normalized) {
            return allowedTemplateTokens[i];
        }
    }

    return null;
}

function getClosestTemplateToken(token) {
    var normalizedToken = normalizeTemplateToken(token);
    var best = null;
    var bestDistance = 999;

    for (var i = 0; i < allowedTemplateTokens.length; i += 1) {
        var candidate = allowedTemplateTokens[i];
        var distance = levenshteinDistance(normalizedToken, normalizeTemplateToken(candidate));
        if (distance < bestDistance) {
            bestDistance = distance;
            best = candidate;
        }
    }

    if (bestDistance <= 4) {
        return best;
    }

    return null;
}

function levenshteinDistance(a, b) {
    if (a === b) {
        return 0;
    }

    var aLen = a.length;
    var bLen = b.length;
    if (aLen === 0) {
        return bLen;
    }
    if (bLen === 0) {
        return aLen;
    }

    var matrix = [];
    for (var i = 0; i <= bLen; i += 1) {
        matrix[i] = [i];
    }
    for (var j = 0; j <= aLen; j += 1) {
        matrix[0][j] = j;
    }

    for (var row = 1; row <= bLen; row += 1) {
        for (var col = 1; col <= aLen; col += 1) {
            var cost = b.charAt(row - 1) === a.charAt(col - 1) ? 0 : 1;
            matrix[row][col] = Math.min(
                matrix[row - 1][col] + 1,
                matrix[row][col - 1] + 1,
                matrix[row - 1][col - 1] + cost
            );
        }
    }

    return matrix[bLen][aLen];
}

function setPreviewContent(key, text, mode) {
    var box = document.querySelector('.template-preview[data-preview-for="' + key + '"]');
    if (!box) {
        return;
    }

    box.classList.remove('is-loading');
    box.classList.remove('is-error');
    if (mode === 'loading') {
        box.classList.add('is-loading');
    }
    if (mode === 'error') {
        box.classList.add('is-error');
    }

    box.textContent = text;
}

function updateTemplateCharCount(textarea) {
    var key = textarea.getAttribute('data-template-key') || '';
    if (!key) {
        return;
    }

    var badge = document.querySelector('.template-char-count[data-char-count-for="' + key + '"]');
    if (!badge) {
        return;
    }

    var length = (textarea.value || '').length;
    badge.classList.remove('is-warn');
    badge.classList.remove('is-danger');

    var levelText = 'Tamanho ideal';
    if (length >= templateDangerThreshold) {
        badge.classList.add('is-danger');
        levelText = 'Mensagem longa';
    } else if (length >= templateWarnThreshold) {
        badge.classList.add('is-warn');
        levelText = 'Atenção ao tamanho';
    }

    badge.textContent = length + ' caracteres | ' + levelText;
}

function updateTemplateTokenCheck(textarea) {
    var key = textarea.getAttribute('data-template-key') || '';
    if (!key) {
        return;
    }

    var badge = document.querySelector('.template-token-check[data-token-check-for="' + key + '"]');
    if (!badge) {
        return;
    }

    var text = textarea.value || '';
    var regex = /{{\s*([^{}]+?)\s*}}/g;
    var found = [];
    var match;
    while ((match = regex.exec(text)) !== null) {
        found.push('{{' + String(match[1] || '').trim() + '}}');
    }

    var unknown = [];
    var unknownMap = {};
    for (var i = 0; i < found.length; i += 1) {
        var token = found[i];
        var normalized = normalizeTemplateToken(token);
        var isKnown = false;
        for (var j = 0; j < allowedTemplateTokens.length; j += 1) {
            if (normalizeTemplateToken(allowedTemplateTokens[j]) === normalized) {
                isKnown = true;
                break;
            }
        }

        if (!isKnown && !unknownMap[normalized]) {
            unknownMap[normalized] = true;
            unknown.push(token);
        }
    }

    badge.classList.remove('is-warn');
    textarea.classList.remove('template-has-invalid-token');

    if (unknown.length === 0) {
        badge.textContent = 'Variáveis válidas';
        templateValidationState[key] = {
            invalid: false,
            message: ''
        };
        updateTemplatesInvalidAlert();
        return;
    }

    badge.classList.add('is-warn');
    textarea.classList.add('template-has-invalid-token');
    var firstUnknown = unknown[0];
    var suggestion = getClosestTemplateToken(firstUnknown);
    var suggestionText = suggestion ? (' | talvez ' + suggestion) : '';
    badge.textContent = 'Variável inválida: ' + firstUnknown + suggestionText;

    templateValidationState[key] = {
        invalid: true,
        message: 'Campo "' + getTemplateFieldLabel(textarea) + '": ' + firstUnknown + suggestionText
    };
    updateTemplatesInvalidAlert();
}

function getTemplateFieldLabel(textarea) {
    var field = textarea.closest('.field');
    var labelEl = field ? field.querySelector('label') : null;
    return labelEl ? labelEl.textContent : 'Template';
}

function updateTemplatesInvalidAlert() {
    var alertBox = document.getElementById('templates-invalid-alert');
    if (!alertBox) {
        return;
    }

    var messages = [];
    var keys = Object.keys(templateValidationState);
    for (var i = 0; i < keys.length; i += 1) {
        var state = templateValidationState[keys[i]];
        if (state && state.invalid && state.message) {
            messages.push(state.message);
        }
    }

    if (messages.length === 0) {
        alertBox.style.display = 'none';
        alertBox.textContent = '';
        return;
    }

    alertBox.style.display = 'block';
    alertBox.textContent = 'Corrija as variáveis inválidas antes de salvar: ' + messages.join(' | ');
}

function hasInvalidTemplateTokens() {
    var keys = Object.keys(templateValidationState);
    for (var i = 0; i < keys.length; i += 1) {
        var state = templateValidationState[keys[i]];
        if (state && state.invalid) {
            return true;
        }
    }

    return false;
}

function queueTemplatePreview(textarea) {
    var key = textarea.getAttribute('data-template-key') || '';
    if (!key) {
        return;
    }

    if (templatePreviewTimers[key]) {
        clearTimeout(templatePreviewTimers[key]);
    }

    setPreviewContent(key, 'Atualizando pré-visualização...', 'loading');

    templatePreviewTimers[key] = setTimeout(function () {
        requestTemplatePreview(key, textarea.value);
    }, 220);
}

function requestTemplatePreview(key, templateText) {
    if (!templatePreviewToken) {
        setPreviewContent(key, 'Token de sessão indisponível para pré-visualização.', 'error');
        return;
    }

    var requestSeq = (templatePreviewRequestSeq[key] || 0) + 1;
    templatePreviewRequestSeq[key] = requestSeq;

    var payload = new URLSearchParams();
    payload.append('action', 'preview_template');
    payload.append('csrf_token', templatePreviewToken);
    payload.append('template_key', key);
    payload.append('template_text', templateText);

    fetch(templatePreviewUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: payload.toString()
    })
        .then(function (response) {
            return response.json().catch(function () {
                return { success: false, message: 'Resposta inválida do servidor.' };
            });
        })
        .then(function (data) {
            if (templatePreviewRequestSeq[key] !== requestSeq) {
                return;
            }

            if (!data || data.success !== true) {
                var message = data && data.message ? data.message : 'Não foi possível gerar a pré-visualização.';
                setPreviewContent(key, message, 'error');
                return;
            }

            setPreviewContent(key, data.rendered || '(pré-visualização vazia)', 'ready');
        })
        .catch(function () {
            if (templatePreviewRequestSeq[key] !== requestSeq) {
                return;
            }

            setPreviewContent(key, 'Erro de conexão ao gerar pré-visualização.', 'error');
        });
}

function initTemplatePreviews() {
    var textareas = document.querySelectorAll('textarea[data-template-preview="1"]');
    for (var i = 0; i < textareas.length; i += 1) {
        (function () {
            var textarea = textareas[i];
            textarea.addEventListener('focus', function () {
                activeTemplateTextarea = textarea;
                updateTemplateTokenToolbarState();
            });
            textarea.addEventListener('click', function () {
                activeTemplateTextarea = textarea;
                updateTemplateTokenToolbarState();
            });
            textarea.addEventListener('input', function () {
                updateTemplateCharCount(textarea);
                updateTemplateTokenCheck(textarea);
                queueTemplatePreview(textarea);
            });
            textarea.addEventListener('keydown', function (event) {
                if (event.ctrlKey && event.code === 'Space') {
                    event.preventDefault();
                    openTemplateShortcutPicker(textarea);
                    return;
                }

                if (!isTemplateShortcutPickerOpen()) {
                    return;
                }

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    moveTemplateShortcutSelection(1);
                    return;
                }
                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    moveTemplateShortcutSelection(-1);
                    return;
                }
                if (event.key === 'Enter') {
                    event.preventDefault();
                    insertSelectedTemplateShortcut();
                    return;
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeTemplateShortcutPicker();
                }
            });

            updateTemplateCharCount(textarea);
            updateTemplateTokenCheck(textarea);
            queueTemplatePreview(textarea);
        })();
    }

    if (textareas.length > 0) {
        activeTemplateTextarea = textareas[0];
        updateTemplateTokenToolbarState();
    }
}

function updateTemplateTokenToolbarState() {
    var chips = document.querySelectorAll('.template-token-toolbar .token-chip');
    for (var i = 0; i < chips.length; i += 1) {
        chips[i].classList.remove('is-active');
    }

    var hint = document.getElementById('template-token-hint');
    if (!hint) {
        return;
    }

    if (!activeTemplateTextarea) {
        hint.textContent = 'Clique em um campo de template e depois em uma variável.';
        return;
    }

    var label = activeTemplateTextarea.closest('.field');
    var labelEl = label ? label.querySelector('label') : null;
    var labelText = labelEl ? labelEl.textContent : 'template';
    hint.textContent = 'Inserindo no campo: ' + labelText;
}

function insertTokenIntoActiveTemplate(token) {
    if (!activeTemplateTextarea) {
        return;
    }

    var textarea = activeTemplateTextarea;
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    if (typeof start !== 'number' || typeof end !== 'number') {
        textarea.value += token;
    } else {
        var before = textarea.value.substring(0, start);
        var after = textarea.value.substring(end);
        textarea.value = before + token + after;
        var cursorPos = start + token.length;
        textarea.selectionStart = cursorPos;
        textarea.selectionEnd = cursorPos;
    }

    textarea.focus();
    updateTemplateCharCount(textarea);
    updateTemplateTokenCheck(textarea);
    queueTemplatePreview(textarea);
}

function initTemplateTokenToolbar() {
    var chips = document.querySelectorAll('.template-token-toolbar .token-chip');
    for (var i = 0; i < chips.length; i += 1) {
        chips[i].addEventListener('click', function () {
            var token = this.getAttribute('data-template-token') || '';
            if (token === '') {
                return;
            }

            insertTokenIntoActiveTemplate(token);
            this.classList.add('is-active');
            var self = this;
            setTimeout(function () {
                self.classList.remove('is-active');
            }, 260);
        });

        chips[i].addEventListener('mouseenter', function () {
            showTemplateTokenTooltip(this.getAttribute('data-template-token') || '');
        });

        chips[i].addEventListener('focus', function () {
            showTemplateTokenTooltip(this.getAttribute('data-template-token') || '');
        });

        chips[i].addEventListener('mouseleave', function () {
            hideTemplateTokenTooltip();
        });

        chips[i].addEventListener('blur', function () {
            hideTemplateTokenTooltip();
        });
    }
}

function showTemplateTokenTooltip(token) {
    var tooltip = document.getElementById('template-token-tooltip');
    if (!tooltip) {
        return;
    }

    var help = templateTokenHelp[token] || null;
    if (!help) {
        tooltip.style.display = 'none';
        tooltip.textContent = '';
        return;
    }

    tooltip.style.display = 'block';
    tooltip.textContent = token + ' - ' + help.label + '. Exemplo: ' + help.example;
}

function hideTemplateTokenTooltip() {
    var tooltip = document.getElementById('template-token-tooltip');
    if (!tooltip) {
        return;
    }

    tooltip.style.display = 'none';
    tooltip.textContent = '';
}

function normalizeTemplateTokens(text) {
    if (!text) {
        return text;
    }

    return text.replace(/{{\s*([^{}]+?)\s*}}/g, function (_match, tokenName) {
        var parsedToken = '{{' + String(tokenName || '').trim() + '}}';
        var canonical = findCanonicalTemplateToken(parsedToken);
        return canonical || parsedToken;
    });
}

function refreshTemplateField(textarea) {
    if (!textarea) {
        return;
    }

    updateTemplateCharCount(textarea);
    updateTemplateTokenCheck(textarea);
    queueTemplatePreview(textarea);
}

function normalizeActiveTemplateField() {
    if (!activeTemplateTextarea) {
        return;
    }

    var original = activeTemplateTextarea.value || '';
    var normalized = normalizeTemplateTokens(original);
    if (normalized === original) {
        return;
    }

    activeTemplateTextarea.value = normalized;
    refreshTemplateField(activeTemplateTextarea);
}

function normalizeAllTemplateFields() {
    var textareas = document.querySelectorAll('textarea[data-template-preview="1"]');
    for (var i = 0; i < textareas.length; i += 1) {
        var textarea = textareas[i];
        var original = textarea.value || '';
        var normalized = normalizeTemplateTokens(original);
        if (normalized !== original) {
            textarea.value = normalized;
        }

        refreshTemplateField(textarea);
    }
}

function initTemplateNormalizationActions() {
    var normalizeActiveButton = document.getElementById('normalize-active-template-btn');
    if (normalizeActiveButton) {
        normalizeActiveButton.addEventListener('click', function () {
            normalizeActiveTemplateField();
        });
    }

    var normalizeAllButton = document.getElementById('normalize-all-templates-btn');
    if (normalizeAllButton) {
        normalizeAllButton.addEventListener('click', function () {
            normalizeAllTemplateFields();
        });
    }
}

function initTemplatesFormGuard() {
    if (!templatesForm) {
        return;
    }

    templatesForm.addEventListener('submit', function (event) {
        var textareas = templatesForm.querySelectorAll('textarea[data-template-preview="1"]');
        for (var i = 0; i < textareas.length; i += 1) {
            updateTemplateTokenCheck(textareas[i]);
        }

        if (!hasInvalidTemplateTokens()) {
            return;
        }

        event.preventDefault();
        updateTemplatesInvalidAlert();

        var firstInvalid = templatesForm.querySelector('textarea.template-has-invalid-token');
        if (firstInvalid) {
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
}

function isTemplateShortcutPickerOpen() {
    var picker = document.getElementById('template-shortcut-picker');
    return !!picker && picker.style.display !== 'none';
}

function positionTemplateShortcutPicker(textarea) {
    var picker = document.getElementById('template-shortcut-picker');
    if (!picker || !textarea) {
        return;
    }

    var rect = textarea.getBoundingClientRect();
    var top = rect.bottom + 8;
    var left = rect.left;

    if (top + 280 > window.innerHeight - 8) {
        top = Math.max(8, rect.top - 290);
    }
    if (left + 370 > window.innerWidth - 8) {
        left = Math.max(8, window.innerWidth - 370);
    }

    picker.style.top = top + 'px';
    picker.style.left = left + 'px';
}

function openTemplateShortcutPicker(textarea) {
    if (!textarea) {
        return;
    }

    activeTemplateTextarea = textarea;
    var picker = document.getElementById('template-shortcut-picker');
    var search = document.getElementById('template-shortcut-search');
    if (!picker || !search) {
        return;
    }

    picker.style.display = 'block';
    positionTemplateShortcutPicker(textarea);
    search.value = '';
    renderTemplateShortcutItems('');
    search.focus();
}

function closeTemplateShortcutPicker() {
    var picker = document.getElementById('template-shortcut-picker');
    if (!picker) {
        return;
    }

    picker.style.display = 'none';
    templateShortcutItems = [];
    templateShortcutSelectedIndex = 0;
    if (activeTemplateTextarea) {
        activeTemplateTextarea.focus();
    }
}

function renderTemplateShortcutItems(filterText) {
    var list = document.getElementById('template-shortcut-list');
    if (!list) {
        return;
    }

    var search = (filterText || '').toLowerCase().trim();
    templateShortcutItems = [];
    for (var i = 0; i < allowedTemplateTokens.length; i += 1) {
        var token = allowedTemplateTokens[i];
        var help = templateTokenHelp[token] || { label: '', example: '' };
        var searchable = (token + ' ' + help.label + ' ' + help.example).toLowerCase();
        if (search === '' || searchable.indexOf(search) !== -1) {
            templateShortcutItems.push(token);
        }
    }

    if (templateShortcutItems.length === 0) {
        list.innerHTML = '<div class="muted" style="padding:4px 2px;">Nenhuma variável encontrada.</div>';
        return;
    }

    if (templateShortcutSelectedIndex >= templateShortcutItems.length) {
        templateShortcutSelectedIndex = 0;
    }
    if (templateShortcutSelectedIndex < 0) {
        templateShortcutSelectedIndex = templateShortcutItems.length - 1;
    }

    var html = '';
    for (var j = 0; j < templateShortcutItems.length; j += 1) {
        var itemToken = templateShortcutItems[j];
        var itemHelp = templateTokenHelp[itemToken] || { label: '', example: '' };
        var selectedClass = j === templateShortcutSelectedIndex ? ' is-selected' : '';
        html += '<button type="button" class="template-shortcut-item' + selectedClass + '" data-shortcut-token="' + itemToken + '">'
            + '<span class="token">' + itemToken + '</span>'
            + '<span class="desc">' + itemHelp.label + ' | Ex: ' + itemHelp.example + '</span>'
            + '</button>';
    }
    list.innerHTML = html;

    var buttons = list.querySelectorAll('.template-shortcut-item');
    for (var k = 0; k < buttons.length; k += 1) {
        buttons[k].addEventListener('click', function () {
            var token = this.getAttribute('data-shortcut-token') || '';
            if (token !== '') {
                insertTokenIntoActiveTemplate(token);
            }
            closeTemplateShortcutPicker();
        });
    }
}

function moveTemplateShortcutSelection(delta) {
    if (templateShortcutItems.length === 0) {
        return;
    }

    templateShortcutSelectedIndex += delta;
    if (templateShortcutSelectedIndex < 0) {
        templateShortcutSelectedIndex = templateShortcutItems.length - 1;
    }
    if (templateShortcutSelectedIndex >= templateShortcutItems.length) {
        templateShortcutSelectedIndex = 0;
    }

    var search = document.getElementById('template-shortcut-search');
    renderTemplateShortcutItems(search ? search.value : '');
}

function insertSelectedTemplateShortcut() {
    if (templateShortcutItems.length === 0) {
        return;
    }

    var token = templateShortcutItems[templateShortcutSelectedIndex] || '';
    if (token !== '') {
        insertTokenIntoActiveTemplate(token);
    }
    closeTemplateShortcutPicker();
}

function initTemplateShortcutPicker() {
    var search = document.getElementById('template-shortcut-search');
    if (search) {
        search.addEventListener('input', function () {
            templateShortcutSelectedIndex = 0;
            renderTemplateShortcutItems(search.value || '');
        });

        search.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                moveTemplateShortcutSelection(1);
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                moveTemplateShortcutSelection(-1);
                return;
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                insertSelectedTemplateShortcut();
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                closeTemplateShortcutPicker();
            }
        });
    }

    document.addEventListener('click', function (event) {
        if (!isTemplateShortcutPickerOpen()) {
            return;
        }

        var picker = document.getElementById('template-shortcut-picker');
        if (!picker) {
            return;
        }

        if (picker.contains(event.target)) {
            return;
        }

        closeTemplateShortcutPicker();
    });

    window.addEventListener('resize', function () {
        if (isTemplateShortcutPickerOpen() && activeTemplateTextarea) {
            positionTemplateShortcutPicker(activeTemplateTextarea);
        }
    });
}

initTemplatePreviews();
initTemplateTokenToolbar();
initTemplatesFormGuard();
initTemplateNormalizationActions();
initTemplateShortcutPicker();

// Calendário
var calendarUrl = '<?= e(base_url('route=calendar')) ?>';
var calCsrfToken = (function () {
    var f = document.querySelector('#calendar-integration input[name="csrf_token"]');
    if (f) { return f.value; }
    // fallback: pegar o primeiro csrf_token da página
    var anyF = document.querySelector('input[name="csrf_token"]');
    return anyF ? anyF.value : '';
})();

function copyCal() {
    var input = document.getElementById('cal-feed-url');
    if (!input) { return; }
    if (navigator.clipboard) {
        navigator.clipboard.writeText(input.value).then(function () {
            var btn = document.querySelector('#calendar-integration .btn-secondary[onclick="copyCal()"]');
            if (btn) {
                var orig = btn.textContent;
                btn.textContent = 'Copiado!';
                setTimeout(function () { btn.textContent = orig; }, 1400);
            }
        });
    } else {
        input.select();
        document.execCommand('copy');
    }
}

function calShowFeedback(msg, isError) {
    var box = document.getElementById('cal-feedback');
    if (!box) { return; }
    box.style.display = 'block';
    box.style.background = isError ? '#fef2f2' : '#f0fdf4';
    box.style.border = '1px solid ' + (isError ? '#fca5a5' : '#86efac');
    box.style.color = isError ? '#991b1b' : '#166534';
    box.textContent = msg;
    setTimeout(function () { box.style.display = 'none'; }, 4000);
}

function calSetBusy(btn, busy) {
    if (!btn) { return; }
    btn.disabled = busy;
    btn.style.opacity = busy ? '0.6' : '1';
}

function calGenerateToken(mode) {
    var confirmMsg = mode === 'revoke'
        ? 'Revogar o link atual? Todos os apps que usavam esse link perderão a sincronização.'
        : 'Gerar link de sincronização?';
    if (!confirm(confirmMsg)) { return; }

    var btn = document.getElementById(mode === 'revoke' ? 'cal-revoke-btn' : 'cal-generate-btn');
    calSetBusy(btn, true);

    // Obter token CSRF atualizado da sessão
    var currentCsrf = document.querySelector('input[name="csrf_token"]');
    var csrfVal = currentCsrf ? currentCsrf.value : calCsrfToken;

    var payload = new URLSearchParams();
    payload.append('action', mode === 'revoke' ? 'revoke' : 'generate');
    payload.append('csrf_token', csrfVal);

    fetch(calendarUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: payload.toString()
    })
    .then(function (r) { return r.json().catch(function () { return { success: false, message: 'Erro de resposta.' }; }); })
    .then(function (data) {
        calSetBusy(btn, false);
        if (!data || data.success !== true) {
            calShowFeedback(data.message || 'Erro ao processar.', true);
            return;
        }
        // Recarregar a página para refletir o novo estado
        window.location.href = window.location.pathname + window.location.search + '#calendar-integration';
        window.location.reload();
    })
    .catch(function () {
        calSetBusy(btn, false);
        calShowFeedback('Erro de conexão. Tente novamente.', true);
    });
}
</script>



