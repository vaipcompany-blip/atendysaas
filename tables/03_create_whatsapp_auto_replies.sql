-- Migration 03: tabela de respostas autom�ticas personalizadas por usu�rio
-- Permite que cada dentista cadastre suas pr�prias palavras-chave e respostas
-- para o funil de atendimento via WhatsApp.
CREATE TABLE IF NOT EXISTS whatsapp_auto_replies (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    keyword      VARCHAR(100) NOT NULL COMMENT 'Palavra-chave ou n�mero que o paciente envia (ex: 1, agendar, duvida)',
    reply        TEXT        NOT NULL COMMENT 'Texto que ser� enviado automaticamente em resposta',
    is_active    TINYINT(1)  NOT NULL DEFAULT 1,
    sort_order   SMALLINT    NOT NULL DEFAULT 0 COMMENT 'Ordem de exibi��o na interface e de verifica��o',
    created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME    NULL,
    KEY idx_auto_replies_user (user_id),
    UNIQUE KEY uniq_auto_replies_user_keyword (user_id, keyword),
    CONSTRAINT fk_auto_replies_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


