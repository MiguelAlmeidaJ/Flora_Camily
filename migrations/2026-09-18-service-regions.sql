-- Regiões atendidas pela Flora Camily.
-- Estados e cidades passam a ser a fonte oficial do checkout.

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
    CONSTRAINT fk_service_cities_state
        FOREIGN KEY (state_id) REFERENCES service_states(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO service_states (name, uf, active, sort_order)
SELECT 'Minas Gerais', 'MG', 1, 10
WHERE NOT EXISTS (
    SELECT 1 FROM service_states WHERE uf = 'MG'
);

SET @mg_id = (
    SELECT id
    FROM service_states
    WHERE uf = 'MG'
    LIMIT 1
);

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Conselheiro Lafaiete', 1, 10
WHERE @mg_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Conselheiro Lafaiete');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Congonhas', 1, 20
WHERE @mg_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Congonhas');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Ouro Branco', 1, 30
WHERE @mg_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Ouro Branco');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Itaverava', 1, 40
WHERE @mg_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Itaverava');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Santana', 1, 50
WHERE @mg_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Santana');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Carandaí', 1, 60
WHERE @mg_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Carandaí');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Casa Grande', 1, 70
WHERE @mg_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Casa Grande');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'Queluzito', 1, 80
WHERE @mg_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'Queluzito');

INSERT INTO service_cities (state_id, name, active, sort_order)
SELECT @mg_id, 'São Gonçalo', 1, 90
WHERE @mg_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM service_cities WHERE state_id = @mg_id AND name = 'São Gonçalo');
