-- Script para agregar soporte multi-empresa a la tabla blacklisted_domains
-- Este script agrega el campo id_empresa para que cada empresa tenga su propia lista negra

-- Agregar columna id_empresa a la tabla blacklisted_domains
ALTER TABLE blacklisted_domains 
ADD COLUMN id_empresa INT(11) NULL AFTER domain;

-- Crear índice para mejorar el rendimiento de las consultas filtradas por empresa
ALTER TABLE blacklisted_domains 
ADD INDEX idx_id_empresa (id_empresa);

-- Crear índice único compuesto para evitar duplicados del mismo dominio en la misma empresa
-- Esto permite que el mismo dominio exista en diferentes empresas
ALTER TABLE blacklisted_domains 
ADD UNIQUE INDEX idx_domain_empresa (domain, id_empresa);

-- Nota: Los registros existentes tendrán id_empresa = NULL
-- Estos serán considerados como lista negra global (para todas las empresas)
-- Si se desea asignar a una empresa específica, se debe actualizar manualmente

