-- Script para corregir el índice único en cartas_entrega
-- El problema: El índice único idx_numero_carta_empresa impide insertar múltiples productos en la misma carta
-- Solución: Eliminar el índice único y crear un índice normal (no único) para permitir múltiples productos por carta

-- Eliminar el índice único compuesto si existe
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cartas_entrega' 
    AND INDEX_NAME = 'idx_numero_carta_empresa'
    AND NON_UNIQUE = 0
);

SET @sql_drop = IF(@idx_exists > 0,
    'ALTER TABLE `cartas_entrega` DROP INDEX `idx_numero_carta_empresa`',
    'SELECT "Índice único idx_numero_carta_empresa no existe" AS message'
);

PREPARE stmt_drop FROM @sql_drop;
EXECUTE stmt_drop;
DEALLOCATE PREPARE stmt_drop;

-- Verificar si existe la columna id_empresa
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cartas_entrega' 
    AND COLUMN_NAME = 'id_empresa'
);

-- Crear índice normal (no único) para mejorar consultas pero permitir múltiples productos por carta
SET @idx_normal_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cartas_entrega' 
    AND INDEX_NAME = 'idx_numero_carta_empresa'
);

SET @sql_create = IF(@col_exists > 0 AND @idx_normal_exists = 0,
    'ALTER TABLE `cartas_entrega` ADD INDEX `idx_numero_carta_empresa` (`numero_carta`, `id_empresa`)',
    IF(@col_exists = 0, 
        'SELECT "ERROR: Columna id_empresa no existe en cartas_entrega" AS message',
        'SELECT "Índice idx_numero_carta_empresa ya existe (normal)" AS message'
    )
);

PREPARE stmt_create FROM @sql_create;
EXECUTE stmt_create;
DEALLOCATE PREPARE stmt_create;

-- Mostrar resumen
SELECT 
    'Corrección de Índice cartas_entrega' AS tipo,
    CASE 
        WHEN @idx_exists > 0 THEN 'Índice único eliminado correctamente'
        ELSE 'No había índice único que eliminar'
    END AS resultado_eliminacion,
    CASE 
        WHEN @col_exists > 0 AND @idx_normal_exists = 0 THEN 'Índice normal creado correctamente'
        WHEN @col_exists = 0 THEN 'ERROR: Columna id_empresa no existe'
        ELSE 'Índice normal ya existe'
    END AS resultado_creacion;
