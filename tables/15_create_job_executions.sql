CREATE TABLE IF NOT EXISTS job_executions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(60) NOT NULL,
    lock_key VARCHAR(120) NOT NULL,
    user_id INT UNSIGNED NULL,
    dry_run TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('running','success','failed','skipped') NOT NULL DEFAULT 'running',
    result_json LONGTEXT NULL,
    error_message VARCHAR(500) NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_job_executions_type_started (job_type, started_at),
    KEY idx_job_executions_user_started (user_id, started_at),
    KEY idx_job_executions_status (status),
    CONSTRAINT fk_job_executions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;