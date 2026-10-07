INSERT INTO app_settings (setting_key, setting_value)
VALUES ('hero_image', 'assets/img/hero-coroas-composicao.webp')
ON DUPLICATE KEY UPDATE setting_value = CASE
    WHEN setting_value IS NULL OR setting_value = '' THEN VALUES(setting_value)
    ELSE setting_value
END;

DELETE FROM app_settings WHERE setting_key = 'hero_image_secondary';
