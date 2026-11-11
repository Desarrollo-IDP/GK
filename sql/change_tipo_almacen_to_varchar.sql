-- Script para cambiar el campo estado_almacen de ENUM a VARCHAR
-- Esto permitirá guardar cualquier valor, no solo los valores predefinidos
-- Fecha: 2025

-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar este script

-- Paso 1: Verificar que la tabla existe
-- SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'almacenes';

-- Paso 2: Cambiar estado_almacen de ENUM a VARCHAR en la tabla almacenes
-- Esto preservará todos los datos existentes
ALTER TABLE `almacenes` 
MODIFY COLUMN `tipo_almacen` VARCHAR(50) DEFAULT 'Disponible' 
COMMENT 'Estado del almacén (ahora acepta cualquier valor)';

-- Paso 3: Verificar que el cambio se aplicó correctamente
-- SELECT COLUMN_TYPE, COLUMN_DEFAULT, COLUMN_COMMENT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME = 'almacenes' 
-- AND COLUMN_NAME = 'estado_almacen';

-- NOTA: Después de ejecutar este script, el campo estado_almacen podrá aceptar
-- cualquier valor de texto (hasta 50 caracteres), no solo los valores predefinidos
-- del ENUM ('Disponible', 'Ocupado', 'Mantenimiento', 'Reservado').
