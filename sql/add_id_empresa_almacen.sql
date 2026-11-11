-- Script para agregar campo id_empresa a las tablas del módulo de Almacén
-- Este script permite filtrar datos por empresa de usuario (sucursal)

-- =====================================================
-- TABLA: bodegas
-- =====================================================

-- Agregar columna id_empresa si no existe
ALTER TABLE `bodegas`
ADD COLUMN IF NOT EXISTS `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_bodega`;

-- Crear índice para id_empresa
ALTER TABLE `bodegas`
ADD INDEX IF NOT EXISTS `idx_bodegas_empresa` (`id_empresa`);

-- =====================================================
-- TABLA: almacenes
-- =====================================================

-- Agregar columna id_empresa si no existe
ALTER TABLE `almacenes`
ADD COLUMN IF NOT EXISTS `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_almacen`;

-- Crear índice para id_empresa
ALTER TABLE `almacenes`
ADD INDEX IF NOT EXISTS `idx_almacenes_empresa` (`id_empresa`);

-- =====================================================
-- TABLA: productos_almacen
-- =====================================================

-- Agregar columna id_empresa si no existe
ALTER TABLE `productos_almacen`
ADD COLUMN IF NOT EXISTS `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_producto`;

-- Crear índice para id_empresa
ALTER TABLE `productos_almacen`
ADD INDEX IF NOT EXISTS `idx_productos_almacen_empresa` (`id_empresa`);

-- =====================================================
-- TABLA: cartas_entrega
-- =====================================================

-- Agregar columna id_empresa si no existe
ALTER TABLE `cartas_entrega`
ADD COLUMN IF NOT EXISTS `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_carta`;

-- Crear índice para id_empresa
ALTER TABLE `cartas_entrega`
ADD INDEX IF NOT EXISTS `idx_cartas_entrega_empresa` (`id_empresa`);

-- =====================================================
-- TABLA: historial_movimientos
-- =====================================================

-- Agregar columna id_empresa si no existe
ALTER TABLE `historial_movimientos`
ADD COLUMN IF NOT EXISTS `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_movimiento`;

-- Crear índice para id_empresa
ALTER TABLE `historial_movimientos`
ADD INDEX IF NOT EXISTS `idx_historial_movimientos_empresa` (`id_empresa`);

