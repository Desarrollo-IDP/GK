-- Tabla para memoria técnica y archivos asociados a empresa
CREATE TABLE IF NOT EXISTS empresa_memoria_tecnica (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_empresa INT NOT NULL,
    sede VARCHAR(100) DEFAULT 'General',
    tipo VARCHAR(50) NOT NULL COMMENT 'nota, diagrama, config_sede, etc',
    titulo VARCHAR(255) DEFAULT NULL,
    descripcion TEXT DEFAULT NULL,
    archivo_url VARCHAR(512) DEFAULT NULL,
    archivo_nombre VARCHAR(255) DEFAULT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    version INT DEFAULT 1,
    fijado TINYINT(1) DEFAULT 0,
    orden INT DEFAULT 0,
    creado_por INT DEFAULT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_por INT DEFAULT NULL,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    activo TINYINT(1) DEFAULT 1,
    
    FOREIGN KEY (id_empresa) REFERENCES empresas_equipos(id_empresa),
    INDEX idx_empresa_tipo (id_empresa, tipo),
    INDEX idx_sede (id_empresa, sede)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;