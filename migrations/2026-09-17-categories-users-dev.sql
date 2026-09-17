-- Execute UMA VEZ em bancos que já receberam a migração de fluxo de pedidos.
-- Faça backup antes de aplicar.

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_categories_active_sort (active, sort_order)
) ENGINE=InnoDB;

INSERT INTO categories (name, slug, active, sort_order) VALUES
('Coroa de Flores Simples', 'coroa-de-flores-simples', 1, 10),
('Coroa de Flores Mediana', 'coroa-de-flores-mediana', 1, 20),
('Coroa de Flores Luxo', 'coroa-de-flores-luxo', 1, 30);

ALTER TABLE products
    ADD COLUMN category_id INT UNSIGNED NULL AFTER category,
    ADD INDEX idx_products_category_id (category_id),
    ADD CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

-- Produtos antigos de coroa entram inicialmente na categoria mediana e podem ser ajustados pelo painel.
UPDATE products p
JOIN categories c ON c.slug = 'coroa-de-flores-mediana'
SET p.category_id = c.id
WHERE p.category_id IS NULL
  AND LOWER(p.category) LIKE '%coroa%';

ALTER TABLE admin_users
    ADD COLUMN role ENUM('admin', 'dev') NOT NULL DEFAULT 'admin' AFTER password_hash;

-- O usuário administrativo já existente passa a ser o primeiro DEV para poder gerenciar usuários e ferramentas.
UPDATE admin_users SET role = 'dev' WHERE username = 'admin';

CREATE TABLE app_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20) NOT NULL DEFAULT 'info',
    action VARCHAR(120) NOT NULL,
    context_json TEXT NULL,
    actor_user_id INT UNSIGNED NULL,
    actor_username VARCHAR(80) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_app_logs_created_at (created_at),
    INDEX idx_app_logs_level (level),
    INDEX idx_app_logs_action (action),
    CONSTRAINT fk_app_logs_user FOREIGN KEY (actor_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
