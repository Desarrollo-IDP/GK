-- Script para agregar campo id_empresa a las tablas level y empresas
-- para implementar filtrado por empresa en el módulo de configuración

-- =====================================================
-- TABLA: level (Nivel del técnico)
-- =====================================================

-- Agregar columna id_empresa si no existe
ALTER TABLE `level` 
ADD COLUMN IF NOT EXISTS `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id`;

-- Crear índice para id_empresa
ALTER TABLE `level` 
ADD INDEX IF NOT EXISTS `idx_level_empresa` (`id_empresa`);

-- =====================================================
-- TABLA: empresas (Empresas de inventario)
-- =====================================================

-- NOTA IMPORTANTE: La tabla `empresas` tiene `id_empresa` como clave primaria
-- Por lo tanto, NO podemos agregar otro campo `id_empresa` para filtrado
-- El filtrado se hace directamente comparando el id_empresa (PK) con la empresa del usuario

-- Si necesitas agregar un campo adicional para relacionar con empresas_usuarios,
-- puedes usar un campo diferente como `id_empresa_usuario`:

-- ALTER TABLE `empresas` 
-- ADD COLUMN IF NOT EXISTS `id_empresa_usuario` INT(11) NULL DEFAULT NULL AFTER `direccion`;

-- Crear índice para id_empresa_usuario
-- ALTER TABLE `empresas` 
-- ADD INDEX IF NOT EXISTS `idx_empresas_usuario` (`id_empresa_usuario`);

-- Por defecto, el código PHP manejará el filtrado comparando directamente id_empresa con la empresa del usuario
-- Si prefieres usar un campo separado, descomenta las líneas anteriores y actualiza el código PHP

