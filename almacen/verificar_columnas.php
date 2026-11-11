<?php
/**
 * Script simple para verificar columnas id_empresa
 * Ejecutar directamente para ver qué columnas existen realmente
 */

require_once __DIR__ . '/includes/db.php';
require_once dirname(__DIR__) . '/auth.php';
checkAuth();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verificación de Columnas id_empresa</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .exists { color: green; font-weight: bold; }
        .not-exists { color: red; }
        code { background-color: #f4f4f4; padding: 2px 5px; }
    </style>
</head>
<body>
    <h1>Verificación de Columnas id_empresa</h1>
    
    <?php
    $db_name_result = $conn->query("SELECT DATABASE() as db");
    $db_name = $db_name_result ? $db_name_result->fetch_assoc()['db'] : 'unknown';
    echo "<p><strong>Base de datos:</strong> <code>{$db_name}</code></p>";
    
    $tables = ['bodegas', 'almacenes', 'productos_almacen', 'cartas_entrega', 'historial_movimientos'];
    
    echo "<table>";
    echo "<tr><th>Tabla</th><th>Columna id_empresa Existe</th><th>Todas las Columnas</th></tr>";
    
    foreach ($tables as $table) {
        // Verificar si la tabla existe
        $table_exists = $conn->query("SHOW TABLES LIKE '{$table}'");
        
        if ($table_exists && $table_exists->num_rows > 0) {
            // Obtener todas las columnas
            $cols = $conn->query("SHOW COLUMNS FROM `{$table}`");
            $all_columns = [];
            $has_id_empresa = false;
            
            if ($cols) {
                while ($col = $cols->fetch_assoc()) {
                    $all_columns[] = $col['Field'];
                    if (strtolower(trim($col['Field'])) === 'id_empresa') {
                        $has_id_empresa = true;
                    }
                }
            }
            
            echo "<tr>";
            echo "<td><code>{$table}</code></td>";
            echo "<td class='" . ($has_id_empresa ? 'exists' : 'not-exists') . "'>";
            echo $has_id_empresa ? '✅ SÍ EXISTE' : '❌ NO EXISTE';
            echo "</td>";
            echo "<td><small>" . implode(', ', array_map('htmlspecialchars', $all_columns)) . "</small></td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td><code>{$table}</code></td>";
            echo "<td class='not-exists'>❌ TABLA NO EXISTE</td>";
            echo "<td>-</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    
    // Prueba directa con ALTER TABLE (solo para mostrar el error si existe)
    echo "<h2>Prueba de ALTER TABLE (solo para verificar)</h2>";
    echo "<p>Si ejecutas esto y da error de columna duplicada, significa que la columna SÍ existe:</p>";
    echo "<pre>";
    echo "ALTER TABLE `bodegas` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_bodega`;\n";
    echo "</pre>";
    ?>
    
    <h2>Comando SQL para verificar manualmente:</h2>
    <pre>
-- Ejecuta esto en phpMyAdmin para cada tabla:
SHOW COLUMNS FROM bodegas;
SHOW COLUMNS FROM almacenes;
SHOW COLUMNS FROM productos_almacen;
SHOW COLUMNS FROM cartas_entrega;
SHOW COLUMNS FROM historial_movimientos;

-- O busca específicamente id_empresa:
SHOW COLUMNS FROM bodegas LIKE 'id_empresa';
    </pre>
</body>
</html>

