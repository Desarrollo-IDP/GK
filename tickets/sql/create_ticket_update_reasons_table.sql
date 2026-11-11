-- Script para crear la tabla de motivos de actualización de tickets
-- Ejecutar este script en la base de datos antes de usar la funcionalidad

CREATE TABLE IF NOT EXISTS `ticket_update_reasons` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `reason` text NOT NULL COMMENT 'Motivo de la actualización',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(100) DEFAULT NULL COMMENT 'Usuario que agregó el motivo',
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
