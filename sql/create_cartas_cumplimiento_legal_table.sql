-- Script para crear la tabla de cartas de cumplimiento legal
-- Fecha: 2026-01-07

-- Paso 1: Crear la tabla sin la restricción de clave foránea
CREATE TABLE IF NOT EXISTS `cartas_cumplimiento_legal` (
  `id_carta` int(11) NOT NULL AUTO_INCREMENT,
  `id_empresa` int(11) NULL DEFAULT NULL,
  `id_requester` int(11) NOT NULL,
  `numero_carta` varchar(50) NOT NULL,
  `fecha_generacion` date NOT NULL,
  `tipo_cumplimiento` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `archivo_firmado` varchar(255) DEFAULT NULL,
  `fecha_firma` date DEFAULT NULL,
  `estado` enum('Pendiente','Firmada','Cancelada') DEFAULT 'Pendiente',
  `creado_por` varchar(100) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_carta`),
  KEY `idx_requester` (`id_requester`),
  KEY `idx_empresa` (`id_empresa`),
  KEY `idx_numero_carta` (`numero_carta`),
  KEY `idx_fecha_generacion` (`fecha_generacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Paso 2: Intentar agregar la restricción de clave foránea (opcional)
-- Si falla, puedes continuar sin ella ya que la integridad se maneja a nivel de aplicación
-- Descomenta las siguientes líneas si quieres intentar agregar la restricción:

-- ALTER TABLE `cartas_cumplimiento_legal`
-- ADD CONSTRAINT `fk_carta_requester` 
-- FOREIGN KEY (`id_requester`) 
-- REFERENCES `requesters` (`id`) 
-- ON DELETE CASCADE 
-- ON UPDATE CASCADE;

-- Crear tabla de contadores para cartas de cumplimiento legal
CREATE TABLE IF NOT EXISTS `carta_cumplimiento_counters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_empresa` int(11) NOT NULL,
  `current_counter` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_empresa_counter` (`id_empresa`),
  KEY `idx_empresa` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

