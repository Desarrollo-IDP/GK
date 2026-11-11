-- Script para agregar campos Ubicación y Activo del equipo a la tabla equipos
-- Ejecutar en la base de datos del inventario (por ejemplo en phpMyAdmin o MySQL)

-- Seleccionar la base de datos (evita el error "Base de datos no seleccionada")
USE `grupo_gk`;

-- Agregar columna ubicacion (texto libre)
ALTER TABLE `equipos` 
ADD COLUMN `ubicacion` VARCHAR(500) NULL DEFAULT NULL 
COMMENT 'Ubicación física o lógica del equipo' 
AFTER `observaciones`;

-- Agregar columna activo_equipo (texto libre - número/código de activo del equipo)
ALTER TABLE `equipos` 
ADD COLUMN `activo_equipo` VARCHAR(500) NULL DEFAULT NULL 
COMMENT 'Número o código de activo del equipo' 
AFTER `ubicacion`;
