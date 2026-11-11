-- Script para crear tabla de contadores de tickets por empresa
-- Este script permite que cada empresa tenga su propio contador independiente

-- Crear tabla de contadores por empresa
CREATE TABLE IF NOT EXISTS `ticket_counters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_empresa` int(11) NOT NULL,
  `current_counter` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_empresa_counter` (`id_empresa`),
  KEY `idx_empresa` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Agregar columna ticket_number a la tabla tickets si no existe
-- Esta columna almacenará el número visible del ticket (por empresa)
ALTER TABLE `tickets` 
ADD COLUMN IF NOT EXISTS `ticket_number` VARCHAR(50) NULL DEFAULT NULL AFTER `id`;

-- Crear índice para ticket_number
ALTER TABLE `tickets` 
ADD INDEX IF NOT EXISTS `idx_ticket_number` (`ticket_number`);

-- Crear índice único compuesto para ticket_number e id_empresa
-- Esto permite que el mismo ticket_number exista en diferentes empresas
ALTER TABLE `tickets` 
ADD UNIQUE INDEX IF NOT EXISTS `idx_ticket_number_empresa` (`ticket_number`, `id_empresa`);

-- Inicializar contadores para empresas existentes
-- Esto crea un registro inicial con contador en 0 para cada empresa
INSERT INTO `ticket_counters` (`id_empresa`, `current_counter`)
SELECT DISTINCT `id_empresa`, 0
FROM `empresas_usuarios`
WHERE `id_empresa` IS NOT NULL
ON DUPLICATE KEY UPDATE `current_counter` = `current_counter`;

-- Si hay tickets existentes, inicializar el contador al máximo ticket_number existente por empresa
-- Esto asegura que los nuevos tickets continúen desde donde quedaron
UPDATE `ticket_counters` tc
SET `current_counter` = (
    SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(`ticket_number`, '-', -1) AS UNSIGNED)), 0)
    FROM `tickets` t
    WHERE t.`id_empresa` = tc.`id_empresa`
    AND t.`ticket_number` IS NOT NULL
    AND t.`ticket_number` REGEXP '^[0-9]+$'
)
WHERE EXISTS (
    SELECT 1 FROM `tickets` t2 
    WHERE t2.`id_empresa` = tc.`id_empresa` 
    AND t2.`ticket_number` IS NOT NULL
);

