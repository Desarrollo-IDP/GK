-- Script para corregir el índice único compuesto en cartas_entrega
-- Este script elimina índices únicos antiguos y crea el índice compuesto correcto

-- Eliminar índice único antiguo de numero_carta si existe (sin id_empresa)
SET @idx_exists_old = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cartas_entrega' 
    AND INDEX_NAME = 'numero_carta'
    AND NON_UNIQUE = 0
);

SET @sql_drop_old = IF(@idx_exists_old > 0,
    'ALTER TABLE `cartas_entrega` DROP INDEX `numero_carta`',
    'SELECT "Índice antiguo no existe" AS message'
);

PREPARE stmt_drop FROM @sql_drop_old;
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

-- Verificar si ya existe el índice compuesto
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cartas_entrega' 
    AND INDEX_NAME = 'idx_numero_carta_empresa'
);

-- Si existe id_empresa pero no el índice compuesto, crearlo
SET @sql = IF(@col_exists > 0 AND @idx_exists = 0,
    'ALTER TABLE `cartas_entrega` ADD UNIQUE INDEX `idx_numero_carta_empresa` (`numero_carta`, `id_empresa`)',
    IF(@col_exists = 0, 
        'SELECT "ERROR: Columna id_empresa no existe en cartas_entrega. Ejecuta primero sql/add_id_empresa_almacen.sql" AS message',
        'SELECT "Índice idx_numero_carta_empresa ya existe" AS message'
    )
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mostrar resumen
SELECT 
    'Verificación de Índices' AS tipo,
    CASE 
        WHEN @col_exists > 0 THEN '✅ Columna id_empresa existe'
        ELSE '❌ Columna id_empresa NO existe'
    END AS columna_id_empresa,
    CASE 
        WHEN @idx_exists > 0 THEN '✅ Índice idx_numero_carta_empresa existe'
        ELSE '❌ Índice idx_numero_carta_empresa NO existe'
    END AS indice_compuesto;

