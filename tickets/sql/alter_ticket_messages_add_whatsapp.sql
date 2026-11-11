-- Integración WhatsApp ↔ tickets
-- Ejecutar una vez en la base grupo_gk

ALTER TABLE `ticket_messages`
    ADD COLUMN `is_whatsapp` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_email`,
    ADD COLUMN `whatsapp_type` VARCHAR(32) DEFAULT NULL AFTER `is_whatsapp`;

-- Opcional: modo de contacto WhatsApp (ajustar id_empresa si aplica)
INSERT INTO `contact_modes` (`name`)
SELECT 'WhatsApp'
WHERE NOT EXISTS (SELECT 1 FROM `contact_modes` WHERE LOWER(`name`) LIKE '%whatsapp%');
