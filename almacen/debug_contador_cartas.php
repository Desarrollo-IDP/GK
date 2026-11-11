<?php
/**
 * Script de depuración para contadores de cartas de entrega por empresa
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/empresa_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug: Contadores de Cartas de Entrega</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1E2A3B; border-bottom: 3px solid #1E2A3B; padding-bottom: 10px; }
        h2 { color: #2C3E50; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #1E2A3B; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .info { background-color: #e8f4f8; padding: 15px; border-left: 4px solid #3498db; margin: 15px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug: Contadores de Cartas de Entrega por Empresa</h1>
        
        <div class="info">
            <h2>1. Información del Usuario</h2>
            <table>
                <tr>
                    <th>Campo</th>
                    <th>Valor</th>
                </tr>
                <tr>
                    <td>ID Usuario</td>
                    <td><?php echo $_SESSION['user']['id'] ?? 'N/A'; ?></td>
                </tr>
                <tr>
                    <td>Username</td>
                    <td><?php echo $_SESSION['user']['username'] ?? 'N/A'; ?></td>
                </tr>
                <tr>
                    <td>ID Empresa Usuario</td>
                    <td><strong><?php echo $user_empresa_id ?? 'NULL'; ?></strong></td>
                </tr>
            </table>
        </div>

        <h2>2. Verificación de Tabla de Contadores</h2>
        <?php
        $check_table = $conn->query("SHOW TABLES LIKE 'carta_entrega_counters'");
        $table_exists = $check_table && $check_table->num_rows > 0;
        ?>
        <table>
            <tr>
                <th>Verificación</th>
                <th>Resultado</th>
            </tr>
            <tr>
                <td>Tabla <code>carta_entrega_counters</code> existe</td>
                <td>
                    <?php if ($table_exists): ?>
                        <span class="success">✅ SÍ</span>
                    <?php else: ?>
                        <span class="error">❌ NO</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php if ($table_exists): ?>
            <h2>3. Estructura de la Tabla</h2>
            <?php
            $structure = $conn->query("DESCRIBE carta_entrega_counters");
            ?>
            <table>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Null</th>
                    <th>Key</th>
                    <th>Default</th>
                    <th>Extra</th>
                </tr>
                <?php while ($row = $structure->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['Field']); ?></td>
                    <td><?php echo htmlspecialchars($row['Type']); ?></td>
                    <td><?php echo htmlspecialchars($row['Null']); ?></td>
                    <td><?php echo htmlspecialchars($row['Key']); ?></td>
                    <td><?php echo htmlspecialchars($row['Default'] ?? 'NULL'); ?></td>
                    <td><?php echo htmlspecialchars($row['Extra']); ?></td>
                </tr>
                <?php endwhile; ?>
            </table>

            <h2>4. Contadores Existentes</h2>
            <?php
            $counters = $conn->query("SELECT * FROM carta_entrega_counters ORDER BY id_empresa");
            ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>ID Empresa</th>
                    <th>Contador Actual</th>
                    <th>Última Actualización</th>
                </tr>
                <?php if ($counters && $counters->num_rows > 0): ?>
                    <?php while ($row = $counters->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo $row['id_empresa']; ?></td>
                        <td><strong><?php echo $row['current_counter']; ?></strong></td>
                        <td><?php echo $row['last_updated']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center;">No hay contadores registrados</td>
                    </tr>
                <?php endif; ?>
            </table>

            <h2>5. Contador para Empresa del Usuario</h2>
            <?php
            $empresa_id_check = $user_empresa_id ?? 0;
            $counter_check = $conn->prepare("SELECT * FROM carta_entrega_counters WHERE id_empresa = ?");
            $counter_check->bind_param("i", $empresa_id_check);
            $counter_check->execute();
            $result_check = $counter_check->get_result();
            ?>
            <table>
                <tr>
                    <th>ID Empresa</th>
                    <th>Contador Existe</th>
                    <th>Valor Actual</th>
                    <th>Próximo Número</th>
                </tr>
                <tr>
                    <td><?php echo $empresa_id_check; ?></td>
                    <td>
                        <?php if ($result_check->num_rows > 0): ?>
                            <span class="success">✅ SÍ</span>
                        <?php else: ?>
                            <span class="warning">⚠️ NO (se creará automáticamente)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        if ($result_check->num_rows > 0) {
                            $row = $result_check->fetch_assoc();
                            echo $row['current_counter'];
                        } else {
                            echo '0 (no existe)';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($result_check->num_rows > 0) {
                            $row = $result_check->fetch_assoc();
                            $next = $row['current_counter'] + 1;
                            echo 'CA-' . str_pad($next, 4, '0', STR_PAD_LEFT);
                        } else {
                            echo 'CA-0001';
                        }
                        ?>
                    </td>
                </tr>
            </table>
        <?php else: ?>
            <div class="info">
                <p><strong>⚠️ La tabla no existe.</strong> Ejecuta el script SQL: <code>sql/create_carta_entrega_counters.sql</code></p>
            </div>
        <?php endif; ?>

        <h2>6. Cartas de Entrega Existentes por Empresa</h2>
        <?php
        $check_column = $conn->query("SHOW COLUMNS FROM cartas_entrega LIKE 'id_empresa'");
        $has_empresa_column = $check_column && $check_column->num_rows > 0;
        
        if ($has_empresa_column) {
            $cartas_query = "SELECT id_empresa, COUNT(*) as total, MAX(numero_carta) as ultimo_numero 
                            FROM cartas_entrega 
                            GROUP BY id_empresa 
                            ORDER BY id_empresa";
        } else {
            $cartas_query = "SELECT NULL as id_empresa, COUNT(*) as total, MAX(numero_carta) as ultimo_numero 
                            FROM cartas_entrega";
        }
        $cartas = $conn->query($cartas_query);
        ?>
        <table>
            <tr>
                <th>ID Empresa</th>
                <th>Total Cartas</th>
                <th>Último Número</th>
            </tr>
            <?php if ($cartas && $cartas->num_rows > 0): ?>
                <?php while ($row = $cartas->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id_empresa'] ?? 'NULL'; ?></td>
                    <td><?php echo $row['total']; ?></td>
                    <td><?php echo $row['ultimo_numero'] ?? 'N/A'; ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align: center;">No hay cartas registradas</td>
                </tr>
            <?php endif; ?>
        </table>

        <h2>7. Prueba de Generación de Número</h2>
        <?php
        // Simular la función generarNumeroCarta
        function testGenerarNumeroCarta($conn, $id_empresa_usuario) {
            if ($id_empresa_usuario === null) {
                $id_empresa_usuario = 0;
            }
            
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT current_counter FROM carta_entrega_counters WHERE id_empresa = ? FOR UPDATE");
                if (!$stmt) {
                    return ['error' => 'Error al preparar consulta: ' . $conn->error];
                }
                
                $stmt->bind_param("i", $id_empresa_usuario);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $next_counter = $row['current_counter'] + 1;
                    
                    $update_stmt = $conn->prepare("UPDATE carta_entrega_counters SET current_counter = ? WHERE id_empresa = ?");
                    $update_stmt->bind_param("ii", $next_counter, $id_empresa_usuario);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    $next_counter = 1;
                    $insert_stmt = $conn->prepare("INSERT INTO carta_entrega_counters (id_empresa, current_counter) VALUES (?, ?)");
                    $insert_stmt->bind_param("ii", $id_empresa_usuario, $next_counter);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
                
                $stmt->close();
                $conn->commit();
                
                return ['success' => true, 'counter' => $next_counter, 'numero' => 'CA-' . str_pad($next_counter, 4, '0', STR_PAD_LEFT)];
            } catch (Exception $e) {
                $conn->rollback();
                return ['error' => $e->getMessage()];
            }
        }
        
        $test_result = testGenerarNumeroCarta($conn, $user_empresa_id);
        ?>
        <table>
            <tr>
                <th>Resultado</th>
                <th>Valor</th>
            </tr>
            <?php if (isset($test_result['success'])): ?>
                <tr>
                    <td>Estado</td>
                    <td><span class="success">✅ Éxito</span></td>
                </tr>
                <tr>
                    <td>Contador</td>
                    <td><?php echo $test_result['counter']; ?></td>
                </tr>
                <tr>
                    <td>Número Generado</td>
                    <td><strong><?php echo $test_result['numero']; ?></strong></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td>Error</td>
                    <td><span class="error">❌ <?php echo htmlspecialchars($test_result['error']); ?></span></td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>
