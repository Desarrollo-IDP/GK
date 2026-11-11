<?php
// Script para crear la tabla ticket_cost_attachments si no existe
include '../../conexion/db.php';

// Verificar si la tabla ya existe
$table_check = $conn->query("SHOW TABLES LIKE 'ticket_cost_attachments'");

if ($table_check->num_rows == 0) {
    // La tabla no existe, crearla
    $create_table = "CREATE TABLE `ticket_cost_attachments` (
      `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `cost_id` bigint(20) UNSIGNED NOT NULL,
      `file_name` varchar(255) NOT NULL,
      `file_content` longblob NOT NULL,
      `file_type` varchar(100) DEFAULT NULL,
      `file_size` int(11) DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `cost_id` (`cost_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($create_table)) {
        echo "✅ Tabla 'ticket_cost_attachments' creada exitosamente.<br>";
        echo "El sistema ahora puede manejar múltiples archivos adjuntos por costo.";
    } else {
        echo "❌ Error al crear la tabla: " . $conn->error;
    }
} else {
    echo "✅ La tabla 'ticket_cost_attachments' ya existe.<br>";
    echo "El sistema está listo para manejar múltiples archivos adjuntos por costo.";
}

$conn->close();
?>

