-- Migration 11: datas bloqueadas (feriados/indisponibilidades da clínica)
-- Executar após as migrations anteriores.

USE atendy;

CREATE TABLE IF NOT EXISTS clinic_blocked_dates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    blocked_date DATE NOT NULL,
    reason VARCHAR(150) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_user_blocked_date (user_id, blocked_date),
    KEY idx_blocked_user_date_active (user_id, blocked_date, is_active),
    CONSTRAINT fk_blocked_dates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


