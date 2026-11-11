-- Agregar columna id_empresa a la tabla catalogos
-- Para que cada empresa tenga sus propios catálogos independientes

ALTER TABLE `catalogos` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_catalogo`;

-- Agregar índice para mejorar el rendimiento de las consultas filtradas por empresa
ALTER TABLE `catalogos` 
ADD INDEX `idx_catalogos_empresa` (`id_empresa`);

