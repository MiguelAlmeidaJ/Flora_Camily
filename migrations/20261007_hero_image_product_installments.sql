ALTER TABLE products
    ADD COLUMN installment_count INT UNSIGNED NULL AFTER price,
    ADD COLUMN installment_value DECIMAL(10,2) NULL AFTER installment_count;

INSERT INTO app_settings (setting_key, setting_value)
VALUES ('hero_image', '')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
