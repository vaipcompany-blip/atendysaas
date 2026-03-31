-- Migration 04: estado conversacional por paciente
-- Guarda em qual etapa do funil o paciente est� e o payload necess�rio
-- para retomar a conversa na pr�xima mensagem recebida.
CREATE TABLE IF NOT EXISTS whatsapp_conversation_state (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    patient_id  INT UNSIGNED NOT NULL,
    state       VARCHAR(60)  NOT NULL COMMENT 'Ex: awaiting_slot_choice, awaiting_reschedule_choice',
    payload     TEXT         NULL     COMMENT 'JSON com dados de contexto (ex: slots oferecidos)',
    expires_at  DATETIME     NOT NULL COMMENT 'Estado expira e � ignorado ap�s este momento',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL,
    UNIQUE KEY uniq_conv_state_patient (user_id, patient_id),
    KEY idx_conv_state_expires (expires_at),
    CONSTRAINT fk_conv_state_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_conv_state_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


