-- Migración OPCIONAL y aditiva (no destructiva).
-- No borra ni modifica datos existentes.
-- El código detecta la columna con SHOW COLUMNS y funciona con o sin ella.
--
-- Qué hace:
-- 1) Agrega columna origen para separar contactos de Tickets vs Inventario
-- 2) Etiqueta contactos legacy solo cuando el id existe en UNA sola tabla padre
--
-- Ejecutar en producción solo cuando se pueda (ventana de mantenimiento ligera).

ALTER TABLE `contactos_empresa`
  ADD COLUMN `origen` VARCHAR(32) NULL DEFAULT NULL AFTER `correo`;

-- Etiquetar legado de forma segura (ids que no chocan entre módulos)
UPDATE contactos_empresa c
INNER JOIN empresas e ON e.id_empresa = c.id_empresa
LEFT JOIN empresas_equipos ee ON ee.id_empresa = c.id_empresa
SET c.origen = 'empresas'
WHERE c.origen IS NULL
  AND ee.id_empresa IS NULL;

UPDATE contactos_empresa c
INNER JOIN empresas_equipos ee ON ee.id_empresa = c.id_empresa
LEFT JOIN empresas e ON e.id_empresa = c.id_empresa
SET c.origen = 'empresas_equipos'
WHERE c.origen IS NULL
  AND e.id_empresa IS NULL;

-- Los ids compartidos entre ambas tablas quedan con origen NULL
-- y el código los trata como fallback compartido (sin borrar al eliminar en un módulo).
