-- Tabla para gestionar catálogos configurables del sistema
CREATE TABLE IF NOT EXISTS catalogos (
    id_catalogo INT AUTO_INCREMENT PRIMARY KEY,
    modulo VARCHAR(50) NOT NULL COMMENT 'Módulo: bodega, almacen, productos',
    campo VARCHAR(100) NOT NULL COMMENT 'Campo al que pertenece',
    valor VARCHAR(255) NOT NULL COMMENT 'Valor del catálogo',
    activo TINYINT(1) DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
    orden INT DEFAULT 0 COMMENT 'Orden de visualización',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_catalogo (modulo, campo, valor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar valores por defecto para Bodegas
INSERT INTO catalogos (modulo, campo, valor, orden) VALUES
('bodega', 'estado_bodega', 'Activa', 1),
('bodega', 'estado_bodega', 'Inactiva', 2),
('bodega', 'estado_bodega', 'Mantenimiento', 3);

-- Insertar valores por defecto para Almacenes
INSERT INTO catalogos (modulo, campo, valor, orden) VALUES
('almacen', 'tipo_almacen', 'General', 1),
('almacen', 'tipo_almacen', 'Refrigerado', 2),
('almacen', 'tipo_almacen', 'Congelado', 3),
('almacen', 'tipo_almacen', 'Peligroso', 4),
('almacen', 'tipo_almacen', 'Seguro', 5),
('almacen', 'estado_almacen', 'Disponible', 1),
('almacen', 'estado_almacen', 'Ocupado', 2),
('almacen', 'estado_almacen', 'Mantenimiento', 3),
('almacen', 'estado_almacen', 'Reservado', 4);

-- Insertar valores por defecto para Productos
INSERT INTO catalogos (modulo, campo, valor, orden) VALUES
('productos', 'categoria', 'Electrónica', 1),
('productos', 'categoria', 'Herramientas', 2),
('productos', 'categoria', 'Materia Prima', 3),
('productos', 'categoria', 'Consumibles', 4),
('productos', 'estado_producto', 'Disponible', 1),
('productos', 'estado_producto', 'Reservado', 2),
('productos', 'estado_producto', 'Entregado', 3),
('productos', 'estado_producto', 'Dañado', 4);

