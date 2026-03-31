CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nome_consultorio VARCHAR(150) NOT NULL,
    telefone VARCHAR(20) NULL,
    endereco VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    session_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_users_email (email),
    UNIQUE KEY uniq_users_cpf (cpf)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    nome VARCHAR(150) NOT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    email VARCHAR(150) NULL,
    telefone VARCHAR(20) NULL,
    cpf VARCHAR(14) NOT NULL,
    data_nascimento DATE NULL,
    endereco VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    UNIQUE KEY uniq_patient_user_cpf (user_id, cpf),
    KEY idx_patients_user (user_id),
    CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED NOT NULL,
    data_hora DATETIME NOT NULL,
    status ENUM('agendada','confirmada','realizada','cancelada','faltou','reagendada') NOT NULL DEFAULT 'agendada',
    procedimento VARCHAR(150) NOT NULL,
    notas TEXT NULL,
    valor_cobrado DECIMAL(10,2) NULL DEFAULT NULL,
    forma_pagamento VARCHAR(30) NULL DEFAULT NULL,
    pago TINYINT(1) NOT NULL DEFAULT 0,
    data_pagamento DATE NULL DEFAULT NULL,
    confirmacao_timestamp DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_appointments_user_datetime (user_id, data_hora),
    KEY idx_appointments_patient (patient_id),
    CONSTRAINT fk_appointments_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_appointments_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED NULL,
    appointment_id INT UNSIGNED NULL,
    direction ENUM('inbound','outbound') NOT NULL,
    texto TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'sent',
    external_message_id VARCHAR(100) NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_messages_user (user_id),
    CONSTRAINT fk_messages_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_messages_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
    CONSTRAINT fk_messages_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS automation_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED NULL,
    tipo_automacao VARCHAR(50) NOT NULL,
    status_envio VARCHAR(30) NOT NULL,
    detalhes VARCHAR(255) NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_automation_user (user_id),
    CONSTRAINT fk_automation_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_automation_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
    user_id INT UNSIGNED PRIMARY KEY,
    horario_abertura TIME NULL,
    horario_fechamento TIME NULL,
    duracao_consulta INT NOT NULL DEFAULT 60,
    intervalo INT NOT NULL DEFAULT 10,
    mensagem_confirmacao TEXT NULL,
    whatsapp_mode VARCHAR(20) NOT NULL DEFAULT 'mock',
    whatsapp_api_url VARCHAR(255) NULL,
    whatsapp_phone_number_id VARCHAR(80) NULL,
    token_whatsapp VARCHAR(255) NULL,
    whatsapp_verify_token VARCHAR(120) NULL,
    whatsapp_default_country VARCHAR(5) NOT NULL DEFAULT '55',
    meta_conversao_mensal DECIMAL(5,2) NOT NULL DEFAULT 60.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    CONSTRAINT fk_settings_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO users (email, cpf, password_hash, nome_consultorio, telefone, endereco, ativo)
VALUES (
    'admin@atendy.local',
    '11122233344',
    '$2y$10$XNZTD8MW8mSjVh4d/wGbWuufw.fPq2kMxLLZaUFeM0WqRjGVLkoB6',
    'Clínica Atendy Demo',
    '(11) 90000-0000',
    'Rua Exemplo, 100',
    1
)
ON DUPLICATE KEY UPDATE email = VALUES(email);

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    related_type VARCHAR(50) NULL,
    related_id INT UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user (user_id),
    KEY idx_notifications_read (user_id, is_read),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO settings (user_id, horario_abertura, horario_fechamento, duracao_consulta, intervalo, mensagem_confirmacao, whatsapp_mode, whatsapp_api_url, whatsapp_verify_token, whatsapp_default_country, meta_conversao_mensal)
SELECT id, '08:00:00', '18:00:00', 60, 10, 'Olá! Sua consulta é amanhã. Você confirma?', 'mock', 'https://graph.facebook.com/v20.0', 'atendy_verify_token', '55', 60.00
FROM users
WHERE email = 'admin@atendy.local'
ON DUPLICATE KEY UPDATE updated_at = NOW();

CREATE TABLE IF NOT EXISTS team_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT UNSIGNED NOT NULL,
    email VARCHAR(150) NOT NULL,
    nome_completo VARCHAR(150) NOT NULL,
    role ENUM('owner','admin','staff') NOT NULL DEFAULT 'staff',
    status ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending',
    password_hash VARCHAR(255) NULL,
    invitation_token VARCHAR(64) NULL,
    token_created_at DATETIME NULL,
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    UNIQUE KEY uniq_team_workspace_email (workspace_id, email),
    KEY idx_team_workspace (workspace_id),
    KEY idx_team_status (workspace_id, status),
    CONSTRAINT fk_team_workspace FOREIGN KEY (workspace_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS team_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_member_id INT UNSIGNED NOT NULL,
    permission VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_member_permission (team_member_id, permission),
    KEY idx_permissions_member (team_member_id),
    CONSTRAINT fk_permissions_member FOREIGN KEY (team_member_id) REFERENCES team_members(id) ON DELETE CASCADE
) ENGINE=InnoDB;



