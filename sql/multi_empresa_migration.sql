-- =====================================================
-- Script de Migración: Sistema Multi-Empresa
-- Fecha: 2025-01-27
-- Descripción: Agrega soporte multi-empresa a todos los módulos
-- =====================================================

-- 1. Agregar columna id_empresa a tabla usuarios
ALTER TABLE `usuarios` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `email`;

ALTER TABLE `usuarios` 
ADD INDEX `idx_usuarios_empresa` (`id_empresa`);

-- 2. Agregar columna id_empresa a tabla tickets
ALTER TABLE `tickets` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `last_wa_message_id`;

ALTER TABLE `tickets` 
ADD INDEX `idx_tickets_empresa` (`id_empresa`);

-- 3. Agregar columna id_empresa a tabla requesters (solicitantes)
ALTER TABLE `requesters` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `email`;

ALTER TABLE `requesters` 
ADD INDEX `idx_requesters_empresa` (`id_empresa`);

-- 4. Agregar columna id_empresa a tabla technicians (técnicos)
ALTER TABLE `technicians` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `email`;

ALTER TABLE `technicians` 
ADD INDEX `idx_technicians_empresa` (`id_empresa`);

-- 5. Agregar columna id_empresa a tabla equipos (ya existe, verificar)
-- Si no existe, ejecutar:
-- ALTER TABLE `equipos` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_equipo`;

-- 6. Agregar columna id_empresa a tabla bodegas
ALTER TABLE `bodegas` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_bodega`;

ALTER TABLE `bodegas` 
ADD INDEX `idx_bodegas_empresa` (`id_empresa`);

-- 7. Agregar columna id_empresa a tabla almacenes
ALTER TABLE `almacenes` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_almacen`;

ALTER TABLE `almacenes` 
ADD INDEX `idx_almacenes_empresa` (`id_empresa`);

-- 8. Agregar columna id_empresa a tabla productos_almacen
ALTER TABLE `productos_almacen` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_producto`;

ALTER TABLE `productos_almacen` 
ADD INDEX `idx_productos_empresa` (`id_empresa`);

-- 9. Agregar columna id_empresa a tabla cartas_entrega
ALTER TABLE `cartas_entrega` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_carta`;

ALTER TABLE `cartas_entrega` 
ADD INDEX `idx_cartas_empresa` (`id_empresa`);

-- 10. Crear tabla para configuración de email por empresa
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

-- 11. Agregar columna id_empresa a tabla historial_movimientos (almacén)
ALTER TABLE `historial_movimientos` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_movimiento`;

ALTER TABLE `historial_movimientos` 
ADD INDEX `idx_historial_empresa` (`id_empresa`);

-- 12. Agregar columna id_empresa a tabla historial_actividades (inventario)
-- NOTA: La tabla usa 'id' como primera columna, no 'id_actividad'
ALTER TABLE `historial_actividades` 
ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id`;

ALTER TABLE `historial_actividades` 
ADD INDEX `idx_historial_actividades_empresa` (`id_empresa`);

-- 13. Actualizar usuarios existentes: asignar empresa NULL (admin puede ver todo)
-- Los usuarios existentes quedarán sin empresa asignada, se deben asignar manualmente

-- 14. Actualizar tickets existentes: asignar empresa basada en requester
-- Este script asigna la empresa del requester al ticket
UPDATE `tickets` t
INNER JOIN `requesters` r ON t.requester_id = r.id
SET t.id_empresa = r.id_empresa
WHERE t.id_empresa IS NULL AND r.id_empresa IS NOT NULL;

-- 15. Actualizar equipos existentes: mantener id_empresa si ya existe
-- Si la columna ya existe, no hacer nada

-- 16. Crear vista para facilitar consultas multi-empresa
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

-- Notas importantes:
-- 1. Después de ejecutar este script, se deben asignar empresas a los usuarios existentes
-- 2. Los tickets existentes se asignarán automáticamente si el requester tiene empresa
-- 3. Se debe crear una interfaz para gestionar la configuración de email por empresa
-- 4. El usuario 'admin' debe tener id_empresa = NULL para ver todo

