-- Base de datos para Sistema de Gestión de Almacén
-- Creado: 2025

-- Tabla de Bodegas
CREATE TABLE IF NOT EXISTS bodegas (
    id_bodega INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    ubicacion TEXT NOT NULL,
    direccion VARCHAR(500),
    ciudad VARCHAR(100),
    estado VARCHAR(100),
    codigo_postal VARCHAR(10),
    telefono VARCHAR(20),
    responsable VARCHAR(255),
    capacidad_total DECIMAL(10,2),
    unidad_medida VARCHAR(50) DEFAULT 'm²',
    estado_bodega ENUM('Activa', 'Inactiva', 'Mantenimiento') DEFAULT 'Activa',
    notas TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre),
    INDEX idx_estado (estado_bodega)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Almacenes (dentro de las bodegas)
CREATE TABLE IF NOT EXISTS almacenes (
    id_almacen INT AUTO_INCREMENT PRIMARY KEY,
    id_bodega INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    codigo VARCHAR(50) UNIQUE,
    ubicacion_especifica VARCHAR(500),
    pasillo VARCHAR(50),
    seccion VARCHAR(50),
    nivel VARCHAR(50),
    capacidad DECIMAL(10,2),
    capacidad_actual DECIMAL(10,2) DEFAULT 0,
    unidad_medida VARCHAR(50) DEFAULT 'm²',
    temperatura VARCHAR(50),
    humedad VARCHAR(50),
    tipo_almacen ENUM('General', 'Refrigerado', 'Congelado', 'Peligroso', 'Seguro') DEFAULT 'General',
    estado_almacen ENUM('Disponible', 'Ocupado', 'Mantenimiento', 'Reservado') DEFAULT 'Disponible',
    notas TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_bodega) REFERENCES bodegas(id_bodega) ON DELETE CASCADE,
    INDEX idx_bodega (id_bodega),
    INDEX idx_codigo (codigo),
    INDEX idx_estado (estado_almacen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Productos en Almacén
CREATE TABLE IF NOT EXISTS productos_almacen (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    id_almacen INT NOT NULL,
    nombre_producto VARCHAR(255) NOT NULL,
    codigo_producto VARCHAR(100) UNIQUE,
    descripcion TEXT,
    categoria VARCHAR(100),
    marca VARCHAR(100),
    modelo VARCHAR(100),
    numero_serie VARCHAR(100),
    cantidad INT DEFAULT 0,
    unidad VARCHAR(50) DEFAULT 'piezas',
    precio_unitario DECIMAL(10,2),
    valor_total DECIMAL(12,2),
    ubicacion_exacta VARCHAR(255),
    lote VARCHAR(100),
    fecha_ingreso DATE,
    fecha_vencimiento DATE,
    estado_producto ENUM('Disponible', 'Reservado', 'Entregado', 'Dañado', 'Vencido') DEFAULT 'Disponible',
    stock_minimo INT DEFAULT 0,
    stock_maximo INT DEFAULT 0,
    proveedor VARCHAR(255),
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_almacen) REFERENCES almacenes(id_almacen) ON DELETE CASCADE,
    INDEX idx_almacen (id_almacen),
    INDEX idx_codigo (codigo_producto),
    INDEX idx_estado (estado_producto),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Cartas de Entrega
CREATE TABLE IF NOT EXISTS cartas_entrega (
    id_carta INT AUTO_INCREMENT PRIMARY KEY,
    numero_carta VARCHAR(50) UNIQUE NOT NULL,
    id_producto INT,
    cantidad_entregada INT NOT NULL,
    nombre_receptor VARCHAR(255) NOT NULL,
    identificacion_receptor VARCHAR(100),
    empresa_receptor VARCHAR(255),
    area_receptor VARCHAR(100),
    fecha_entrega DATE NOT NULL,
    fecha_devolucion DATE,
    tipo_entrega ENUM('Definitiva', 'Temporal', 'Préstamo') DEFAULT 'Definitiva',
    estado_entrega ENUM('Pendiente', 'Entregado', 'Devuelto', 'Vencido') DEFAULT 'Pendiente',
    observaciones TEXT,
    firmado_por VARCHAR(255),
    autorizado_por VARCHAR(255),
    documento_pdf VARCHAR(500),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_producto) REFERENCES productos_almacen(id_producto) ON DELETE SET NULL,
    INDEX idx_numero (numero_carta),
    INDEX idx_producto (id_producto),
    INDEX idx_estado (estado_entrega),
    INDEX idx_fecha_entrega (fecha_entrega)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Historial de Movimientos
CREATE TABLE IF NOT EXISTS historial_movimientos (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT,
    tipo_movimiento ENUM('Entrada', 'Salida', 'Transferencia', 'Ajuste', 'Entrega', 'Devolución') NOT NULL,
    cantidad INT NOT NULL,
    almacen_origen INT,
    almacen_destino INT,
    id_carta_entrega INT,
    usuario VARCHAR(255),
    motivo VARCHAR(500),
    observaciones TEXT,
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_producto) REFERENCES productos_almacen(id_producto) ON DELETE SET NULL,
    FOREIGN KEY (almacen_origen) REFERENCES almacenes(id_almacen) ON DELETE SET NULL,
    FOREIGN KEY (almacen_destino) REFERENCES almacenes(id_almacen) ON DELETE SET NULL,
    FOREIGN KEY (id_carta_entrega) REFERENCES cartas_entrega(id_carta) ON DELETE SET NULL,
    INDEX idx_producto (id_producto),
    INDEX idx_tipo (tipo_movimiento),
    INDEX idx_fecha (fecha_movimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de ejemplo
INSERT INTO bodegas (nombre, ubicacion, direccion, ciudad, estado, codigo_postal, responsable, capacidad_total, estado_bodega) VALUES
('Bodega Central', 'Zona Industrial Norte', 'Av. Industrial #123', 'Ciudad de México', 'CDMX', '01234', 'Juan Pérez', 5000.00, 'Activa'),
('Bodega Sur', 'Zona Industrial Sur', 'Calle Comercio #456', 'Guadalajara', 'Jalisco', '44100', 'María García', 3500.00, 'Activa');

INSERT INTO almacenes (id_bodega, nombre, codigo, ubicacion_especifica, pasillo, seccion, nivel, capacidad, tipo_almacen, estado_almacen) VALUES
(1, 'Almacén A1', 'BC-A1', 'Planta Baja - Zona A', 'P1', 'S1', 'N1', 500.00, 'General', 'Disponible'),
(1, 'Almacén A2', 'BC-A2', 'Planta Baja - Zona A', 'P2', 'S1', 'N1', 500.00, 'General', 'Disponible'),
(1, 'Almacén Refrigerado', 'BC-R1', 'Zona de Refrigeración', 'P1', 'S2', 'N1', 300.00, 'Refrigerado', 'Disponible'),
(2, 'Almacén B1', 'BS-B1', 'Planta Principal', 'P1', 'S1', 'N1', 400.00, 'General', 'Disponible');

INSERT INTO productos_almacen (id_almacen, nombre_producto, codigo_producto, descripcion, categoria, marca, cantidad, unidad, precio_unitario, estado_producto, stock_minimo, stock_maximo) VALUES
(1, 'Computadora Portátil Dell Latitude', 'COMP-001', 'Laptop Dell Latitude 5420, Intel i5, 8GB RAM, 256GB SSD', 'Electrónica', 'Dell', 15, 'piezas', 12000.00, 'Disponible', 5, 50),
(1, 'Monitor LED 24"', 'MON-001', 'Monitor LED 24 pulgadas Full HD', 'Electrónica', 'Samsung', 30, 'piezas', 3500.00, 'Disponible', 10, 100),
(2, 'Teclado Inalámbrico', 'TEC-001', 'Teclado inalámbrico ergonómico', 'Accesorios', 'Logitech', 50, 'piezas', 450.00, 'Disponible', 20, 200),
(2, 'Mouse Óptico', 'MOU-001', 'Mouse óptico inalámbrico', 'Accesorios', 'Logitech', 75, 'piezas', 250.00, 'Disponible', 30, 300);

