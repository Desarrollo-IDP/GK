-- Script para verificar si las columnas id_empresa existen en las tablas del módulo de Almacén

SELECT 
    TABLE_NAME as 'Tabla',
    COLUMN_NAME as 'Columna',
    DATA_TYPE as 'Tipo',
    IS_NULLABLE as 'Permite NULL',
    COLUMN_DEFAULT as 'Valor por Defecto'
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('bodegas', 'almacenes', 'productos_almacen', 'cartas_entrega', 'historial_movimientos')
    AND COLUMN_NAME = 'id_empresa'
ORDER BY TABLE_NAME;

-- Si no hay resultados, las columnas no existen
-- Si hay resultados, las columnas existen

