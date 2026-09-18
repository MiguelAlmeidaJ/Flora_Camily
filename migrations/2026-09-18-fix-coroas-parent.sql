-- Corrige bancos em que as categorias de coroas antigas permaneceram como categorias principais.
-- Necessário porque instalações anteriores podem usar slugs simplificados como coroa-simples.

SET @coroas_id = (
    SELECT id
    FROM categories
    WHERE slug = 'coroas'
    LIMIT 1
);

UPDATE categories
SET parent_id = @coroas_id
WHERE @coroas_id IS NOT NULL
  AND id <> @coroas_id
  AND parent_id IS NULL
  AND (
      LOWER(TRIM(name)) IN (
          'coroa de flores simples',
          'coroa de flores mediana',
          'coroa de flores luxo'
      )
      OR slug IN (
          'coroa-de-flores-simples',
          'coroa-de-flores-mediana',
          'coroa-de-flores-luxo',
          'coroa-simples',
          'coroa-mediana',
          'coroa-luxo'
      )
  );

UPDATE categories
SET sort_order = CASE
    WHEN LOWER(TRIM(name)) = 'coroa de flores simples' OR slug IN ('coroa-de-flores-simples', 'coroa-simples') THEN 10
    WHEN LOWER(TRIM(name)) = 'coroa de flores mediana' OR slug IN ('coroa-de-flores-mediana', 'coroa-mediana') THEN 20
    WHEN LOWER(TRIM(name)) = 'coroa de flores luxo' OR slug IN ('coroa-de-flores-luxo', 'coroa-luxo') THEN 30
    ELSE sort_order
END
WHERE parent_id = @coroas_id;
