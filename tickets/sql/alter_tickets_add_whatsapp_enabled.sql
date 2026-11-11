-- Campo para habilitar conversación/envío WhatsApp por ticket
-- Independiente del Modo de Contacto
-- Ejecutar una vez (si la columna ya existe, ignorar el error de duplicado)

ALTER TABLE `tickets`
    ADD COLUMN `whatsapp_enabled` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = muestra chat y permite envío WhatsApp en view';

-- Activar en tickets que ya tenían canal WhatsApp
UPDATE `tickets` t
LEFT JOIN `contact_modes` cm ON cm.id = t.contact_mode_id
SET t.`whatsapp_enabled` = 1
WHERE t.`whatsapp_enabled` = 0
  AND (
        (t.`last_wa_message_id` IS NOT NULL AND t.`last_wa_message_id` <> '')
        OR LOWER(COALESCE(cm.`name`, '')) LIKE '%whatsapp%'
        OR EXISTS (
            SELECT 1
            FROM `ticket_messages` tm
            WHERE tm.`ticket_id` = t.`id`
              AND tm.`is_whatsapp` = 1
        )
      );
