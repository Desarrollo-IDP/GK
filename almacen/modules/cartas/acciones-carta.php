<?php
require_once '../../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
checkAuth();

header('Content-Type: application/json');

// Obtener empresa del usuario
$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa
$has_empresa_column = false;
$check_column = $conn->query("SHOW COLUMNS FROM cartas_entrega LIKE 'id_empresa'");
if ($check_column && $check_column->num_rows > 0) {
    $has_empresa_column = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'cambiar_estado') {
        $numero_carta = limpiarDatos($_POST['numero_carta']);
        $nuevo_estado = limpiarDatos($_POST['nuevo_estado']);
        
        // Obtener el estado actual y los productos de la carta
        $get_carta_query = "SELECT estado_entrega, id_producto, cantidad_entregada FROM cartas_entrega WHERE numero_carta = ?";
        if ($has_empresa_column && $user_empresa_id !== null) {
            $get_carta_query .= " AND id_empresa = " . intval($user_empresa_id);
        }
        $get_carta_stmt = $conn->prepare($get_carta_query);
        $get_carta_stmt->bind_param('s', $numero_carta);
        $get_carta_stmt->execute();
        $carta_result = $get_carta_stmt->get_result();
        
        if ($carta_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No se encontró la carta o no tiene permiso para modificarla']);
            exit;
        }
        
        $productos_carta = [];
        $estado_actual = null;
        while ($row = $carta_result->fetch_assoc()) {
            if ($estado_actual === null) {
                $estado_actual = $row['estado_entrega'];
            }
            $productos_carta[] = [
                'id_producto' => $row['id_producto'],
                'cantidad_entregada' => $row['cantidad_entregada']
            ];
        }
        $get_carta_stmt->close();
        
        // Si se está cancelando y no estaba cancelada antes, devolver productos al inventario
        if ($nuevo_estado === 'Cancelado' && $estado_actual !== 'Cancelado') {
            $user = $_SESSION['user']['username'] ?? 'Sistema';
            
            // Verificar si existe columna id_empresa en historial_movimientos
            $check_historial_column = $conn->query("SHOW COLUMNS FROM historial_movimientos LIKE 'id_empresa'");
            $has_historial_column = $check_historial_column && $check_historial_column->num_rows > 0;
            
            // Iniciar transacción para asegurar consistencia
            $conn->begin_transaction();
            
            try {
                // Devolver cantidades al inventario
                foreach ($productos_carta as $producto) {
                    $id_producto = intval($producto['id_producto']);
                    $cantidad = intval($producto['cantidad_entregada']);
                    
                    // Actualizar cantidad del producto (devolver al inventario)
                    $update_producto = $conn->prepare("UPDATE productos_almacen SET cantidad = cantidad + ? WHERE id_producto = ?");
                    $update_producto->bind_param('ii', $cantidad, $id_producto);
                    $update_producto->execute();
                    $update_producto->close();
                    
                    // Registrar en historial
                    $motivo = "Carta de entrega #$numero_carta cancelada - Productos devueltos";
                    if ($has_historial_column && $user_empresa_id !== null) {
                        $historial_stmt = $conn->prepare("INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, usuario, motivo, fecha_movimiento, id_empresa) VALUES (?, 'Devolución', ?, ?, ?, NOW(), ?)");
                        $historial_stmt->bind_param('iissi', $id_producto, $cantidad, $user, $motivo, $user_empresa_id);
                    } else {
                        $historial_stmt = $conn->prepare("INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, usuario, motivo, fecha_movimiento) VALUES (?, 'Devolución', ?, ?, ?, NOW())");
                        $historial_stmt->bind_param('iiss', $id_producto, $cantidad, $user, $motivo);
                    }
                    $historial_stmt->execute();
                    $historial_stmt->close();
                }
                
                // Actualizar estado de la carta
                $sql = "UPDATE cartas_entrega SET estado_entrega = ? WHERE numero_carta = ?";
                if ($has_empresa_column && $user_empresa_id !== null) {
                    $sql .= " AND id_empresa = " . intval($user_empresa_id);
                }
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ss', $nuevo_estado, $numero_carta);
                $stmt->execute();
                $stmt->close();
                
                // Confirmar transacción
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Carta cancelada. Los productos han sido devueltos al inventario.']);
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Error al cancelar la carta: ' . $e->getMessage()]);
            }
        } else {
            // Cambio de estado normal (no es cancelación)
            $sql = "UPDATE cartas_entrega SET estado_entrega = ? WHERE numero_carta = ?";
            if ($has_empresa_column && $user_empresa_id !== null) {
                $sql .= " AND id_empresa = " . intval($user_empresa_id);
            }
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $nuevo_estado, $numero_carta);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
            $stmt->close();
        }
    }
}
?>

