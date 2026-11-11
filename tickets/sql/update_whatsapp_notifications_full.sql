-- =============================================================================
-- Actualización completa de BD — rama whatsapp + notificaciones + correo
-- =============================================================================
-- Base recomendada: grupo_gk (o la que use el módulo tickets)
--
-- Características:
--   - Seguro para ejecutar más de una vez (idempotente)
--   - Agrega solo lo que falte (columnas, tablas, índices, catálogo)
--
-- Cómo ejecutarlo:
--   1) phpMyAdmin → Importar / SQL
--   2) o mysql -u USUARIO -p NOMBRE_BD < tickets/sql/update_whatsapp_notifications_full.sql
-- =============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Helpers: procedimientos temporales para ALTER seguro
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
DROP PROCEDURE IF EXISTS sp_add_index_if_missing;

DELIMITER $$

CREATE PROCEDURE sp_add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE sp_add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- =============================================================================
-- 1) Correo / mensajes — columnas base en ticket_messages
-- =============================================================================
CALL sp_add_column_if_missing(
    'ticket_messages',
    'is_email',
    '`is_email` TINYINT(1) DEFAULT 0'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'message_id',
    '`message_id` VARCHAR(255) NULL'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'parent_message_id',
    '`parent_message_id` BIGINT UNSIGNED NULL'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'in_reply_to',
    '`in_reply_to` VARCHAR(255) NULL'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'references_msg',
    '`references_msg` TEXT NULL'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'cc_email',
    '`cc_email` TEXT NULL'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'message_html',
    '`message_html` LONGTEXT NULL AFTER `message`'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'message_text',
    '`message_text` LONGTEXT NULL AFTER `message_html`'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'headers_raw',
    '`headers_raw` LONGTEXT NULL AFTER `message_text`'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'headers_json',
    '`headers_json` LONGTEXT NULL AFTER `headers_raw`'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'raw_source',
    '`raw_source` LONGTEXT NULL AFTER `headers_json`'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'content_type',
    '`content_type` VARCHAR(255) NULL AFTER `raw_source`'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'imap_uid',
    '`imap_uid` BIGINT NULL DEFAULT NULL'
);

-- =============================================================================
-- 2) WhatsApp — columnas en ticket_messages
-- =============================================================================
CALL sp_add_column_if_missing(
    'ticket_messages',
    'is_whatsapp',
    '`is_whatsapp` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Mensaje vía WhatsApp'' AFTER `is_email`'
);

CALL sp_add_column_if_missing(
    'ticket_messages',
    'whatsapp_type',
    '`whatsapp_type` VARCHAR(32) DEFAULT NULL COMMENT ''text|image|audio|document|...'' AFTER `is_whatsapp`'
);

-- =============================================================================
-- 3) WhatsApp — columna en tickets
-- =============================================================================
CALL sp_add_column_if_missing(
    'tickets',
    'last_wa_message_id',
    '`last_wa_message_id` VARCHAR(255) DEFAULT NULL COMMENT ''Último wamid de WhatsApp'''
);

CALL sp_add_column_if_missing(
    'tickets',
    'whatsapp_enabled',
    '`whatsapp_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = muestra chat y permite envío WhatsApp en view'''
);

-- =============================================================================
-- 4) Cierre por inactividad
-- =============================================================================
CALL sp_add_column_if_missing(
    'tickets',
    'closed_by_inactivity',
    '`closed_by_inactivity` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = cerrado por inactividad'''
);

-- =============================================================================
-- 5) Adjuntos — columnas usadas por correo / WhatsApp
-- =============================================================================
CALL sp_add_column_if_missing(
    'ticket_attachments',
    'ticket_message_id',
    '`ticket_message_id` BIGINT UNSIGNED NULL'
);

CALL sp_add_column_if_missing(
    'ticket_attachments',
    'content_id',
    '`content_id` VARCHAR(255) NULL COMMENT ''CID de imágenes inline en correos'''
);

CALL sp_add_column_if_missing(
    'ticket_attachments',
    'mime_type',
    '`mime_type` VARCHAR(255) NULL'
);

CALL sp_add_column_if_missing(
    'ticket_attachments',
    'is_inline',
    '`is_inline` TINYINT(1) NOT NULL DEFAULT 0'
);

CALL sp_add_column_if_missing(
    'ticket_attachments',
    'file_path',
    '`file_path` VARCHAR(500) NULL'
);

CALL sp_add_column_if_missing(
    'ticket_attachments',
    'filename',
    '`filename` VARCHAR(255) NULL'
);

CALL sp_add_column_if_missing(
    'ticket_attachments',
    'size',
    '`size` INT NULL'
);

-- =============================================================================
-- 6) Índices útiles (solo si faltan)
-- =============================================================================
CALL sp_add_index_if_missing(
    'ticket_messages',
    'idx_tm_ticket_created',
    'INDEX `idx_tm_ticket_created` (`ticket_id`, `created_at`)'
);

CALL sp_add_index_if_missing(
    'ticket_messages',
    'idx_tm_is_whatsapp',
    'INDEX `idx_tm_is_whatsapp` (`is_whatsapp`)'
);

CALL sp_add_index_if_missing(
    'ticket_messages',
    'idx_tm_is_email',
    'INDEX `idx_tm_is_email` (`is_email`)'
);

CALL sp_add_index_if_missing(
    'ticket_messages',
    'idx_tm_message_id',
    'INDEX `idx_tm_message_id` (`message_id`)'
);

CALL sp_add_index_if_missing(
    'ticket_attachments',
    'idx_ta_ticket_message',
    'INDEX `idx_ta_ticket_message` (`ticket_message_id`)'
);

CALL sp_add_index_if_missing(
    'tickets',
    'idx_tickets_closed_at',
    'INDEX `idx_tickets_closed_at` (`closed_at`)'
);

CALL sp_add_index_if_missing(
    'tickets',
    'idx_tickets_last_wa',
    'INDEX `idx_tickets_last_wa` (`last_wa_message_id`)'
);

-- =============================================================================
-- 7) Notificaciones — lecturas por usuario
-- =============================================================================
CREATE TABLE IF NOT EXISTS `ticket_notification_reads` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `message_id` INT NOT NULL,
    `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_message` (`user_id`, `message_id`),
    KEY `idx_user_read_at` (`user_id`, `read_at`),
    KEY `idx_message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Marca mensajes entrantes como leídos por usuario (campana de notificaciones)';

-- =============================================================================
-- 8) Catálogo — modo de contacto WhatsApp (solo si existe la tabla)
-- =============================================================================
SET @has_contact_modes := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'contact_modes'
);

-- Crear catálogo mínimo si no existe (no destructivo)
SET @sql := IF(
    @has_contact_modes = 0,
    'CREATE TABLE `contact_modes` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_empresa` INT NULL DEFAULT NULL,
        `name` VARCHAR(50) NOT NULL,
        `description` TEXT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_contact_modes := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'contact_modes'
);

SET @sql := IF(
    @has_contact_modes > 0,
    'INSERT INTO `contact_modes` (`name`)
     SELECT ''WhatsApp''
     WHERE NOT EXISTS (
         SELECT 1 FROM `contact_modes` WHERE LOWER(`name`) LIKE ''%whatsapp%''
     )',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migración: activar el flag en tickets que ya usaban WhatsApp
SET @has_contact_mode_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tickets'
      AND COLUMN_NAME = 'contact_mode_id'
);

SET @sql := IF(
    @has_contact_modes > 0 AND @has_contact_mode_id > 0,
    'UPDATE `tickets` t
     LEFT JOIN `contact_modes` cm ON cm.id = t.contact_mode_id
     SET t.`whatsapp_enabled` = 1
     WHERE t.`whatsapp_enabled` = 0
       AND (
             (t.`last_wa_message_id` IS NOT NULL AND t.`last_wa_message_id` <> '''')
             OR LOWER(COALESCE(cm.`name`, '''')) LIKE ''%whatsapp%''
             OR EXISTS (
                 SELECT 1
                 FROM `ticket_messages` tm
                 WHERE tm.`ticket_id` = t.`id`
                   AND tm.`is_whatsapp` = 1
             )
           )',
    'UPDATE `tickets` t
     SET t.`whatsapp_enabled` = 1
     WHERE t.`whatsapp_enabled` = 0
       AND (
             (t.`last_wa_message_id` IS NOT NULL AND t.`last_wa_message_id` <> '''')
             OR EXISTS (
                 SELECT 1
                 FROM `ticket_messages` tm
                 WHERE tm.`ticket_id` = t.`id`
                   AND tm.`is_whatsapp` = 1
             )
           )'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================================================
-- 9) Limpieza de procedimientos temporales
-- =============================================================================
DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
DROP PROCEDURE IF EXISTS sp_add_index_if_missing;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- =============================================================================
-- 10) Verificación rápida (opcional: revisar resultado en phpMyAdmin)
-- =============================================================================
SELECT 'ticket_messages.is_whatsapp' AS item,
       CASE WHEN COUNT(*) > 0 THEN 'OK' ELSE 'FALTA' END AS status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_messages' AND COLUMN_NAME = 'is_whatsapp'

UNION ALL
SELECT 'ticket_messages.whatsapp_type',
       CASE WHEN COUNT(*) > 0 THEN 'OK' ELSE 'FALTA' END
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_messages' AND COLUMN_NAME = 'whatsapp_type'

UNION ALL
SELECT 'tickets.last_wa_message_id',
       CASE WHEN COUNT(*) > 0 THEN 'OK' ELSE 'FALTA' END
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'last_wa_message_id'

UNION ALL
SELECT 'tickets.whatsapp_enabled',
       CASE WHEN COUNT(*) > 0 THEN 'OK' ELSE 'FALTA' END
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'whatsapp_enabled'

UNION ALL
SELECT 'tickets.closed_by_inactivity',
       CASE WHEN COUNT(*) > 0 THEN 'OK' ELSE 'FALTA' END
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'closed_by_inactivity'

UNION ALL
SELECT 'ticket_messages.message_html',
       CASE WHEN COUNT(*) > 0 THEN 'OK' ELSE 'FALTA' END
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_messages' AND COLUMN_NAME = 'message_html'

UNION ALL
SELECT 'ticket_attachments.content_id',
       CASE WHEN COUNT(*) > 0 THEN 'OK' ELSE 'FALTA' END
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_attachments' AND COLUMN_NAME = 'content_id'

UNION ALL
SELECT 'ticket_attachments.ticket_message_id',
       CASE WHEN COUNT(*) > 0 THEN 'OK' ELSE 'FALTA' END
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_attachments' AND COLUMN_NAME = 'ticket_message_id'

UNION ALL
SELECT 'tabla ticket_notification_reads',
       CASE WHEN COUNT(*) > 0 THEN 'OK' ELSE 'FALTA' END
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_notification_reads'

UNION ALL
SELECT 'tabla contact_modes',
       CASE WHEN COUNT(*) > 0 THEN 'OK' ELSE 'FALTA' END
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_modes';

-- Verificación opcional del catálogo WhatsApp (solo si la tabla existe)
SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_modes'
    ) > 0,
    'SELECT ''contact_modes WhatsApp'' AS item,
            CASE WHEN COUNT(*) > 0 THEN ''OK'' ELSE ''FALTA'' END AS status
     FROM `contact_modes`
     WHERE LOWER(`name`) LIKE ''%whatsapp%''',
    'SELECT ''contact_modes WhatsApp'' AS item, ''OMITIDO (sin tabla)'' AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Fin del script
