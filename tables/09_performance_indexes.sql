-- Migration 09: índices de performance e idempotência
-- Execute este script UMA vez após as migrations 01-08.

USE atendy;

-- �"?�"? whatsapp_messages: índice único para idempotência de mensagens recebidas �"?�"?
-- Evita processar o mesmo message_id externo duas vezes no webhook.
ALTER TABLE whatsapp_messages
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
    ADD UNIQUE KEY IF NOT EXISTS uniq_messages_external_id (external_message_id);

-- �"?�"? whatsapp_messages: busca por paciente/direção �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
ALTER TABLE whatsapp_messages
    ADD INDEX IF NOT EXISTS idx_messages_patient_direction (patient_id, direction);

-- �"?�"? automation_logs: busca por consulta + tipo (anti-duplicidade) �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
ALTER TABLE automation_logs
    ADD INDEX IF NOT EXISTS idx_automation_appt_tipo (appointment_id, tipo_automacao);

-- �"?�"? automation_logs: busca por usuário + tipo + timestamp (inatividade) �"?�"?�"?�"?�"?�"?�"?
ALTER TABLE automation_logs
    ADD INDEX IF NOT EXISTS idx_automation_user_tipo_ts (user_id, tipo_automacao, timestamp);

-- �"?�"? patients: busca por status (leads vs ativos) �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
ALTER TABLE patients
    ADD INDEX IF NOT EXISTS idx_patients_user_status (user_id, status);

-- �"?�"? appointments: status + data_hora (automações de confirmação/lembrete) �"?�"?�"?�"?�"?
ALTER TABLE appointments
    ADD INDEX IF NOT EXISTS idx_appt_user_status_dt (user_id, status, data_hora);

-- �"?�"? password_resets: busca por token (reset de senha) �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
ALTER TABLE password_resets
    ADD INDEX IF NOT EXISTS idx_pr_token_hash (token_hash);

-- �"?�"? login_attempts: busca por IP + timestamp (rate limit) �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
ALTER TABLE login_attempts
    ADD INDEX IF NOT EXISTS idx_login_ip_ts (ip_address, attempted_at);


