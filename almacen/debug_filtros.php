<?php
/**
 * Script de Debug para Verificar Filtros Multi-Empresa
 * 
 * Este script ayuda a diagnosticar problemas con los filtros de empresa
 * Ejecutar desde: almacen/debug_filtros.php
 */

// Desde almacen/debug_filtros.php
// Usar db.php de almacen (como lo hacen los otros archivos del módulo)
require_once __DIR__ . '/includes/db.php';
// auth.php y empresa_helper.php están en la raíz (un nivel arriba)
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/includes/empresa_helper.php';
checkAuth();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Filtros Multi-Empresa</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        code { background-color: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔍 Debug: Filtros Multi-Empresa - Módulo Almacén</h1>

    <?php
    $user_empresa_id = getCurrentUserEmpresaId();
    $is_admin = isMultiEmpresaAdmin();
    $user_info = $_SESSION['user'] ?? [];
    ?>

    <div class="section info">
        <h2>1. Información del Usuario</h2>
        <table>
            <tr><th>Campo</th><th>Valor</th></tr>
            <tr><td>ID Usuario</td><td><?php echo htmlspecialchars($user_info['id'] ?? 'N/A'); ?></td></tr>
            <tr><td>Username</td><td><?php echo htmlspecialchars($user_info['username'] ?? 'N/A'); ?></td></tr>
            <tr><td>Rol</td><td><?php echo htmlspecialchars($user_info['rol'] ?? 'N/A'); ?></td></tr>
            <tr><td>ID Empresa Usuario</td><td><code><?php echo $user_empresa_id !== null ? $user_empresa_id : 'NULL (Admin)'; ?></code></td></tr>
            <tr><td>Es Admin</td><td><?php echo $is_admin ? '✅ Sí' : '❌ No'; ?></td></tr>
            <tr><td>Nombre Empresa</td><td><?php echo htmlspecialchars(getCurrentUserEmpresaName() ?? 'N/A'); ?></td></tr>
        </table>
    </div>

    <?php
    // Verificar columnas usando INFORMATION_SCHEMA (más confiable)
    $tables = ['bodegas', 'almacenes', 'productos_almacen', 'cartas_entrega', 'historial_movimientos'];
    $columns_status = [];
    $tables_exist = [];
    $db_name_result = $conn->query("SELECT DATABASE() as db");
    $db_name = $db_name_result ? $db_name_result->fetch_assoc()['db'] : 'unknown';
    
    // Primero verificar si las tablas existen
    foreach ($tables as $table) {
        $table_check = $conn->query("SHOW TABLES LIKE '{$table}'");
        $tables_exist[$table] = $table_check && $table_check->num_rows > 0;
        
        if ($tables_exist[$table]) {
            // Primero verificar con SHOW COLUMNS (más directo)
            $check = $conn->query("SHOW COLUMNS FROM `{$table}`");
            $has_column = false;
            if ($check) {
                while ($col = $check->fetch_assoc()) {
                    // Verificar si existe id_empresa (case-insensitive)
                    if (strtolower($col['Field']) === 'id_empresa') {
                        $has_column = true;
                        break;
                    }
                }
            }
            
            // También verificar con INFORMATION_SCHEMA
            $check_query = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_SCHEMA = ? 
                           AND TABLE_NAME = ? 
                           AND COLUMN_NAME = 'id_empresa'";
            $stmt = $conn->prepare($check_query);
            $info_schema_result = false;
            if ($stmt) {
                $stmt->bind_param("ss", $db_name, $table);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $info_schema_result = $result['count'] > 0;
                $stmt->close();
            }
            
            // La columna existe si cualquiera de los dos métodos la encuentra
            $columns_status[$table] = $has_column || $info_schema_result;
        } else {
            $columns_status[$table] = false;
        }
    }
    ?>

    <div class="section <?php echo array_reduce($columns_status, function($carry, $item) { return $carry && $item; }, true) ? 'success' : 'error'; ?>">
        <h2>2. Verificación de Columnas id_empresa</h2>
        <p><strong>Base de datos actual:</strong> <code><?php echo htmlspecialchars($db_name); ?></code></p>
        <table>
            <tr><th>Tabla</th><th>Tabla Existe</th><th>Columna Existe (INFORMATION_SCHEMA)</th><th>Verificación Directa (SHOW COLUMNS)</th></tr>
            <?php foreach ($tables as $table): ?>
            <?php
            // Verificación adicional directa - buscar en todas las columnas
            $direct_exists = false;
            $all_columns = [];
            if ($tables_exist[$table]) {
                $cols_check = $conn->query("SHOW COLUMNS FROM `{$table}`");
                if ($cols_check) {
                    while ($col = $cols_check->fetch_assoc()) {
                        $all_columns[] = $col['Field'];
                        if (strtolower($col['Field']) === 'id_empresa') {
                            $direct_exists = true;
                        }
                    }
                }
            }
            ?>
            <tr>
                <td><code><?php echo $table; ?></code></td>
                <td><?php echo $tables_exist[$table] ? '✅ Sí' : '❌ No'; ?></td>
                <td><?php echo $columns_status[$table] ? '✅ Sí' : '❌ No'; ?></td>
                <td>
                    <?php if ($tables_exist[$table]): ?>
                        <?php echo $direct_exists ? '✅ Sí' : '❌ No'; ?>
                        <?php if (!$direct_exists && !empty($all_columns)): ?>
                            <br><small style="color: #666;">Columnas similares: <?php 
                                $similar = array_filter($all_columns, function($col) {
                                    return stripos($col, 'empresa') !== false || stripos($col, 'id_') !== false;
                                });
                                echo !empty($similar) ? implode(', ', array_slice($similar, 0, 5)) : 'ninguna';
                            ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <?php
        // Mostrar todas las columnas de cada tabla para debug
        if (!array_reduce($columns_status, function($carry, $item) { return $carry && $item; }, true)) {
            echo '<div style="margin-top: 15px; padding: 15px; background-color: #fff3cd; border: 2px solid #ffc107; border-radius: 5px;">';
            echo '<h3>🔍 Información de Debug:</h3>';
            
            // Verificar si hay tablas que no existen
            $missing_tables = [];
            foreach ($tables as $table) {
                if (!$tables_exist[$table]) {
                    $missing_tables[] = $table;
                }
            }
            
            if (!empty($missing_tables)) {
                echo '<p style="color: red;"><strong>⚠️ Las siguientes tablas NO existen en la base de datos <code>' . htmlspecialchars($db_name) . '</code>:</strong></p>';
                echo '<ul>';
                foreach ($missing_tables as $table) {
                    echo '<li><code>' . htmlspecialchars($table) . '</code></li>';
                }
                echo '</ul>';
                echo '<p><strong>Posible causa:</strong> El archivo SQL que importaste es de otra base de datos (<code>grupo_gk</code>) pero estás conectado a <code>' . htmlspecialchars($db_name) . '</code></p>';
            }
            
            echo '<h4>Columnas actuales en las tablas existentes:</h4>';
            foreach ($tables as $table) {
                if ($tables_exist[$table]) {
                    echo "<h5>Tabla: <code>{$table}</code></h5>";
                    $cols = $conn->query("SHOW COLUMNS FROM `{$table}`");
                    if ($cols) {
                        echo '<ul>';
                        $has_id_empresa = false;
                        $empresa_columns = [];
                        while ($col = $cols->fetch_assoc()) {
                            $field_lower = strtolower($col['Field']);
                            $highlight = ($field_lower === 'id_empresa') ? ' style="color: green; font-weight: bold;"' : '';
                            if ($field_lower === 'id_empresa') {
                                $has_id_empresa = true;
                            }
                            // Buscar columnas que contengan "empresa"
                            if (stripos($col['Field'], 'empresa') !== false) {
                                $empresa_columns[] = $col['Field'];
                            }
                            echo '<li' . $highlight . '><code>' . htmlspecialchars($col['Field']) . '</code> - ' . htmlspecialchars($col['Type']) . '</li>';
                        }
                        echo '</ul>';
                        if ($has_id_empresa) {
                            echo '<p style="color: green; font-weight: bold;">✅ La columna <code>id_empresa</code> SÍ existe en esta tabla.</p>';
                        } else {
                            echo '<p style="color: red;">❌ La columna <code>id_empresa</code> NO existe en esta tabla.</p>';
                            if (!empty($empresa_columns)) {
                                echo '<p style="color: orange;">⚠️ Pero encontré estas columnas relacionadas: <code>' . implode('</code>, <code>', array_map('htmlspecialchars', $empresa_columns)) . '</code></p>';
                            }
                        }
                    }
                }
            }
            echo '</div>';
        }
        ?>
        <?php if (!array_reduce($columns_status, function($carry, $item) { return $carry && $item; }, true)): ?>
        <div style="margin-top: 15px; padding: 10px; background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 5px;">
            <p><strong>⚠️ Las columnas no se detectaron. Posibles causas:</strong></p>
            <ul>
                <li>El script SQL se ejecutó en una base de datos diferente a <code><?php echo htmlspecialchars($db_name); ?></code></li>
                <li>El script SQL tuvo errores al ejecutarse</li>
                <li>Las tablas no existen en esta base de datos</li>
            </ul>
            <p><strong>Para verificar manualmente:</strong></p>
            <ol>
                <li>Ejecuta en phpMyAdmin: <code>SHOW COLUMNS FROM bodegas LIKE 'id_empresa';</code></li>
                <li>Verifica que estás en la base de datos correcta: <code><?php echo htmlspecialchars($db_name); ?></code></li>
                <li>Si las columnas no existen, ejecuta el script SQL manualmente: <code>sql/add_id_empresa_almacen_simple.sql</code></li>
            </ol>
        </div>
        <?php endif; ?>
    </div>

    <?php
    // Contar registros por empresa
    ?>
    <div class="section info">
        <h2>3. Conteo de Registros por Empresa</h2>
        <table>
            <tr><th>Tabla</th><th>Total Registros</th><th>Con id_empresa NULL</th><th>Con id_empresa asignado</th></tr>
            <?php foreach ($tables as $table): ?>
            <?php
            $total = $conn->query("SELECT COUNT(*) as total FROM {$table}")->fetch_assoc()['total'];
            if ($columns_status[$table]) {
                $null_count = $conn->query("SELECT COUNT(*) as total FROM {$table} WHERE id_empresa IS NULL")->fetch_assoc()['total'];
                $assigned_count = $conn->query("SELECT COUNT(*) as total FROM {$table} WHERE id_empresa IS NOT NULL")->fetch_assoc()['total'];
            } else {
                $null_count = 'N/A';
                $assigned_count = 'N/A';
            }
            ?>
            <tr>
                <td><code><?php echo $table; ?></code></td>
                <td><?php echo $total; ?></td>
                <td><?php echo $null_count; ?></td>
                <td><?php echo $assigned_count; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <?php
    // Probar consultas con filtros
    ?>
    <div class="section info">
        <h2>4. Prueba de Consultas con Filtros</h2>
        
        <?php if ($has_empresa_column_bodegas = $columns_status['bodegas']): ?>
        <h3>Bodegas</h3>
        <?php
        $test_query = "SELECT COUNT(*) as total FROM bodegas";
        if ($user_empresa_id !== null) {
            $test_query .= " WHERE id_empresa = " . intval($user_empresa_id);
        }
        $test_result = $conn->query($test_query);
        $test_count = $test_result ? $test_result->fetch_assoc()['total'] : 0;
        ?>
        <p><strong>Consulta:</strong> <code><?php echo htmlspecialchars($test_query); ?></code></p>
        <p><strong>Resultado:</strong> <?php echo $test_count; ?> bodegas</p>
        <?php endif; ?>

        <?php if ($has_empresa_column_almacenes = $columns_status['almacenes']): ?>
        <h3>Almacenes</h3>
        <?php
        $test_query = "SELECT COUNT(*) as total FROM almacenes";
        if ($user_empresa_id !== null) {
            $test_query .= " WHERE id_empresa = " . intval($user_empresa_id);
        }
        $test_result = $conn->query($test_query);
        $test_count = $test_result ? $test_result->fetch_assoc()['total'] : 0;
        ?>
        <p><strong>Consulta:</strong> <code><?php echo htmlspecialchars($test_query); ?></code></p>
        <p><strong>Resultado:</strong> <?php echo $test_count; ?> almacenes</p>
        <?php endif; ?>

        <?php if ($has_empresa_column_productos = $columns_status['productos_almacen']): ?>
        <h3>Productos</h3>
        <?php
        $test_query = "SELECT COUNT(*) as total FROM productos_almacen";
        if ($user_empresa_id !== null) {
            $test_query .= " WHERE id_empresa = " . intval($user_empresa_id);
        }
        $test_result = $conn->query($test_query);
        $test_count = $test_result ? $test_result->fetch_assoc()['total'] : 0;
        ?>
        <p><strong>Consulta:</strong> <code><?php echo htmlspecialchars($test_query); ?></code></p>
        <p><strong>Resultado:</strong> <?php echo $test_count; ?> productos</p>
        <?php endif; ?>
    </div>

    <div class="section warning">
        <h2>5. Recomendaciones</h2>
        <ul>
            <?php if (!$columns_status['bodegas'] || !$columns_status['almacenes'] || !$columns_status['productos_almacen']): ?>
            <li>⚠️ <strong>Ejecutar script SQL:</strong> Algunas columnas no existen. Ejecuta <code>sql/add_id_empresa_almacen.sql</code></li>
            <?php endif; ?>
            
            <?php if ($user_empresa_id === null && !$is_admin): ?>
            <li>⚠️ <strong>Usuario sin empresa:</strong> El usuario no tiene empresa asignada. Asigna una empresa en la tabla <code>usuarios</code></li>
            <?php endif; ?>
            
            <?php
            if ($columns_status['bodegas']) {
                try {
                    $null_bodegas = $conn->query("SELECT COUNT(*) as total FROM bodegas WHERE id_empresa IS NULL")->fetch_assoc()['total'];
                    if ($null_bodegas > 0):
            ?>
            <li>⚠️ <strong>Datos sin empresa:</strong> Hay <?php echo $null_bodegas; ?> bodegas sin <code>id_empresa</code> asignado. Estos registros no se mostrarán para usuarios con empresa asignada.</li>
            <?php 
                    endif;
                } catch (Exception $e) {
                    // Ignorar error si la columna no existe
                }
            }
            ?>
        </ul>
    </div>

    <div class="section info">
        <h2>6. Verificar Datos de Usuario en Base de Datos</h2>
        <?php
        $user_id = $user_info['id'] ?? null;
        if ($user_id) {
            // Intentar diferentes nombres de columnas según la estructura de la tabla
            // Primero verificar qué columnas existen en la tabla usuarios
            $user_columns_check = $conn->query("SHOW COLUMNS FROM usuarios");
            $user_columns = [];
            if ($user_columns_check) {
                while ($col = $user_columns_check->fetch_assoc()) {
                    $user_columns[] = $col['Field'];
                }
            }
            
            // Construir query según las columnas disponibles
            $id_col = in_array('id', $user_columns) ? 'id' : (in_array('id_usuario', $user_columns) ? 'id_usuario' : null);
            $username_col = in_array('username', $user_columns) ? 'username' : (in_array('nombre_usuario', $user_columns) ? 'nombre_usuario' : null);
            $rol_col = in_array('rol', $user_columns) ? 'rol' : null;
            $empresa_col = in_array('id_empresa', $user_columns) ? 'id_empresa' : null;
            
            if ($id_col && $username_col && $rol_col) {
                $select_cols = [];
                $select_cols[] = "{$id_col} as id";
                $select_cols[] = "{$username_col} as username";
                $select_cols[] = "{$rol_col} as rol";
                if ($empresa_col) {
                    $select_cols[] = "{$empresa_col} as id_empresa";
                }
                
                $user_query = "SELECT " . implode(", ", $select_cols) . " FROM usuarios WHERE {$id_col} = " . intval($user_id);
                $user_db = $conn->query($user_query);
                if ($user_db && $user_db->num_rows > 0) {
                    $user_data = $user_db->fetch_assoc();
                } else {
                    $user_data = null;
                }
            } else {
                // Si no se pudo construir la query, intentar queries simples
                $user_queries = [
                    "SELECT id_usuario as id, nombre_usuario as username, rol FROM usuarios WHERE id_usuario = " . intval($user_id),
                    "SELECT id, username, rol FROM usuarios WHERE id = " . intval($user_id)
                ];
                
                $user_data = null;
                foreach ($user_queries as $query) {
                    $user_db = $conn->query($query);
                    if ($user_db && $user_db->num_rows > 0) {
                        $user_data = $user_db->fetch_assoc();
                        break;
                    }
                }
            }
            
            if ($user_data) {
                ?>
                <table>
                    <tr><th>Campo</th><th>Valor en BD</th></tr>
                    <tr><td>ID</td><td><?php echo $user_data['id']; ?></td></tr>
                    <tr><td>Username</td><td><?php echo htmlspecialchars($user_data['username'] ?? 'N/A'); ?></td></tr>
                    <tr><td>Rol</td><td><?php echo htmlspecialchars($user_data['rol'] ?? 'N/A'); ?></td></tr>
                    <tr><td>id_empresa</td><td><code><?php echo isset($user_data['id_empresa']) && $user_data['id_empresa'] !== null ? $user_data['id_empresa'] : 'NULL'; ?></code></td></tr>
                </table>
                <?php
            } else {
                echo "<p>No se pudo obtener información del usuario desde la base de datos.</p>";
            }
        }
        ?>
    </div>

    <div class="section error">
        <h2>7. Instrucciones para Crear Columnas Manualmente</h2>
        <p>Si el script SQL no funcionó, ejecuta estos comandos uno por uno en phpMyAdmin:</p>
        <ol>
            <li><strong>Bodegas:</strong><br>
                <code>ALTER TABLE `bodegas` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_bodega`;</code><br>
                <code>ALTER TABLE `bodegas` ADD INDEX `idx_bodegas_empresa` (`id_empresa`);</code>
            </li>
            <li><strong>Almacenes:</strong><br>
                <code>ALTER TABLE `almacenes` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_almacen`;</code><br>
                <code>ALTER TABLE `almacenes` ADD INDEX `idx_almacenes_empresa` (`id_empresa`);</code>
            </li>
            <li><strong>Productos:</strong><br>
                <code>ALTER TABLE `productos_almacen` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_producto`;</code><br>
                <code>ALTER TABLE `productos_almacen` ADD INDEX `idx_productos_almacen_empresa` (`id_empresa`);</code>
            </li>
            <li><strong>Cartas de Entrega:</strong><br>
                <code>ALTER TABLE `cartas_entrega` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_carta`;</code><br>
                <code>ALTER TABLE `cartas_entrega` ADD INDEX `idx_cartas_entrega_empresa` (`id_empresa`);</code>
            </li>
            <li><strong>Historial de Movimientos:</strong><br>
                <code>ALTER TABLE `historial_movimientos` ADD COLUMN `id_empresa` INT(11) NULL DEFAULT NULL AFTER `id_movimiento`;</code><br>
                <code>ALTER TABLE `historial_movimientos` ADD INDEX `idx_historial_movimientos_empresa` (`id_empresa`);</code>
            </li>
        </ol>
        <p><strong>O usa el script simple:</strong> <code>sql/add_id_empresa_almacen_simple.sql</code></p>
    </div>

</body>
</html>

