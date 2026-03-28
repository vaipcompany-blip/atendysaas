-- Migration 06: controle de tentativas de login (rate limit)
USE atendy;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login_identifier VARCHAR(180) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_attempts_lookup (login_identifier, ip_address, attempted_at),
    KEY idx_login_attempts_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

