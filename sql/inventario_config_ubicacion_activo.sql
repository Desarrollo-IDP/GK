-- Configuración de opciones de Ubicación y Activo por empresa (id_empresa_usuario del usuario logueado)
-- Ejecutar en la base de datos (phpMyAdmin o MySQL)

USE `grupo_gk`;

-- Opciones de Ubicación por empresa
CREATE TABLE IF NOT EXISTS `inventario_config_ubicaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_empresa_usuario` int(11) NOT NULL COMMENT 'Empresa del usuario logueado (misma que en equipos)',
  `valor` varchar(500) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_config_ubicaciones_empresa` (`id_empresa_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Opciones de ubicación para equipos por empresa';

-- Opciones de Activo del equipo por empresa
CREATE TABLE IF NOT EXISTS `inventario_config_activos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_empresa_usuario` int(11) NOT NULL COMMENT 'Empresa del usuario logueado (misma que en equipos)',
  `valor` varchar(500) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_config_activos_empresa` (`id_empresa_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Opciones de activo de equipo por empresa';
