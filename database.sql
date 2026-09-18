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
    parent_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_categories_active_sort (active, sort_order),
    INDEX idx_categories_parent_sort (parent_id, sort_order),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE RESTRICT
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

CREATE TABLE IF NOT EXISTS service_states (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    uf CHAR(2) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_service_states_active_sort (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_cities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    state_id INT UNSIGNED NOT NULL,
    name VARCHAR(140) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_service_city_state_name (state_id, name),
    INDEX idx_service_cities_state_active_sort (state_id, active, sort_order),
    CONSTRAINT fk_service_cities_state FOREIGN KEY (state_id) REFERENCES service_states(id) ON DELETE RESTRICT
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

INSERT INTO categories (name, slug, parent_id, active, sort_order)
SELECT 'Coroas', 'coroas', NULL, 1, 10
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'coroas');

INSERT INTO categories (name, slug, parent_id, active, sort_order)
SELECT 'Coroa de Flores Simples', 'coroa-de-flores-simples', (SELECT id FROM categories WHERE slug = 'coroas' LIMIT 1), 1, 10
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'coroa-de-flores-simples');

INSERT INTO categories (name, slug, parent_id, active, sort_order)
SELECT 'Coroa de Flores Mediana', 'coroa-de-flores-mediana', (SELECT id FROM categories WHERE slug = 'coroas' LIMIT 1), 1, 20
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'coroa-de-flores-mediana');

INSERT INTO categories (name, slug, parent_id, active, sort_order)
SELECT 'Coroa de Flores Luxo', 'coroa-de-flores-luxo', (SELECT id FROM categories WHERE slug = 'coroas' LIMIT 1), 1, 30
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'coroa-de-flores-luxo');

INSERT INTO service_states (name, uf, active, sort_order)
SELECT 'Minas Gerais', 'MG', 1, 10
WHERE NOT EXISTS (SELECT 1 FROM service_states WHERE uf = 'MG');

SET @mg_id = (SELECT id FROM service_states WHERE uf = 'MG' LIMIT 1);

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Conselheiro Lafaiete', 1, 10
WHERE @mg_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Conselheiro Lafaiete');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Congonhas', 1, 20
WHERE @mg_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Congonhas');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Ouro Branco', 1, 30
WHERE @mg_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Ouro Branco');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Itaverava', 1, 40
WHERE @mg_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Itaverava');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Santana', 1, 50
WHERE @mg_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Santana');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Carandaí', 1, 60
WHERE @mg_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Carandaí');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Casa Grande', 1, 70
WHERE @mg_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Casa Grande');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Queluzito', 1, 80
WHERE @mg_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Queluzito');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'São Gonçalo', 1, 90
WHERE @mg_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'São Gonçalo');

INSERT INTO admin_users (username, password_hash, role)
SELECT 'admin', '$2y$12$mCB0v37refyQnuXMzY5Hbudk1HuLuH0KzK5EeDpCxboTyGKIMU49m', 'dev'
WHERE NOT EXISTS (SELECT 1 FROM admin_users WHERE username = 'admin');

INSERT IGNORE INTO migration_history (migration_name, applied_by) VALUES
('2026-09-17-order-flow.sql', 'bootstrap'),
('2026-09-17-categories-users-dev.sql', 'bootstrap'),
('2026-09-17-admin-sidebar-settings.sql', 'bootstrap'),
('2026-09-18-category-hierarchy.sql', 'bootstrap'),
('2026-09-18-service-regions.sql', 'bootstrap');

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
