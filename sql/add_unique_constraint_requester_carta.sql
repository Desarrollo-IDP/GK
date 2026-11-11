-- Script para agregar restricción UNIQUE en id_requester para cartas_cumplimiento_legal
-- Este script primero limpia los duplicados, manteniendo solo la carta más reciente (o firmada si existe)

-- IMPORTANTE: Ejecutar este script paso por paso para limpiar duplicados antes de agregar la restricción
-- Si ya limpiaste los duplicados manualmente, puedes ejecutar solo el último comando ALTER TABLE

-- Paso 1: Crear tabla temporal con las cartas que deben mantenerse
CREATE TEMPORARY TABLE IF NOT EXISTS cartas_a_mantener_temp AS
SELECT 
    id_requester,
    CASE 
        -- Si hay una carta firmada, mantener la más reciente de las firmadas
        WHEN MAX(CASE WHEN estado = 'Firmada' THEN 1 ELSE 0 END) > 0 
        THEN MAX(CASE WHEN estado = 'Firmada' THEN id_carta ELSE NULL END)
        -- Si no hay carta firmada, mantener la más reciente por id_carta
        ELSE MAX(id_carta)
    END AS id_carta_a_mantener
FROM cartas_cumplimiento_legal
GROUP BY id_requester;

-- Paso 2: Eliminar las cartas que NO están en la tabla temporal (son duplicados)
DELETE c FROM cartas_cumplimiento_legal c
LEFT JOIN cartas_a_mantener_temp cam ON c.id_requester = cam.id_requester AND c.id_carta = cam.id_carta_a_mantener
WHERE cam.id_carta_a_mantener IS NULL;

-- Paso 3: Eliminar tabla temporal
DROP TEMPORARY TABLE IF EXISTS cartas_a_mantener_temp;

-- Paso 4: Agregar índice único en id_requester
-- NOTA: Si el índice ya existe, este comando mostrará un error que puedes ignorar
-- Para verificar si existe antes: SHOW INDEX FROM cartas_cumplimiento_legal WHERE Key_name = 'idx_unique_id_requester';

ALTER TABLE cartas_cumplimiento_legal 
ADD UNIQUE INDEX idx_unique_id_requester (id_requester);
