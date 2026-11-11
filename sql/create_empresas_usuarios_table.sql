-- =====================================================
-- Script para crear tabla empresas_usuarios
-- Fecha: 2025-01-27
-- Descripción: Tabla para gestionar empresas de usuarios
-- =====================================================

-- Crear tabla empresas_usuarios
CREATE TABLE IF NOT EXISTS `empresas_usuarios` (
  `id_empresa` INT(11) NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(255) NOT NULL,
  `descripcion` TEXT NULL DEFAULT NULL,
  `direccion` VARCHAR(500) NULL DEFAULT NULL,
  `telefono` VARCHAR(50) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `contacto` VARCHAR(255) NULL DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_empresa`),
  INDEX `idx_empresas_usuarios_activo` (`activo`),
  INDEX `idx_empresas_usuarios_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar empresa por defecto (opcional)
-- INSERT INTO empresas_usuarios (nombre, descripcion, activo) VALUES ('Empresa Principal', 'Empresa principal del sistema', 1);

-- =====================================================
-- Actualizar tabla usuarios para usar empresas_usuarios
-- =====================================================

-- Verificar que la columna id_empresa existe en usuarios
-- Si no existe, se agregará automáticamente por el script de migración anterior

-- Agregar foreign key (opcional, puede causar problemas si hay datos existentes)
-- ALTER TABLE `usuarios` 
-- ADD CONSTRAINT `fk_usuarios_empresa` 
-- FOREIGN KEY (`id_empresa`) REFERENCES `empresas_usuarios` (`id_empresa`) 
-- ON DELETE SET NULL ON UPDATE CASCADE;

-- =====================================================
-- Notas:
-- =====================================================
-- 1. Esta tabla es independiente de la tabla 'empresas' que se usa para inventario
-- 2. Los usuarios se asociarán a empresas_usuarios mediante id_empresa
-- 3. Si ya hay datos en usuarios con id_empresa apuntando a 'empresas', 
--    necesitarás migrar esos datos manualmente

