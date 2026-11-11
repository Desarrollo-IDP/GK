-- Script SIMPLE para agregar campo id_empresa a las tablas del módulo de Almacén
-- Ejecutar cada ALTER TABLE por separado si hay errores

-- =====================================================
-- TABLA: bodegas
-- =====================================================
ALTER TABLE `bodegas`
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_bodega`;

ALTER TABLE `bodegas`
ADD INDEX `idx_bodegas_empresa` (`id_empresa`);

-- =====================================================
-- TABLA: almacenes
-- =====================================================
ALTER TABLE `almacenes`
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_almacen`;

ALTER TABLE `almacenes`
ADD INDEX `idx_almacenes_empresa` (`id_empresa`);

-- =====================================================
-- TABLA: productos_almacen
-- =====================================================
ALTER TABLE `productos_almacen`
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_producto`;

ALTER TABLE `productos_almacen`
ADD INDEX `idx_productos_almacen_empresa` (`id_empresa`);

-- =====================================================
-- TABLA: cartas_entrega
-- =====================================================
ALTER TABLE `cartas_entrega`
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_carta`;

ALTER TABLE `cartas_entrega`
ADD INDEX `idx_cartas_entrega_empresa` (`id_empresa`);

-- =====================================================
-- TABLA: historial_movimientos
-- =====================================================
ALTER TABLE `historial_movimientos`
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_movimiento`;

ALTER TABLE `historial_movimientos`
ADD INDEX `idx_historial_movimientos_empresa` (`id_empresa`);

