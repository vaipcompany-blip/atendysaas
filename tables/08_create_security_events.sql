-- Migration 08: eventos de segurança por usuário
CREATE TABLE IF NOT EXISTS security_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    event_type VARCHAR(60) NOT NULL,
    ip_address VARCHAR(45) NULL,
    details VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_security_events_user_created (user_id, created_at),
    KEY idx_security_events_type_created (event_type, created_at),
    CONSTRAINT fk_security_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


