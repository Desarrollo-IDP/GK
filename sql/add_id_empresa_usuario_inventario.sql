-- Script para agregar campo id_empresa_usuario a tablas de inventario
-- para relacionar con empresas_usuarios del sistema multi-empresa

-- =====================================================
-- TABLA: equipos
-- =====================================================

-- Agregar columna id_empresa_usuario para relacionar con empresas_usuarios
ALTER TABLE `equipos` 
ADD COLUMN IF NOT EXISTS `id_empresa_usuario` INT(11) NULL DEFAULT NULL AFTER `id_empresa`;

-- Crear índice para id_empresa_usuario
ALTER TABLE `equipos` 
ADD INDEX IF NOT EXISTS `idx_equipos_empresa_usuario` (`id_empresa_usuario`);

-- =====================================================
-- TABLA: historial_actividades
-- =====================================================

-- Agregar columna id_empresa para filtrar historial por empresa
ALTER TABLE `historial_actividades` 
ADD COLUMN IF NOT EXISTS `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id`;

-- Crear índice para id_empresa
ALTER TABLE `historial_actividades` 
ADD INDEX IF NOT EXISTS `idx_historial_empresa` (`id_empresa`);

-- =====================================================
-- TABLA: empresas (inventario)
-- =====================================================

-- Agregar columna id_empresa_usuario para relacionar empresas de inventario con empresas_usuarios
ALTER TABLE `empresas` 
ADD COLUMN IF NOT EXISTS `id_empresa_usuario` INT(11) NULL DEFAULT NULL AFTER `direccion`;

-- Crear índice para id_empresa_usuario
ALTER TABLE `empresas` 
ADD INDEX IF NOT EXISTS `idx_empresas_usuario` (`id_empresa_usuario`);

-- =====================================================
-- NOTAS IMPORTANTES
-- =====================================================

-- 1. La tabla `equipos` tiene dos campos relacionados con empresas:
--    - `id_empresa`: Apunta a tabla `empresas` (inventario) - MANTENER
--    - `id_empresa_usuario`: Apunta a tabla `empresas_usuarios` - NUEVO

-- 2. La tabla `empresas` (inventario) ahora puede relacionarse con `empresas_usuarios`
--    mediante el campo `id_empresa_usuario`

-- 3. Después de ejecutar este script, se debe:
--    a) Asignar `id_empresa_usuario` a los equipos existentes según corresponda
--    b) Asignar `id_empresa_usuario` a las empresas de inventario existentes
--    c) Actualizar el código PHP para usar estos nuevos campos

-- 4. Para migrar datos existentes:
--    UPDATE equipos SET id_empresa_usuario = 1 WHERE id_empresa IN (SELECT id_empresa FROM empresas WHERE ...);
--    (Ajustar según la lógica de negocio)

