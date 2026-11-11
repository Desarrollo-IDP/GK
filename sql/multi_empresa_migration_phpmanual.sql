-- =====================================================
-- Script de Migración: Sistema Multi-Empresa
-- Versión Manual para phpMyAdmin (ejecutar uno por uno)
-- Fecha: 2025-01-27
-- =====================================================
-- INSTRUCCIONES:
-- 1. Copiar y pegar cada comando individualmente en phpMyAdmin
-- 2. Ejecutar uno por uno
-- 3. Si un comando falla porque la columna ya existe, continuar con el siguiente
-- =====================================================

-- 1. Agregar columna id_empresa a usuarios
ALTER TABLE `usuarios` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `email`;

-- 2. Agregar índice a usuarios
ALTER TABLE `usuarios` ADD INDEX `idx_usuarios_empresa` (`id_empresa`);

-- 3. Agregar columna id_empresa a tickets
ALTER TABLE `tickets` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `last_wa_message_id`;

-- 4. Agregar índice a tickets
ALTER TABLE `tickets` ADD INDEX `idx_tickets_empresa` (`id_empresa`);

-- 5. Agregar columna id_empresa a requesters
ALTER TABLE `requesters` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `email`;

-- 6. Agregar índice a requesters
ALTER TABLE `requesters` ADD INDEX `idx_requesters_empresa` (`id_empresa`);

-- 7. Agregar columna id_empresa a technicians
ALTER TABLE `technicians` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `email`;

-- 8. Agregar índice a technicians
ALTER TABLE `technicians` ADD INDEX `idx_technicians_empresa` (`id_empresa`);

-- 9. Agregar columna id_empresa a equipos (si no existe)
ALTER TABLE `equipos` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_equipo`;

-- 10. Agregar índice a equipos
ALTER TABLE `equipos` ADD INDEX `idx_equipos_empresa` (`id_empresa`);

-- 11. Agregar columna id_empresa a bodegas
ALTER TABLE `bodegas` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_bodega`;

-- 12. Agregar índice a bodegas
ALTER TABLE `bodegas` ADD INDEX `idx_bodegas_empresa` (`id_empresa`);

-- 13. Agregar columna id_empresa a almacenes
ALTER TABLE `almacenes` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_almacen`;

-- 14. Agregar índice a almacenes
ALTER TABLE `almacenes` ADD INDEX `idx_almacenes_empresa` (`id_almacen`);

-- 15. Agregar columna id_empresa a productos_almacen
ALTER TABLE `productos_almacen` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_producto`;

-- 16. Agregar índice a productos_almacen
ALTER TABLE `productos_almacen` ADD INDEX `idx_productos_empresa` (`id_empresa`);

-- 17. Agregar columna id_empresa a cartas_entrega
ALTER TABLE `cartas_entrega` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_carta`;

-- 18. Agregar índice a cartas_entrega
ALTER TABLE `cartas_entrega` ADD INDEX `idx_cartas_empresa` (`id_empresa`);

-- 19. Agregar columna id_empresa a historial_movimientos
ALTER TABLE `historial_movimientos` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_movimiento`;

-- 20. Agregar índice a historial_movimientos
ALTER TABLE `historial_movimientos` ADD INDEX `idx_historial_empresa` (`id_empresa`);

-- 21. Agregar columna id_empresa a historial_actividades
-- NOTA: La tabla usa 'id' como primera columna, no 'id_actividad'
ALTER TABLE `historial_actividades` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id`;

-- 22. Agregar índice a historial_actividades
ALTER TABLE `historial_actividades` ADD INDEX `idx_historial_actividades_empresa` (`id_empresa`);

-- 23. Crear tabla empresa_email_config
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

-- 24. Actualizar tickets existentes con empresa del requester
UPDATE `tickets` t
INNER JOIN `requesters` r ON t.requester_id = r.id
SET t.id_empresa = r.id_empresa
WHERE t.id_empresa IS NULL AND r.id_empresa IS NOT NULL;

-- 25. Crear vista v_usuarios_empresa (opcional)
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

