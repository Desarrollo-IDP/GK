-- Script para crear contadores independientes por empresa para cartas de entrega
-- Similar al sistema de contadores de equipos y tickets

-- =====================================================
-- CONTADORES DE CARTAS DE ENTREGA
-- =====================================================

-- Crear tabla de contadores por empresa para cartas de entrega
CREATE TABLE IF NOT EXISTS `carta_entrega_counters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_empresa` int(11) NOT NULL,
  `current_counter` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_empresa_counter` (`id_empresa`),
  KEY `idx_empresa` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Crear índice único compuesto para numero_carta e id_empresa en cartas_entrega
-- Esto permite que el mismo numero_carta exista en diferentes empresas
-- Primero verificar si existe la columna id_empresa y el índice
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cartas_entrega' 
    AND COLUMN_NAME = 'id_empresa'
);

SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cartas_entrega' 
    AND INDEX_NAME = 'idx_numero_carta_empresa'
);

-- Si existe id_empresa pero no el índice, crearlo
SET @sql = IF(@col_exists > 0 AND @idx_exists = 0,
    'ALTER TABLE `cartas_entrega` ADD UNIQUE INDEX `idx_numero_carta_empresa` (`numero_carta`, `id_empresa`)',
    IF(@col_exists = 0, 
        'SELECT "Columna id_empresa no existe en cartas_entrega" AS message',
        'SELECT "Índice ya existe" AS message'
    )
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- INICIALIZACIÓN DE CONTADORES
-- =====================================================

-- Inicializar contadores para empresas existentes
INSERT INTO `carta_entrega_counters` (`id_empresa`, `current_counter`)
SELECT DISTINCT COALESCE(`id_empresa`, 0), 0
FROM `cartas_entrega`
WHERE `id_empresa` IS NOT NULL OR `id_empresa` IS NULL
ON DUPLICATE KEY UPDATE `current_counter` = `current_counter`;

-- También inicializar para empresas_usuarios
INSERT INTO `carta_entrega_counters` (`id_empresa`, `current_counter`)
SELECT DISTINCT `id_empresa`, 0
FROM `empresas_usuarios`
WHERE `id_empresa` IS NOT NULL
ON DUPLICATE KEY UPDATE `current_counter` = `current_counter`;

-- Inicializar contador global (id_empresa = 0) si no existe
INSERT INTO `carta_entrega_counters` (`id_empresa`, `current_counter`)
VALUES (0, 0)
ON DUPLICATE KEY UPDATE `current_counter` = `current_counter`;

-- Si hay cartas existentes, inicializar el contador al máximo numero_carta existente por empresa
-- Esto asegura que los nuevos números continúen desde donde quedaron
UPDATE `carta_entrega_counters` tc
SET `current_counter` = (
    SELECT COALESCE(MAX(CAST(SUBSTRING(numero_carta, 4) AS UNSIGNED)), 0)
    FROM `cartas_entrega` ce
    WHERE (tc.id_empresa = 0 AND (ce.id_empresa IS NULL OR ce.id_empresa = 0))
       OR (tc.id_empresa != 0 AND ce.id_empresa = tc.id_empresa)
    AND ce.numero_carta LIKE 'CA-%'
)
WHERE EXISTS (
    SELECT 1 FROM `cartas_entrega` ce
    WHERE (tc.id_empresa = 0 AND (ce.id_empresa IS NULL OR ce.id_empresa = 0))
       OR (tc.id_empresa != 0 AND ce.id_empresa = tc.id_empresa)
    AND ce.numero_carta LIKE 'CA-%'
);

