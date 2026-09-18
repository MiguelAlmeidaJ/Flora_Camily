-- Importe este arquivo dentro do banco criado pelo painel da hospedagem.
-- Não é necessário (nem recomendado em hospedagem compartilhada) executar CREATE DATABASE aqui.

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'dev') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_categories_active_sort (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'Homenagens florais',
    category_id INT UNSIGNED NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_active (active),
    INDEX idx_products_featured (featured),
    INDEX idx_products_category_id (category_id),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(160) NOT NULL,
    customer_phone VARCHAR(40) NOT NULL,
    customer_email VARCHAR(160) NULL,
    honoree_name VARCHAR(160) NOT NULL,
    state CHAR(2) NULL,
    city VARCHAR(120) NULL,
    delivery_place VARCHAR(255) NULL,
    delivery_date DATE NULL,
    delivery_time TIME NULL,
    desired_datetime VARCHAR(100) NULL,
    ribbon_message VARCHAR(255) NULL,
    notes TEXT NULL,
    products_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_fee DECIMAL(10,2) NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(40) NOT NULL DEFAULT 'novo',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    email_notified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_status (status),
    INDEX idx_orders_is_read (is_read),
    INDEX idx_orders_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_name VARCHAR(160) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(120) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migration_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(190) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    applied_by VARCHAR(80) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_settings (setting_key, setting_value) VALUES
('site_logo', 'assets/img/logo.svg'),
('site_favicon', 'assets/img/logo.svg')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

INSERT INTO categories (name, slug, active, sort_order)
SELECT 'Coroa de Flores Simples', 'coroa-de-flores-simples', 1, 10
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'coroa-de-flores-simples');

INSERT INTO categories (name, slug, active, sort_order)
SELECT 'Coroa de Flores Mediana', 'coroa-de-flores-mediana', 1, 20
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'coroa-de-flores-mediana');

INSERT INTO categories (name, slug, active, sort_order)
SELECT 'Coroa de Flores Luxo', 'coroa-de-flores-luxo', 1, 30
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'coroa-de-flores-luxo');

INSERT INTO admin_users (username, password_hash, role)
SELECT 'admin', '$2y$12$mCB0v37refyQnuXMzY5Hbudk1HuLuH0KzK5EeDpCxboTyGKIMU49m', 'dev'
WHERE NOT EXISTS (SELECT 1 FROM admin_users WHERE username = 'admin');

INSERT IGNORE INTO migration_history (migration_name, applied_by) VALUES
('2026-09-17-order-flow.sql', 'bootstrap'),
('2026-09-17-categories-users-dev.sql', 'bootstrap'),
('2026-09-17-admin-sidebar-settings.sql', 'bootstrap');

INSERT INTO products (name, category, category_id, description, price, active, featured)
SELECT 'Coroa Serenidade', 'Coroa de Flores Simples', (SELECT id FROM categories WHERE slug = 'coroa-de-flores-simples' LIMIT 1), 'Composição elegante em tons suaves para uma homenagem delicada e respeitosa.', 299.90, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Coroa Serenidade');

INSERT INTO products (name, category, category_id, description, price, active, featured)
SELECT 'Coroa Memória', 'Coroa de Flores Mediana', (SELECT id FROM categories WHERE slug = 'coroa-de-flores-mediana' LIMIT 1), 'Arranjo floral pensado para transmitir carinho, presença e boas lembranças.', 349.90, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Coroa Memória');

INSERT INTO products (name, category, description, price, active, featured)
SELECT 'Arranjo Acolhimento', 'Arranjos florais', 'Arranjo de homenagem com acabamento leve, elegante e acolhedor.', 189.90, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Arranjo Acolhimento');

INSERT INTO products (name, category, description, price, active, featured)
SELECT 'Homenagem Essencial', 'Homenagens florais', 'Uma opção simples e sensível para demonstrar cuidado e solidariedade.', 149.90, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Homenagem Essencial');
