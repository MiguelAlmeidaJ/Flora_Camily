-- Estrutura hierárquica de categorias.
-- Cria "Coroas" como categoria principal e move as categorias atuais para subcategorias.

SET @schema_name = DATABASE();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE categories ADD COLUMN parent_id INT UNSIGNED NULL AFTER id',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'categories'
      AND COLUMN_NAME = 'parent_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE categories ADD INDEX idx_categories_parent_sort (parent_id, sort_order)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'categories'
      AND INDEX_NAME = 'idx_categories_parent_sort'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE categories ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE RESTRICT',
        'SELECT 1'
    )
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @schema_name
      AND TABLE_NAME = 'categories'
      AND CONSTRAINT_NAME = 'fk_categories_parent'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO categories (name, slug, parent_id, active, sort_order)
SELECT 'Coroas', 'coroas', NULL, 1, 10
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE slug = 'coroas'
);

SET @coroas_id = (
    SELECT id
    FROM categories
    WHERE slug = 'coroas'
    LIMIT 1
);

UPDATE categories
SET parent_id = @coroas_id
WHERE id <> @coroas_id
  AND parent_id IS NULL
  AND slug IN (
      'coroa-de-flores-simples',
      'coroa-de-flores-mediana',
      'coroa-de-flores-luxo'
  );

UPDATE categories
SET sort_order = CASE slug
    WHEN 'coroa-de-flores-simples' THEN 10
    WHEN 'coroa-de-flores-mediana' THEN 20
    WHEN 'coroa-de-flores-luxo' THEN 30
    ELSE sort_order
END
WHERE parent_id = @coroas_id;
