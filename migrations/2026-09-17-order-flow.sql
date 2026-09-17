-- Execute UMA VEZ em bancos que já possuíam a versão anterior do Flora Camily.
-- Faça backup antes de aplicar.

ALTER TABLE orders
    ADD COLUMN customer_email VARCHAR(160) NULL AFTER customer_phone,
    ADD COLUMN honoree_name VARCHAR(160) NOT NULL DEFAULT '' AFTER customer_email,
    ADD COLUMN state CHAR(2) NULL AFTER honoree_name,
    ADD COLUMN delivery_date DATE NULL AFTER delivery_place,
    ADD COLUMN delivery_time TIME NULL AFTER delivery_date,
    ADD COLUMN products_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER notes,
    ADD COLUMN shipping_fee DECIMAL(10,2) NULL AFTER products_total,
    ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN email_notified TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read,
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE orders
SET products_total = total
WHERE products_total = 0.00;

UPDATE orders
SET status = 'novo'
WHERE status = 'whatsapp_iniciado';

ALTER TABLE orders
    MODIFY customer_phone VARCHAR(40) NOT NULL,
    MODIFY status VARCHAR(40) NOT NULL DEFAULT 'novo',
    ADD INDEX idx_orders_status (status),
    ADD INDEX idx_orders_is_read (is_read),
    ADD INDEX idx_orders_created_at (created_at);
