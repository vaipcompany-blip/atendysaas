-- Migration 10: templates de automação no settings
-- Executar após as migrations 01-09.

USE atendy;

ALTER TABLE settings
    ADD COLUMN IF NOT EXISTS template_lembrete_12h TEXT NULL AFTER mensagem_confirmacao,
    ADD COLUMN IF NOT EXISTS template_lembrete_2h TEXT NULL AFTER template_lembrete_12h,
    ADD COLUMN IF NOT EXISTS template_followup_falta TEXT NULL AFTER template_lembrete_2h,
    ADD COLUMN IF NOT EXISTS template_followup_cancelamento TEXT NULL AFTER template_followup_falta,
    ADD COLUMN IF NOT EXISTS template_followup_inatividade TEXT NULL AFTER template_followup_cancelamento;

UPDATE settings
SET
    mensagem_confirmacao = COALESCE(NULLIF(TRIM(mensagem_confirmacao), ''), 'Olá {{nome}}! Sua consulta será em {{data_hora}}. Responda SIM para confirmar.'),
    template_lembrete_12h = COALESCE(NULLIF(TRIM(template_lembrete_12h), ''), 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 12 horas. Data: {{data_hora}}'),
    template_lembrete_2h = COALESCE(NULLIF(TRIM(template_lembrete_2h), ''), 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 2 horas. Data: {{data_hora}}'),
    template_followup_falta = COALESCE(NULLIF(TRIM(template_followup_falta), ''), 'Oi {{nome}}! Sentimos sua falta na consulta. Quer reagendar?'),
    template_followup_cancelamento = COALESCE(NULLIF(TRIM(template_followup_cancelamento), ''), 'Olá {{nome}}! Podemos te ajudar a remarcar sua consulta?'),
    template_followup_inatividade = COALESCE(NULLIF(TRIM(template_followup_inatividade), ''), 'Oi {{nome}}! Faz um tempo que você não agenda consulta. Quer ver horários disponíveis?')
WHERE user_id > 0;


