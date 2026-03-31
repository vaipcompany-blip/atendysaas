-- Migration 07: controle de invalida��o global de sess�es por usu�rio
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER ativo;


