-- =====================================================
-- Script de Migración: Sistema Multi-Empresa
-- Versión para phpMyAdmin
-- Fecha: 2025-01-27
-- Descripción: Agrega soporte multi-empresa a todos los módulos
-- =====================================================

-- IMPORTANTE: Ejecutar cada sección por separado si hay errores
-- Si una columna ya existe, el comando fallará pero puedes continuar

-- =====================================================
-- SECCIÓN 1: TABLA usuarios
-- =====================================================

-- Verificar y agregar columna id_empresa a usuarios
ALTER TABLE `usuarios` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `email`;

-- Agregar índice
ALTER TABLE `usuarios` 
ADD INDEX `idx_usuarios_empresa` (`id_empresa`);

-- =====================================================
-- SECCIÓN 2: TABLA tickets
-- =====================================================

-- Verificar y agregar columna id_empresa a tickets
ALTER TABLE `tickets` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `last_wa_message_id`;

-- Agregar índice
ALTER TABLE `tickets` 
ADD INDEX `idx_tickets_empresa` (`id_empresa`);

-- =====================================================
-- SECCIÓN 3: TABLA requesters
-- =====================================================

-- Verificar y agregar columna id_empresa a requesters
ALTER TABLE `requesters` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `email`;

-- Agregar índice
ALTER TABLE `requesters` 
ADD INDEX `idx_requesters_empresa` (`id_empresa`);

-- =====================================================
-- SECCIÓN 4: TABLA technicians
-- =====================================================

-- Verificar y agregar columna id_empresa a technicians
ALTER TABLE `technicians` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `email`;

-- Agregar índice
ALTER TABLE `technicians` 
ADD INDEX `idx_technicians_empresa` (`id_empresa`);

-- =====================================================
-- SECCIÓN 5: TABLA equipos (verificar si ya existe)
-- =====================================================

-- Si la columna id_empresa ya existe en equipos, este comando fallará pero puedes continuar
-- Verificar primero con: SHOW COLUMNS FROM equipos LIKE 'id_empresa';
ALTER TABLE `equipos` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_equipo`;

-- Agregar índice (solo si la columna se agregó)
ALTER TABLE `equipos` 
ADD INDEX `idx_equipos_empresa` (`id_empresa`);

-- =====================================================
-- SECCIÓN 6: TABLA bodegas
-- =====================================================

-- Verificar y agregar columna id_empresa a bodegas
ALTER TABLE `bodegas` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_bodega`;

-- Agregar índice
ALTER TABLE `bodegas` 
ADD INDEX `idx_bodegas_empresa` (`id_empresa`);

-- =====================================================
-- SECCIÓN 7: TABLA almacenes
-- =====================================================

-- Verificar y agregar columna id_empresa a almacenes
ALTER TABLE `almacenes` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_almacen`;

-- Agregar índice
ALTER TABLE `almacenes` 
ADD INDEX `idx_almacenes_empresa` (`id_empresa`);

-- =====================================================
-- SECCIÓN 8: TABLA productos_almacen
-- =====================================================

-- Verificar y agregar columna id_empresa a productos_almacen
ALTER TABLE `productos_almacen` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_producto`;

-- Agregar índice
ALTER TABLE `productos_almacen` 
ADD INDEX `idx_productos_empresa` (`id_empresa`);

-- =====================================================
-- SECCIÓN 9: TABLA cartas_entrega
-- =====================================================

-- Verificar y agregar columna id_empresa a cartas_entrega
ALTER TABLE `cartas_entrega` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_carta`;

-- Agregar índice
ALTER TABLE `cartas_entrega` 
ADD INDEX `idx_cartas_empresa` (`id_empresa`);

-- =====================================================
-- SECCIÓN 10: TABLA historial_movimientos (almacén)
-- =====================================================

-- Verificar y agregar columna id_empresa a historial_movimientos
ALTER TABLE `historial_movimientos` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_movimiento`;

-- Agregar índice
ALTER TABLE `historial_movimientos` 
ADD INDEX `idx_historial_empresa` (`id_empresa`);

-- =====================================================
-- SECCIÓN 11: TABLA historial_actividades (inventario)
-- =====================================================

-- Verificar y agregar columna id_empresa a historial_actividades
-- NOTA: Si la tabla no tiene columna id_actividad, usar FIRST en lugar de AFTER
ALTER TABLE `historial_actividades` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL FIRST;

-- Agregar índice
ALTER TABLE `historial_actividades` 
ADD INDEX `idx_historial_actividades_empresa` (`id_empresa`);

-- =====================================================
-- SECCIÓN 12: CREAR TABLA empresa_email_config
-- =====================================================

CREATE TABLE IF NOT EXISTS `empresa_email_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_empresa` INT(11) NOT NULL,
  `smtp_host` VARCHAR(255) NOT NULL,
  `smtp_port` INT(11) NOT NULL DEFAULT 587,
  `smtp_username` VARCHAR(255) NOT NULL,
  `smtp_password` VARCHAR(255) NOT NULL,
  `smtp_from_email` VARCHAR(255) NOT NULL,
  `smtp_from_name` VARCHAR(255) NOT NULL DEFAULT 'Sistema de Tickets',
  `imap_host` VARCHAR(255) NOT NULL,
  `imap_port` INT(11) NOT NULL DEFAULT 993,
  `imap_username` VARCHAR(255) NOT NULL,
  `imap_password` VARCHAR(255) NOT NULL,
  `imap_folder` VARCHAR(100) NOT NULL DEFAULT 'INBOX',
  `imap_encryption` VARCHAR(50) NOT NULL DEFAULT '/imap/ssl',
  `imap_options` VARCHAR(255) NOT NULL DEFAULT '/imap/ssl/validate-cert',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_empresa_email` (`id_empresa`),
  INDEX `idx_empresa_email_activo` (`id_empresa`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECCIÓN 13: ACTUALIZAR DATOS EXISTENTES
-- =====================================================

-- Actualizar tickets existentes: asignar empresa basada en requester
-- Este script asigna la empresa del requester al ticket
UPDATE `tickets` t
INNER JOIN `requesters` r ON t.requester_id = r.id
SET t.id_empresa = r.id_empresa
WHERE t.id_empresa IS NULL AND r.id_empresa IS NOT NULL;

-- =====================================================
-- SECCIÓN 14: CREAR VISTA (opcional)
-- =====================================================

-- Crear vista para facilitar consultas multi-empresa
CREATE OR REPLACE VIEW `v_usuarios_empresa` AS
SELECT 
    u.id_usuario,
    u.nombre_usuario,
    u.rol,
    u.id_empresa,
    e.nombre as empresa_nombre,
    u.email,
    u.can_view_inventory_history,
    u.can_view_ticket_history,
    u.can_manage_users
FROM usuarios u
LEFT JOIN empresas e ON u.id_empresa = e.id_empresa;

-- =====================================================
-- NOTAS IMPORTANTES:
-- =====================================================
-- 1. Después de ejecutar este script, se deben asignar empresas a los usuarios existentes
-- 2. Los tickets existentes se asignarán automáticamente si el requester tiene empresa
-- 3. Se debe crear una interfaz para gestionar la configuración de email por empresa
-- 4. El usuario 'admin' debe tener id_empresa = NULL para ver todo
-- 5. Si alguna tabla no existe, ese comando fallará pero puedes continuar con las demás

