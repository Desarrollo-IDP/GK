<?php
// Script para crear la tabla de tracking de status si no existe
include 'conexion/db.php';

// Verificar si la tabla existe
$check_table = "SHOW TABLES LIKE 'ticket_status_tracking'";
$result = $conn->query($check_table);

if ($result->num_rows == 0) {
    // Crear la tabla
    $create_table = "
    CREATE TABLE `ticket_status_tracking` (
      `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `ticket_id` int(11) NOT NULL,
      `status_id` int(11) NOT NULL,
      `started_at` timestamp NULL DEFAULT current_timestamp(),
      `ended_at` timestamp NULL DEFAULT NULL,
      `duration_minutes` int(11) DEFAULT NULL,
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` timestamp NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `ticket_id` (`ticket_id`),
      KEY `status_id` (`status_id`),
      KEY `is_active` (`is_active`),
      KEY `started_at` (`started_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    if ($conn->query($create_table)) {
        echo "✅ Tabla 'ticket_status_tracking' creada exitosamente.<br>";
    } else {
        echo "❌ Error al crear la tabla: " . $conn->error . "<br>";
    }
} else {
    echo "✅ La tabla 'ticket_status_tracking' ya existe.<br>";
}

// Verificar la estructura de la tabla
$describe = "DESCRIBE ticket_status_tracking";
$result = $conn->query($describe);

if ($result) {
    echo "<br><strong>Estructura de la tabla ticket_status_tracking:</strong><br>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Por defecto</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Error al obtener la estructura de la tabla: " . $conn->error . "<br>";
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Configuración de Tracking de Status</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>Configuración de Tracking de Status de Tickets</h2>
    <p>Este script verifica y crea la tabla necesaria para el seguimiento de tiempo en status específicos.</p>
    
    <h3>Funcionalidad:</h3>
    <ul>
        <li><strong>Tracking automático:</strong> Cuando un ticket se pone en status_id = 10, se inicia el tracking</li>
        <li><strong>Duración calculada:</strong> Cuando se cambia el status, se calcula automáticamente el tiempo transcurrido</li>
        <li><strong>Historial completo:</strong> Se mantiene un registro de todos los períodos en status específicos</li>
        <li><strong>Múltiples períodos:</strong> Si un ticket vuelve al status 10, se crea un nuevo registro</li>
    </ul>
    
    <h3>Instrucciones:</h3>
    <ol>
        <li>Ejecuta este script una vez para configurar la tabla</li>
        <li>Una vez configurado, puedes eliminar este archivo por seguridad</li>
        <li>El tracking se activará automáticamente al editar tickets</li>
    </ol>
    
    <h3>Resultado:</h3>
    <?php
    // El código PHP ya se ejecutó arriba
    ?>
    
    <p><strong>Nota:</strong> Una vez que veas el mensaje de éxito, puedes eliminar este archivo por seguridad.</p>
</body>
</html>
