USE atendy;

ALTER TABLE settings
    ADD COLUMN IF NOT EXISTS whatsapp_mode VARCHAR(20) NOT NULL DEFAULT 'mock' AFTER mensagem_confirmacao,
    ADD COLUMN IF NOT EXISTS whatsapp_api_url VARCHAR(255) NULL AFTER whatsapp_mode,
    ADD COLUMN IF NOT EXISTS whatsapp_phone_number_id VARCHAR(80) NULL AFTER whatsapp_api_url,
    ADD COLUMN IF NOT EXISTS whatsapp_verify_token VARCHAR(120) NULL AFTER token_whatsapp,
    ADD COLUMN IF NOT EXISTS whatsapp_default_country VARCHAR(5) NOT NULL DEFAULT '55' AFTER whatsapp_verify_token;

UPDATE settings
SET whatsapp_mode = COALESCE(NULLIF(whatsapp_mode, ''), 'mock'),
    whatsapp_api_url = COALESCE(NULLIF(whatsapp_api_url, ''), 'https://graph.facebook.com/v20.0'),
    whatsapp_verify_token = COALESCE(NULLIF(whatsapp_verify_token, ''), 'atendy_verify_token'),
    whatsapp_default_country = COALESCE(NULLIF(whatsapp_default_country, ''), '55')
WHERE user_id > 0;

