-- =====================================================
-- Script de Migración: Sistema de Procesamiento de Emails
-- Fase 1: Preparación de Base de Datos
-- =====================================================

-- 1. Crear tabla email_processing_log para registrar ejecuciones
CREATE TABLE IF NOT EXISTS `email_processing_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_empresa` INT(11) NOT NULL,
  `status` ENUM('success', 'error', 'timeout', 'skipped') NOT NULL,
  `emails_processed` INT(11) DEFAULT 0 COMMENT 'Número de correos procesados exitosamente',
  `emails_found` INT(11) DEFAULT 0 COMMENT 'Número de correos encontrados',
  `execution_time` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Tiempo de ejecución en segundos',
  `error_message` TEXT NULL COMMENT 'Mensaje de error si status es error o timeout',
  `last_processed_at` TIMESTAMP NULL COMMENT 'Fecha y hora del último procesamiento',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
  PRIMARY KEY (`id`),
  INDEX `idx_empresa_status` (`id_empresa`, `status`),
  INDEX `idx_last_processed` (`id_empresa`, `last_processed_at`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de ejecuciones de procesamiento de emails por empresa';

-- 2. Agregar columnas a empresa_email_config para control de procesamiento
ALTER TABLE `empresa_email_config` 
ADD COLUMN IF NOT EXISTS `last_processed_at` TIMESTAMP NULL AFTER `updated_at`,
ADD COLUMN IF NOT EXISTS `processing_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `activo`,
ADD COLUMN IF NOT EXISTS `max_emails_per_run` INT(11) NOT NULL DEFAULT 50 AFTER `processing_enabled`,
ADD COLUMN IF NOT EXISTS `processing_interval_minutes` INT(11) NOT NULL DEFAULT 5 AFTER `max_emails_per_run`,
ADD COLUMN IF NOT EXISTS `error_count` INT(11) NOT NULL DEFAULT 0 AFTER `processing_interval_minutes`,
ADD COLUMN IF NOT EXISTS `last_error` TEXT NULL AFTER `error_count`;

-- 3. Agregar índices para optimizar consultas
ALTER TABLE `empresa_email_config` 
ADD INDEX IF NOT EXISTS `idx_processing_enabled` (`processing_enabled`, `activo`),
ADD INDEX IF NOT EXISTS `idx_last_processed` (`last_processed_at`),
ADD INDEX IF NOT EXISTS `idx_error_count` (`error_count`);

-- 4. Comentarios en columnas nuevas
ALTER TABLE `empresa_email_config` 
MODIFY COLUMN `last_processed_at` TIMESTAMP NULL COMMENT 'Última vez que se procesaron emails para esta empresa',
MODIFY COLUMN `processing_enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Habilitado, 0=Deshabilitado para procesamiento automático',
MODIFY COLUMN `max_emails_per_run` INT(11) NOT NULL DEFAULT 50 COMMENT 'Máximo de correos a procesar por ejecución',
MODIFY COLUMN `processing_interval_minutes` INT(11) NOT NULL DEFAULT 5 COMMENT 'Intervalo mínimo en minutos entre procesamientos',
MODIFY COLUMN `error_count` INT(11) NOT NULL DEFAULT 0 COMMENT 'Contador de errores consecutivos',
MODIFY COLUMN `last_error` TEXT NULL COMMENT 'Último mensaje de error registrado';

-- Verificar que las tablas se crearon correctamente
SELECT 'Tabla email_processing_log creada correctamente' AS resultado;
SELECT 'Columnas agregadas a empresa_email_config correctamente' AS resultado;

