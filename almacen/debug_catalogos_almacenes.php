<?php
require_once 'includes/db.php';
require_once '../auth.php';
require_once '../includes/empresa_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa en catalogos
$check_column_catalogos = $conn->query("SHOW COLUMNS FROM catalogos LIKE 'id_empresa'");
$has_empresa_column_catalogos = ($check_column_catalogos && $check_column_catalogos->num_rows > 0) ? true : false;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug: Catálogos Almacenes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1E2A3B;
            border-bottom: 2px solid #1E2A3B;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 30px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-box {
            background: #f9f9f9;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 15px 0;
        }
        .success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        .error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #1E2A3B;
            color: white;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.9em;
        }
        .query-box {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 0.9em;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug: Catálogos Almacenes - Filtrado por Empresa</h1>
        
        <div class="info-box">
            <h3>1. Información del Usuario</h3>
            <table>
                <tr>
                    <th>Campo</th>
                    <th>Valor</th>
                </tr>
                <tr>
                    <td><strong>ID Usuario</strong></td>
                    <td><?php echo $_SESSION['user']['id'] ?? $_SESSION['user']['id_usuario'] ?? 'N/A'; ?></td>
                </tr>
                <tr>
                    <td><strong>Username</strong></td>
                    <td><?php echo $_SESSION['user']['username'] ?? $_SESSION['user']['nombre_usuario'] ?? 'N/A'; ?></td>
                </tr>
                <tr>
                    <td><strong>ID Empresa Usuario</strong></td>
                    <td><?php echo $user_empresa_id ?? 'NULL (Admin)'; ?></td>
                </tr>
                <tr>
                    <td><strong>Es Admin</strong></td>
                    <td><?php echo ($user_empresa_id === null) ? '✅ Sí' : '❌ No'; ?></td>
                </tr>
            </table>
        </div>

        <div class="info-box <?php echo $has_empresa_column_catalogos ? 'success' : 'warning'; ?>">
            <h3>2. Verificación de Columna id_empresa en catalogos</h3>
            <p><strong>Columna existe:</strong> <?php echo $has_empresa_column_catalogos ? '✅ Sí' : '❌ No'; ?></p>
            <?php if ($check_column_catalogos): ?>
                <p><strong>Filas encontradas:</strong> <?php echo $check_column_catalogos->num_rows; ?></p>
            <?php endif; ?>
        </div>

        <h2>3. Catálogos: Tipo de Almacén</h2>
        <?php
        $tipos_query = "SELECT * FROM catalogos WHERE modulo='almacen' AND campo='tipo_almacen' AND activo=1";
        if ($has_empresa_column_catalogos && $user_empresa_id !== null) {
            $tipos_query .= " AND id_empresa = " . intval($user_empresa_id);
        }
        $tipos_query .= " ORDER BY orden, valor";
        
        echo '<div class="query-box">';
        echo '<strong>Query ejecutada:</strong><br>';
        echo htmlspecialchars($tipos_query);
        echo '</div>';
        
        $tipos_result = $conn->query($tipos_query);
        if ($tipos_result) {
            echo '<p><strong>Total registros encontrados:</strong> ' . $tipos_result->num_rows . '</p>';
            if ($tipos_result->num_rows > 0) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Valor</th><th>ID Empresa</th><th>Orden</th><th>Activo</th></tr>';
                while ($row = $tipos_result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['id_catalogo'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($row['valor']) . '</td>';
                    echo '<td>' . ($row['id_empresa'] ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($row['orden'] ?? '0') . '</td>';
                    echo '<td>' . ($row['activo'] ? 'Sí' : 'No') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="info-box warning">⚠️ No se encontraron registros con este filtro.</div>';
                
                // Mostrar todos los registros sin filtro para comparar
                echo '<h3>Comparación: Todos los registros (sin filtro)</h3>';
                $all_tipos_query = "SELECT * FROM catalogos WHERE modulo='almacen' AND campo='tipo_almacen' AND activo=1 ORDER BY orden, valor";
                $all_tipos_result = $conn->query($all_tipos_query);
                if ($all_tipos_result && $all_tipos_result->num_rows > 0) {
                    echo '<p><strong>Total registros sin filtro:</strong> ' . $all_tipos_result->num_rows . '</p>';
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Valor</th><th>ID Empresa</th><th>Orden</th><th>Activo</th></tr>';
                    while ($row = $all_tipos_result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['id_catalogo'] ?? 'N/A') . '</td>';
                        echo '<td>' . htmlspecialchars($row['valor']) . '</td>';
                        echo '<td>' . ($row['id_empresa'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($row['orden'] ?? '0') . '</td>';
                        echo '<td>' . ($row['activo'] ? 'Sí' : 'No') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            }
        } else {
            echo '<div class="info-box error">❌ Error en la consulta: ' . $conn->error . '</div>';
        }
        ?>

        <h2>4. Catálogos: Estado de Almacén</h2>
        <?php
        $estados_query = "SELECT * FROM catalogos WHERE modulo='almacen' AND campo='estado_almacen' AND activo=1";
        if ($has_empresa_column_catalogos && $user_empresa_id !== null) {
            $estados_query .= " AND id_empresa = " . intval($user_empresa_id);
        }
        $estados_query .= " ORDER BY orden, valor";
        
        echo '<div class="query-box">';
        echo '<strong>Query ejecutada:</strong><br>';
        echo htmlspecialchars($estados_query);
        echo '</div>';
        
        $estados_result = $conn->query($estados_query);
        if ($estados_result) {
            echo '<p><strong>Total registros encontrados:</strong> ' . $estados_result->num_rows . '</p>';
            if ($estados_result->num_rows > 0) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Valor</th><th>ID Empresa</th><th>Orden</th><th>Activo</th></tr>';
                while ($row = $estados_result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['id_catalogo'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($row['valor']) . '</td>';
                    echo '<td>' . ($row['id_empresa'] ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($row['orden'] ?? '0') . '</td>';
                    echo '<td>' . ($row['activo'] ? 'Sí' : 'No') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="info-box warning">⚠️ No se encontraron registros con este filtro.</div>';
                
                // Mostrar todos los registros sin filtro para comparar
                echo '<h3>Comparación: Todos los registros (sin filtro)</h3>';
                $all_estados_query = "SELECT * FROM catalogos WHERE modulo='almacen' AND campo='estado_almacen' AND activo=1 ORDER BY orden, valor";
                $all_estados_result = $conn->query($all_estados_query);
                if ($all_estados_result && $all_estados_result->num_rows > 0) {
                    echo '<p><strong>Total registros sin filtro:</strong> ' . $all_estados_result->num_rows . '</p>';
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Valor</th><th>ID Empresa</th><th>Orden</th><th>Activo</th></tr>';
                    while ($row = $all_estados_result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['id_catalogo'] ?? 'N/A') . '</td>';
                        echo '<td>' . htmlspecialchars($row['valor']) . '</td>';
                        echo '<td>' . ($row['id_empresa'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($row['orden'] ?? '0') . '</td>';
                        echo '<td>' . ($row['activo'] ? 'Sí' : 'No') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            }
        } else {
            echo '<div class="info-box error">❌ Error en la consulta: ' . $conn->error . '</div>';
        }
        ?>

        <h2>5. Verificación de Estructura de Tabla catalogos</h2>
        <?php
        $columns_query = "SHOW COLUMNS FROM catalogos";
        $columns_result = $conn->query($columns_query);
        if ($columns_result) {
            echo '<table>';
            echo '<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
            while ($row = $columns_result->fetch_assoc()) {
                echo '<tr>';
                echo '<td><strong>' . htmlspecialchars($row['Field']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Key']) . '</td>';
                echo '<td>' . ($row['Default'] ?? 'NULL') . '</td>';
                echo '<td>' . htmlspecialchars($row['Extra']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>

        <h2>6. Resumen de Catálogos por Empresa</h2>
        <?php
        if ($has_empresa_column_catalogos) {
            $summary_query = "SELECT 
                id_empresa,
                COUNT(*) as total,
                SUM(CASE WHEN campo='tipo_almacen' THEN 1 ELSE 0 END) as tipos,
                SUM(CASE WHEN campo='estado_almacen' THEN 1 ELSE 0 END) as estados
                FROM catalogos 
                WHERE modulo='almacen' AND activo=1
                GROUP BY id_empresa
                ORDER BY id_empresa";
            
            $summary_result = $conn->query($summary_query);
            if ($summary_result && $summary_result->num_rows > 0) {
                echo '<table>';
                echo '<tr><th>ID Empresa</th><th>Total Catálogos</th><th>Tipos Almacén</th><th>Estados Almacén</th></tr>';
                while ($row = $summary_result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . ($row['id_empresa'] ?? 'NULL') . '</td>';
                    echo '<td>' . $row['total'] . '</td>';
                    echo '<td>' . $row['tipos'] . '</td>';
                    echo '<td>' . $row['estados'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
        } else {
            echo '<div class="info-box warning">⚠️ La columna id_empresa no existe en la tabla catalogos.</div>';
        }
        ?>

        <h2>7. Prueba de Consulta con isset()</h2>
        <?php
        echo '<div class="info-box">';
        echo '<p><strong>isset($has_empresa_column_catalogos):</strong> ' . (isset($has_empresa_column_catalogos) ? '✅ Sí' : '❌ No') . '</p>';
        echo '<p><strong>$has_empresa_column_catalogos:</strong> ' . ($has_empresa_column_catalogos ? 'true' : 'false') . '</p>';
        echo '<p><strong>Condición completa:</strong> ';
        if (isset($has_empresa_column_catalogos) && $has_empresa_column_catalogos && $user_empresa_id !== null) {
            echo '✅ Se aplicará el filtro por empresa';
        } else {
            echo '❌ NO se aplicará el filtro por empresa';
            if (!isset($has_empresa_column_catalogos)) {
                echo ' (variable no definida)';
            } elseif (!$has_empresa_column_catalogos) {
                echo ' (columna no existe)';
            } elseif ($user_empresa_id === null) {
                echo ' (usuario es admin)';
            }
        }
        echo '</p>';
        echo '</div>';
        ?>

    </div>
</body>
</html>

