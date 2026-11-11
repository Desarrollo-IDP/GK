-- Script para crear contadores independientes por empresa para requesters y technicians
-- Similar al sistema de contadores de tickets

-- =====================================================
-- CONTADORES DE REQUESTERS (SOLICITANTES)
-- =====================================================

-- Crear tabla de contadores por empresa para requesters
CREATE TABLE IF NOT EXISTS `requester_counters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_empresa` int(11) NOT NULL,
  `current_counter` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_empresa_counter` (`id_empresa`),
  KEY `idx_empresa` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Agregar columna requester_number a la tabla requesters si no existe
ALTER TABLE `requesters` 
ADD COLUMN IF NOT EXISTS `requester_number` VARCHAR(50) NULL DEFAULT NULL AFTER `id`;

-- Crear índice para requester_number
ALTER TABLE `requesters` 
ADD INDEX IF NOT EXISTS `idx_requester_number` (`requester_number`);

-- Crear índice único compuesto para requester_number e id_empresa
ALTER TABLE `requesters` 
ADD UNIQUE INDEX IF NOT EXISTS `idx_requester_number_empresa` (`requester_number`, `id_empresa`);

-- =====================================================
-- CONTADORES DE TECHNICIANS (TÉCNICOS)
-- =====================================================

-- Crear tabla de contadores por empresa para technicians
CREATE TABLE IF NOT EXISTS `technician_counters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_empresa` int(11) NOT NULL,
  `current_counter` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_empresa_counter` (`id_empresa`),
  KEY `idx_empresa` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Agregar columna technician_number a la tabla technicians si no existe
ALTER TABLE `technicians` 
ADD COLUMN IF NOT EXISTS `technician_number` VARCHAR(50) NULL DEFAULT NULL AFTER `id`;

-- Crear índice para technician_number
ALTER TABLE `technicians` 
ADD INDEX IF NOT EXISTS `idx_technician_number` (`technician_number`);

-- Crear índice único compuesto para technician_number e id_empresa
ALTER TABLE `technicians` 
ADD UNIQUE INDEX IF NOT EXISTS `idx_technician_number_empresa` (`technician_number`, `id_empresa`);

-- =====================================================
-- INICIALIZACIÓN DE CONTADORES
-- =====================================================

-- Inicializar contadores para empresas existentes (requesters)
INSERT INTO `requester_counters` (`id_empresa`, `current_counter`)
SELECT DISTINCT COALESCE(`id_empresa`, 0), 0
FROM `requesters`
WHERE `id_empresa` IS NOT NULL OR `id_empresa` IS NULL
ON DUPLICATE KEY UPDATE `current_counter` = `current_counter`;

-- Inicializar contadores para empresas existentes (technicians)
INSERT INTO `technician_counters` (`id_empresa`, `current_counter`)
SELECT DISTINCT COALESCE(`id_empresa`, 0), 0
FROM `technicians`
WHERE `id_empresa` IS NOT NULL OR `id_empresa` IS NULL
ON DUPLICATE KEY UPDATE `current_counter` = `current_counter`;

-- Actualizar contadores al máximo número asignado por empresa (requesters)
UPDATE `requester_counters` rc
SET `current_counter` = (
    SELECT COALESCE(MAX(CAST(`requester_number` AS UNSIGNED)), 0)
    FROM `requesters` r
    WHERE COALESCE(r.`id_empresa`, 0) = rc.`id_empresa`
    AND r.`requester_number` IS NOT NULL
    AND r.`requester_number` REGEXP '^[0-9]+$'
)
WHERE EXISTS (
    SELECT 1 FROM `requesters` r2 
    WHERE COALESCE(r2.`id_empresa`, 0) = rc.`id_empresa` 
    AND r2.`requester_number` IS NOT NULL
);

-- Actualizar contadores al máximo número asignado por empresa (technicians)
UPDATE `technician_counters` tc
SET `current_counter` = (
    SELECT COALESCE(MAX(CAST(`technician_number` AS UNSIGNED)), 0)
    FROM `technicians` t
    WHERE COALESCE(t.`id_empresa`, 0) = tc.`id_empresa`
    AND t.`technician_number` IS NOT NULL
    AND t.`technician_number` REGEXP '^[0-9]+$'
)
WHERE EXISTS (
    SELECT 1 FROM `technicians` t2 
    WHERE COALESCE(t2.`id_empresa`, 0) = tc.`id_empresa` 
    AND t2.`technician_number` IS NOT NULL
);

