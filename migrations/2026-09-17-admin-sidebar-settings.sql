-- Execute UMA VEZ após as migrations anteriores do Flora Camily.
-- Faça backup do banco antes de aplicar.

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(120) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO app_settings (setting_key, setting_value) VALUES
('site_logo', 'assets/img/logo.svg'),
('site_favicon', 'assets/img/logo.svg')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

CREATE TABLE IF NOT EXISTS migration_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(190) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    applied_by VARCHAR(80) NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO migration_history (migration_name, applied_by) VALUES
('2026-09-17-order-flow.sql', 'bootstrap'),
('2026-09-17-categories-users-dev.sql', 'bootstrap'),
('2026-09-17-admin-sidebar-settings.sql', 'bootstrap');
