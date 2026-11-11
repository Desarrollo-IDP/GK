<?php
/**
 * Script para verificar los tipos de datos de las columnas en empresa_email_config
 */

include 'tickets/conexion/db.php';

if (!$conn) {
    die("Error de conexión a la base de datos");
}

echo "<h2>Verificación de Tipos de Datos - empresa_email_config</h2>";

// Obtener información de las columnas
$query = "SHOW COLUMNS FROM empresa_email_config";
$result = $conn->query($query);

if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Verificar específicamente los campos problemáticos
    echo "<h3>Verificación de Campos Problemáticos</h3>";
    $problem_fields = ['smtp_from_email', 'imap_username', 'imap_encryption'];
    
    foreach ($problem_fields as $field) {
        $check_query = "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
                       FROM INFORMATION_SCHEMA.COLUMNS 
                       WHERE TABLE_SCHEMA = DATABASE() 
                       AND TABLE_NAME = 'empresa_email_config' 
                       AND COLUMN_NAME = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $field);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_row = $check_result->fetch_assoc()) {
            echo "<p><strong>$field:</strong> ";
            echo "Tipo: " . htmlspecialchars($check_row['COLUMN_TYPE']) . ", ";
            echo "Nullable: " . htmlspecialchars($check_row['IS_NULLABLE']) . ", ";
            echo "Default: " . htmlspecialchars($check_row['COLUMN_DEFAULT'] ?? 'NULL');
            echo "</p>";
        }
        $check_stmt->close();
    }
    
    // Verificar datos existentes
    echo "<h3>Datos Existentes (últimos 3 registros)</h3>";
    $data_query = "SELECT id_empresa, smtp_from_email, imap_username, imap_encryption 
                   FROM empresa_email_config 
                   ORDER BY id DESC 
                   LIMIT 3";
    $data_result = $conn->query($data_query);
    
    if ($data_result && $data_result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID Empresa</th><th>smtp_from_email</th><th>imap_username</th><th>imap_encryption</th></tr>";
        
        while ($data_row = $data_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($data_row['id_empresa']) . "</td>";
            echo "<td>" . htmlspecialchars($data_row['smtp_from_email']) . " (tipo: " . gettype($data_row['smtp_from_email']) . ")</td>";
            echo "<td>" . htmlspecialchars($data_row['imap_username']) . " (tipo: " . gettype($data_row['imap_username']) . ")</td>";
            echo "<td>" . htmlspecialchars($data_row['imap_encryption']) . " (tipo: " . gettype($data_row['imap_encryption']) . ")</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No hay datos en la tabla.</p>";
    }
    
} else {
    echo "Error al obtener información de columnas: " . $conn->error;
}

$conn->close();
?>

