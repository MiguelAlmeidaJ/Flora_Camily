-- Padroniza o banco em UTF-8 e corrige sequências mojibake comuns já gravadas.
-- Pode ser executada uma única vez pelo painel DEV > Configurações > Migrations.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE admin_users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE categories CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE orders CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE order_items CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE app_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE app_settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE migration_history CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

UPDATE categories
SET name = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name,
'Ã¡','á'),'Ã ','à'),'Ã¢','â'),'Ã£','ã'),'Ã¤','ä'),'Ã©','é'),'Ã¨','è'),'Ãª','ê'),'Ã­','í'),'Ã³','ó'),'Ã²','ò'),'Ã´','ô'),'Ãµ','õ'),'Ãº','ú'),'Ã¼','ü'),'Ã§','ç')
WHERE name REGEXP 'Ã¡|Ã |Ã¢|Ã£|Ã¤|Ã©|Ã¨|Ãª|Ã­|Ã³|Ã²|Ã´|Ãµ|Ãº|Ã¼|Ã§';

UPDATE products
SET
    name = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name,
'Ã¡','á'),'Ã ','à'),'Ã¢','â'),'Ã£','ã'),'Ã¤','ä'),'Ã©','é'),'Ã¨','è'),'Ãª','ê'),'Ã­','í'),'Ã³','ó'),'Ã²','ò'),'Ã´','ô'),'Ãµ','õ'),'Ãº','ú'),'Ã¼','ü'),'Ã§','ç'),
    category = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(category,
'Ã¡','á'),'Ã ','à'),'Ã¢','â'),'Ã£','ã'),'Ã¤','ä'),'Ã©','é'),'Ã¨','è'),'Ãª','ê'),'Ã­','í'),'Ã³','ó'),'Ã²','ò'),'Ã´','ô'),'Ãµ','õ'),'Ãº','ú'),'Ã¼','ü'),'Ã§','ç'),
    description = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(description,
'Ã¡','á'),'Ã ','à'),'Ã¢','â'),'Ã£','ã'),'Ã¤','ä'),'Ã©','é'),'Ã¨','è'),'Ãª','ê'),'Ã­','í'),'Ã³','ó'),'Ã²','ò'),'Ã´','ô'),'Ãµ','õ'),'Ãº','ú'),'Ã¼','ü'),'Ã§','ç')
WHERE
    name REGEXP 'Ã¡|Ã |Ã¢|Ã£|Ã¤|Ã©|Ã¨|Ãª|Ã­|Ã³|Ã²|Ã´|Ãµ|Ãº|Ã¼|Ã§'
    OR category REGEXP 'Ã¡|Ã |Ã¢|Ã£|Ã¤|Ã©|Ã¨|Ãª|Ã­|Ã³|Ã²|Ã´|Ãµ|Ãº|Ã¼|Ã§'
    OR description REGEXP 'Ã¡|Ã |Ã¢|Ã£|Ã¤|Ã©|Ã¨|Ãª|Ã­|Ã³|Ã²|Ã´|Ãµ|Ãº|Ã¼|Ã§';

UPDATE order_items
SET product_name = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(product_name,
'Ã¡','á'),'Ã ','à'),'Ã¢','â'),'Ã£','ã'),'Ã¤','ä'),'Ã©','é'),'Ã¨','è'),'Ãª','ê'),'Ã­','í'),'Ã³','ó'),'Ã²','ò'),'Ã´','ô'),'Ãµ','õ'),'Ãº','ú'),'Ã¼','ü'),'Ã§','ç')
WHERE product_name REGEXP 'Ã¡|Ã |Ã¢|Ã£|Ã¤|Ã©|Ã¨|Ãª|Ã­|Ã³|Ã²|Ã´|Ãµ|Ãº|Ã¼|Ã§';

UPDATE orders
SET
    customer_name = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(customer_name,
'Ã¡','á'),'Ã ','à'),'Ã¢','â'),'Ã£','ã'),'Ã¤','ä'),'Ã©','é'),'Ã¨','è'),'Ãª','ê'),'Ã­','í'),'Ã³','ó'),'Ã²','ò'),'Ã´','ô'),'Ãµ','õ'),'Ãº','ú'),'Ã¼','ü'),'Ã§','ç'),
    honoree_name = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(honoree_name,
'Ã¡','á'),'Ã ','à'),'Ã¢','â'),'Ã£','ã'),'Ã¤','ä'),'Ã©','é'),'Ã¨','è'),'Ãª','ê'),'Ã­','í'),'Ã³','ó'),'Ã²','ò'),'Ã´','ô'),'Ãµ','õ'),'Ãº','ú'),'Ã¼','ü'),'Ã§','ç'),
    city = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(city,
'Ã¡','á'),'Ã ','à'),'Ã¢','â'),'Ã£','ã'),'Ã¤','ä'),'Ã©','é'),'Ã¨','è'),'Ãª','ê'),'Ã­','í'),'Ã³','ó'),'Ã²','ò'),'Ã´','ô'),'Ãµ','õ'),'Ãº','ú'),'Ã¼','ü'),'Ã§','ç'),
    delivery_place = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(delivery_place,
'Ã¡','á'),'Ã ','à'),'Ã¢','â'),'Ã£','ã'),'Ã¤','ä'),'Ã©','é'),'Ã¨','è'),'Ãª','ê'),'Ã­','í'),'Ã³','ó'),'Ã²','ò'),'Ã´','ô'),'Ãµ','õ'),'Ãº','ú'),'Ã¼','ü'),'Ã§','ç'),
    ribbon_message = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ribbon_message,
'Ã¡','á'),'Ã ','à'),'Ã¢','â'),'Ã£','ã'),'Ã¤','ä'),'Ã©','é'),'Ã¨','è'),'Ãª','ê'),'Ã­','í'),'Ã³','ó'),'Ã²','ò'),'Ã´','ô'),'Ãµ','õ'),'Ãº','ú'),'Ã¼','ü'),'Ã§','ç'),
    notes = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(notes,
'Ã¡','á'),'Ã ','à'),'Ã¢','â'),'Ã£','ã'),'Ã¤','ä'),'Ã©','é'),'Ã¨','è'),'Ãª','ê'),'Ã­','í'),'Ã³','ó'),'Ã²','ò'),'Ã´','ô'),'Ãµ','õ'),'Ãº','ú'),'Ã¼','ü'),'Ã§','ç')
WHERE
    customer_name REGEXP 'Ã¡|Ã |Ã¢|Ã£|Ã¤|Ã©|Ã¨|Ãª|Ã­|Ã³|Ã²|Ã´|Ãµ|Ãº|Ã¼|Ã§'
    OR honoree_name REGEXP 'Ã¡|Ã |Ã¢|Ã£|Ã¤|Ã©|Ã¨|Ãª|Ã­|Ã³|Ã²|Ã´|Ãµ|Ãº|Ã¼|Ã§'
    OR city REGEXP 'Ã¡|Ã |Ã¢|Ã£|Ã¤|Ã©|Ã¨|Ãª|Ã­|Ã³|Ã²|Ã´|Ãµ|Ãº|Ã¼|Ã§'
    OR delivery_place REGEXP 'Ã¡|Ã |Ã¢|Ã£|Ã¤|Ã©|Ã¨|Ãª|Ã­|Ã³|Ã²|Ã´|Ãµ|Ãº|Ã¼|Ã§'
    OR ribbon_message REGEXP 'Ã¡|Ã |Ã¢|Ã£|Ã¤|Ã©|Ã¨|Ãª|Ã­|Ã³|Ã²|Ã´|Ãµ|Ãº|Ã¼|Ã§'
    OR notes REGEXP 'Ã¡|Ã |Ã¢|Ã£|Ã¤|Ã©|Ã¨|Ãª|Ã­|Ã³|Ã²|Ã´|Ãµ|Ãº|Ã¼|Ã§';

-- Correções adicionais frequentes em textos copiados de sistemas antigos.
UPDATE products SET
    name = REPLACE(REPLACE(REPLACE(name, 'Âº', 'º'), 'Âª', 'ª'), 'Â°', '°'),
    category = REPLACE(REPLACE(REPLACE(category, 'Âº', 'º'), 'Âª', 'ª'), 'Â°', '°'),
    description = REPLACE(REPLACE(REPLACE(description, 'Âº', 'º'), 'Âª', 'ª'), 'Â°', '°');

UPDATE categories SET
    name = REPLACE(REPLACE(REPLACE(name, 'Âº', 'º'), 'Âª', 'ª'), 'Â°', '°');

UPDATE order_items SET
    product_name = REPLACE(REPLACE(REPLACE(product_name, 'Âº', 'º'), 'Âª', 'ª'), 'Â°', '°');

UPDATE orders SET
    customer_name = REPLACE(REPLACE(REPLACE(customer_name, 'Âº', 'º'), 'Âª', 'ª'), 'Â°', '°'),
    honoree_name = REPLACE(REPLACE(REPLACE(honoree_name, 'Âº', 'º'), 'Âª', 'ª'), 'Â°', '°'),
    city = REPLACE(REPLACE(REPLACE(city, 'Âº', 'º'), 'Âª', 'ª'), 'Â°', '°'),
    delivery_place = REPLACE(REPLACE(REPLACE(delivery_place, 'Âº', 'º'), 'Âª', 'ª'), 'Â°', '°'),
    ribbon_message = REPLACE(REPLACE(REPLACE(ribbon_message, 'Âº', 'º'), 'Âª', 'ª'), 'Â°', '°'),
    notes = REPLACE(REPLACE(REPLACE(notes, 'Âº', 'º'), 'Âª', 'ª'), 'Â°', '°');
