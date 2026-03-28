CREATE TABLE IF NOT EXISTS legal_consents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    terms_version VARCHAR(40) NOT NULL,
    privacy_version VARCHAR(40) NOT NULL,
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accepted_ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_legal_consents_user_date (user_id, accepted_at),
    CONSTRAINT fk_legal_consents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;