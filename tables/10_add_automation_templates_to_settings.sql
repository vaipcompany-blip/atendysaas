-- Migration 10: templates de automa��o no settings
-- Executar ap�s as migrations 01-09.
ALTER TABLE settings
    ADD COLUMN IF NOT EXISTS template_lembrete_12h TEXT NULL AFTER mensagem_confirmacao,
    ADD COLUMN IF NOT EXISTS template_lembrete_2h TEXT NULL AFTER template_lembrete_12h,
    ADD COLUMN IF NOT EXISTS template_followup_falta TEXT NULL AFTER template_lembrete_2h,
    ADD COLUMN IF NOT EXISTS template_followup_cancelamento TEXT NULL AFTER template_followup_falta,
    ADD COLUMN IF NOT EXISTS template_followup_inatividade TEXT NULL AFTER template_followup_cancelamento;

UPDATE settings
SET
    mensagem_confirmacao = COALESCE(NULLIF(TRIM(mensagem_confirmacao), ''), 'Ol� {{nome}}! Sua consulta ser� em {{data_hora}}. Responda SIM para confirmar.'),
    template_lembrete_12h = COALESCE(NULLIF(TRIM(template_lembrete_12h), ''), 'Ol� {{nome}}! Lembrete: sua consulta � em cerca de 12 horas. Data: {{data_hora}}'),
    template_lembrete_2h = COALESCE(NULLIF(TRIM(template_lembrete_2h), ''), 'Ol� {{nome}}! Lembrete: sua consulta � em cerca de 2 horas. Data: {{data_hora}}'),
    template_followup_falta = COALESCE(NULLIF(TRIM(template_followup_falta), ''), 'Oi {{nome}}! Sentimos sua falta na consulta. Quer reagendar?'),
    template_followup_cancelamento = COALESCE(NULLIF(TRIM(template_followup_cancelamento), ''), 'Ol� {{nome}}! Podemos te ajudar a remarcar sua consulta?'),
    template_followup_inatividade = COALESCE(NULLIF(TRIM(template_followup_inatividade), ''), 'Oi {{nome}}! Faz um tempo que voc� n�o agenda consulta. Quer ver hor�rios dispon�veis?')
WHERE user_id > 0;


