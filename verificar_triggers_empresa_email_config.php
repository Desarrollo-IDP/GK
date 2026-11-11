<?php
/**
 * Script para verificar si hay triggers en empresa_email_config que puedan estar modificando los valores
 */

include 'tickets/conexion/db.php';

if (!$conn) {
    die("Error de conexión a la base de datos");
}

echo "<h2>Verificación de Triggers - empresa_email_config</h2>";

// Verificar si hay triggers en la tabla
$query = "SHOW TRIGGERS WHERE `Table` = 'empresa_email_config'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<p><strong>⚠️ Se encontraron triggers en la tabla empresa_email_config:</strong></p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Trigger</th><th>Event</th><th>Timing</th><th>Statement</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Trigger']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Event']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Timing']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Statement']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>✅ No se encontraron triggers en la tabla empresa_email_config.</p>";
}

// Verificar constraints
echo "<h3>Verificación de Constraints</h3>";
$constraint_query = "SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'empresa_email_config'";
$constraint_result = $conn->query($constraint_query);

if ($constraint_result && $constraint_result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Constraint Name</th><th>Type</th></tr>";
    
    while ($row = $constraint_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['CONSTRAINT_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($row['CONSTRAINT_TYPE']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No se encontraron constraints adicionales.</p>";
}

// Probar un UPDATE directo para ver si el problema está en bind_param
echo "<h3>Prueba de UPDATE Directo</h3>";
echo "<p>Ejecutando UPDATE directo con valores hardcodeados para verificar si el problema está en bind_param:</p>";

$test_update = "UPDATE empresa_email_config 
                SET smtp_from_email = 'test@example.com', 
                    imap_username = 'test@example.com', 
                    imap_encryption = '/imap/ssl'
                WHERE id_empresa = 2 
                LIMIT 1";

if ($conn->query($test_update)) {
    echo "<p>✅ UPDATE directo ejecutado correctamente.</p>";
    
    // Verificar qué se guardó
    $verify_query = "SELECT smtp_from_email, imap_username, imap_encryption 
                    FROM empresa_email_config 
                    WHERE id_empresa = 2";
    $verify_result = $conn->query($verify_query);
    
    if ($verify_row = $verify_result->fetch_assoc()) {
        echo "<p><strong>Valores guardados después del UPDATE directo:</strong></p>";
        echo "<ul>";
        echo "<li>smtp_from_email: '" . htmlspecialchars($verify_row['smtp_from_email']) . "'</li>";
        echo "<li>imap_username: '" . htmlspecialchars($verify_row['imap_username']) . "'</li>";
        echo "<li>imap_encryption: '" . htmlspecialchars($verify_row['imap_encryption']) . "'</li>";
        echo "</ul>";
    }
} else {
    echo "<p>❌ Error en UPDATE directo: " . $conn->error . "</p>";
}

$conn->close();
?>

