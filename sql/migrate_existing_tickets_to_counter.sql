-- Script para migrar tickets existentes al sistema de contadores por empresa
-- Este script asigna números de ticket a los tickets existentes basándose en su empresa

-- Paso 1: Inicializar contadores para todas las empresas existentes
INSERT INTO `ticket_counters` (`id_empresa`, `current_counter`)
SELECT DISTINCT COALESCE(`id_empresa`, 0), 0
FROM `tickets`
WHERE `id_empresa` IS NOT NULL OR `id_empresa` IS NULL
ON DUPLICATE KEY UPDATE `current_counter` = `current_counter`;

-- Paso 2: Asignar números de ticket a tickets existentes que no tienen ticket_number
-- Los números se asignan secuencialmente por empresa basándose en la fecha de creación

-- Para cada empresa, asignar números secuenciales
SET @row_number = 0;
SET @current_empresa = NULL;

UPDATE `tickets` t
SET `ticket_number` = (
    SELECT ticket_num
    FROM (
        SELECT 
            id,
            id_empresa,
            @row_number := CASE 
                WHEN @current_empresa = COALESCE(id_empresa, 0) THEN @row_number + 1
                ELSE 1
            END AS ticket_num,
            @current_empresa := COALESCE(id_empresa, 0) AS empresa_set
        FROM `tickets`
        WHERE `ticket_number` IS NULL
        ORDER BY COALESCE(id_empresa, 0), created_at ASC
    ) AS numbered
    WHERE numbered.id = t.id
)
WHERE `ticket_number` IS NULL;

-- Paso 3: Actualizar los contadores al máximo número asignado por empresa
UPDATE `ticket_counters` tc
SET `current_counter` = (
    SELECT COALESCE(MAX(CAST(`ticket_number` AS UNSIGNED)), 0)
    FROM `tickets` t
    WHERE COALESCE(t.`id_empresa`, 0) = tc.`id_empresa`
    AND t.`ticket_number` IS NOT NULL
    AND t.`ticket_number` REGEXP '^[0-9]+$'
)
WHERE EXISTS (
    SELECT 1 FROM `tickets` t2 
    WHERE COALESCE(t2.`id_empresa`, 0) = tc.`id_empresa` 
    AND t2.`ticket_number` IS NOT NULL
);

-- Paso 4: Verificar que todos los tickets tienen ticket_number
-- Si algún ticket aún no tiene ticket_number, asignarle uno basado en su ID
UPDATE `tickets`
SET `ticket_number` = CAST(`id` AS CHAR)
WHERE `ticket_number` IS NULL;

