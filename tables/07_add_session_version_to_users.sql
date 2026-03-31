-- Migration 07: controle de invalidação global de sessões por usuário
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER ativo;


