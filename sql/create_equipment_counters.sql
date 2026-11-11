-- Script para crear contadores independientes por empresa para equipos
-- Similar al sistema de contadores de tickets

-- =====================================================
-- CONTADORES DE EQUIPOS
-- =====================================================

-- Crear tabla de contadores por empresa para equipos
CREATE TABLE IF NOT EXISTS `equipment_counters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_empresa` int(11) NOT NULL,
  `current_counter` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_empresa_counter` (`id_empresa`),
  KEY `idx_empresa` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Agregar columna equipo_number a la tabla equipos si no existe
ALTER TABLE `equipos` 
ADD COLUMN IF NOT EXISTS `equipo_number` VARCHAR(50) NULL DEFAULT NULL AFTER `id_equipo`;

-- Crear índice para equipo_number
ALTER TABLE `equipos` 
ADD INDEX IF NOT EXISTS `idx_equipo_number` (`equipo_number`);

-- Crear índice único compuesto para equipo_number e id_empresa_usuario
-- Esto permite que el mismo equipo_number exista en diferentes empresas
ALTER TABLE `equipos` 
ADD UNIQUE INDEX IF NOT EXISTS `idx_equipo_number_empresa` (`equipo_number`, `id_empresa_usuario`);

-- =====================================================
-- INICIALIZACIÓN DE CONTADORES
-- =====================================================

-- Inicializar contadores para empresas existentes
INSERT INTO `equipment_counters` (`id_empresa`, `current_counter`)
SELECT DISTINCT COALESCE(`id_empresa_usuario`, 0), 0
FROM `equipos`
WHERE `id_empresa_usuario` IS NOT NULL OR `id_empresa_usuario` IS NULL
ON DUPLICATE KEY UPDATE `current_counter` = `current_counter`;

-- También inicializar para empresas_usuarios
INSERT INTO `equipment_counters` (`id_empresa`, `current_counter`)
SELECT DISTINCT `id_empresa`, 0
FROM `empresas_usuarios`
WHERE `id_empresa` IS NOT NULL
ON DUPLICATE KEY UPDATE `current_counter` = `current_counter`;

-- Actualizar contadores al máximo número asignado por empresa
UPDATE `equipment_counters` ec
SET `current_counter` = (
    SELECT COALESCE(MAX(CAST(`equipo_number` AS UNSIGNED)), 0)
    FROM `equipos` e
    WHERE COALESCE(e.`id_empresa_usuario`, 0) = ec.`id_empresa`
    AND e.`equipo_number` IS NOT NULL
    AND e.`equipo_number` REGEXP '^[0-9]+$'
)
WHERE EXISTS (
    SELECT 1 FROM `equipos` e2 
    WHERE COALESCE(e2.`id_empresa_usuario`, 0) = ec.`id_empresa` 
    AND e2.`equipo_number` IS NOT NULL
);

