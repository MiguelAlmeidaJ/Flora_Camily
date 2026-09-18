-- Reparação idempotente do schema de pedidos.
-- Pode ser executada mesmo se parte das colunas já existir.

SET @schema_name = DATABASE();

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN customer_email VARCHAR(160) NULL AFTER customer_phone',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'customer_email'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN honoree_name VARCHAR(160) NOT NULL DEFAULT '''' AFTER customer_email',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'honoree_name'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN state CHAR(2) NULL AFTER honoree_name',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'state'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN delivery_date DATE NULL AFTER delivery_place',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'delivery_date'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN delivery_time TIME NULL AFTER delivery_date',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'delivery_time'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN products_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER notes',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'products_total'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN shipping_fee DECIMAL(10,2) NULL AFTER products_total',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_fee'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'is_read'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN email_notified TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'email_notified'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'updated_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE orders
SET products_total = total
WHERE products_total = 0.00;

UPDATE orders
SET status = 'novo'
WHERE status = 'whatsapp_iniciado';

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_status (status)',
    'SELECT 1')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_is_read (is_read)',
    'SELECT 1')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_is_read'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_created_at (created_at)',
    'SELECT 1')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_created_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
