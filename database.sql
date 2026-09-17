CREATE DATABASE IF NOT EXISTS flora_camily CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE flora_camily;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'Homenagens florais',
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_active (active),
    INDEX idx_products_featured (featured)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(160) NOT NULL,
    customer_phone VARCHAR(40) NULL,
    city VARCHAR(120) NULL,
    delivery_place VARCHAR(255) NULL,
    desired_datetime VARCHAR(100) NULL,
    ribbon_message VARCHAR(255) NULL,
    notes TEXT NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(40) NOT NULL DEFAULT 'whatsapp_iniciado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

INSERT INTO admin_users (username, password_hash)
SELECT 'admin', '$2y$12$mCB0v37refyQnuXMzY5Hbudk1HuLuH0KzK5EeDpCxboTyGKIMU49m'
WHERE NOT EXISTS (SELECT 1 FROM admin_users WHERE username = 'admin');

INSERT INTO products (name, category, description, price, active, featured)
SELECT 'Coroa Serenidade', 'Coroas de flores', 'Composição elegante em tons suaves para uma homenagem delicada e respeitosa.', 299.90, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Coroa Serenidade');

INSERT INTO products (name, category, description, price, active, featured)
SELECT 'Coroa Memória', 'Coroas de flores', 'Arranjo floral pensado para transmitir carinho, presença e boas lembranças.', 349.90, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Coroa Memória');

INSERT INTO products (name, category, description, price, active, featured)
SELECT 'Arranjo Acolhimento', 'Arranjos florais', 'Arranjo de homenagem com acabamento leve, elegante e acolhedor.', 189.90, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Arranjo Acolhimento');

INSERT INTO products (name, category, description, price, active, featured)
SELECT 'Homenagem Essencial', 'Homenagens florais', 'Uma opção simples e sensível para demonstrar cuidado e solidariedade.', 149.90, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Homenagem Essencial');
