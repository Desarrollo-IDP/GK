-- Lecturas de notificaciones de mensajes entrantes (por usuario)
CREATE TABLE IF NOT EXISTS ticket_notification_reads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    message_id INT NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_message (user_id, message_id),
    KEY idx_user_read_at (user_id, read_at),
    KEY idx_message_id (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
