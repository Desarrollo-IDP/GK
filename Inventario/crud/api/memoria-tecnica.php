<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.

require_once '../includes/db.php';
require_once '../../../auth.php';
require_once '../../../log_activity.php';
checkAuth();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $id_empresa = isset($_GET['id_empresa']) ? intval($_GET['id_empresa']) : 0;
        $sede_filter = isset($_GET['sede']) ? $_GET['sede'] : 'General';

        if ($id_empresa > 0) {
            // 1. Obtener sedes únicas para esta empresa
            $sql_sedes = "SELECT DISTINCT sede FROM empresa_memoria_tecnica WHERE id_empresa = ? AND activo = 1";
            $stmt_sedes = $conn->prepare($sql_sedes);
            $stmt_sedes->bind_param('i', $id_empresa);
            $stmt_sedes->execute();
            $res_sedes = $stmt_sedes->get_result();
            $sedes = [];
            while ($row = $res_sedes->fetch_assoc()) $sedes[] = $row['sede'];
            
            if ((empty($sede_filter) || $sede_filter === 'General') && !empty($sedes)) {
                $sede_filter = $sedes[0];
            }
            // if (empty($sedes)) $sedes = ['General']; // Eliminado a petición

            // 2. Obtener notas y diagrama vigentes (filtrado por sede)
            $sql_actual = "SELECT m.id, m.id_empresa, m.sede, m.tipo, m.titulo, m.descripcion, m.fecha_actualizacion, m.fijado, m.orden, m.activo, u.nombre_usuario as usuario_nombre 
                           FROM empresa_memoria_tecnica m 
                           LEFT JOIN usuarios u ON m.actualizado_por = u.id_usuario 
                           WHERE m.id_empresa = ? AND TRIM(m.sede) = TRIM(?) AND m.activo = 1 
                           ORDER BY m.fijado DESC, m.orden DESC, m.fecha_actualizacion DESC";
            $stmt1 = $conn->prepare($sql_actual);
            $stmt1->bind_param('is', $id_empresa, $sede_filter);
            $stmt1->execute();
            $res1 = $stmt1->get_result();
            $actuales = [];
            while ($row = $res1->fetch_assoc()) {
                if ($row['tipo'] === 'diagrama') {
                    // Siempre generamos la URL para el diagrama binario si el registro existe
                    $row['archivo_url'] = 'api/get-diagrama.php?id=' . $row['id'];
                }
                $actuales[] = $row;
            }

            // 2. Obtener historial real desde historial_actividades (Solo para supervisor/administrador)
            $historial = [];
            $user_rol = strtolower($_SESSION['user']['rol'] ?? '');
            if ($user_rol === 'supervisor' || $user_rol === 'administrador' || (function_exists('isAdmin') && isAdmin())) {
                $sql_historial = "SELECT h.*, h.nombre_usuario as usuario_nombre 
                                  FROM historial_actividades h 
                                  WHERE h.id_empresa = ? 
                                  AND h.tabla_afectada IN ('empresa_memoria_tecnica', 'equipos')
                                  AND (h.accion LIKE '%nota%' OR h.accion LIKE '%diagrama%' OR h.accion LIKE '%sede%')
                                  ORDER BY h.fecha_hora DESC LIMIT 50";
                $stmt2 = $conn->prepare($sql_historial);
                $stmt2->bind_param('i', $id_empresa);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                while ($row = $res2->fetch_assoc()) $historial[] = $row;
            }

            ob_clean();
            echo json_encode([
                'success' => true, 
                'sedes' => $sedes, 
                'sede_actual' => $sede_filter,
                'actuales' => $actuales, 
                'historial' => $historial
            ]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID de empresa requerido']);
        }
        break;
    
    case 'POST':
        $action = $_POST['action'] ?? '';
        if ($action === 'edit_sede') {
            $id_empresa = isset($_POST['id_empresa']) ? intval($_POST['id_empresa']) : 0;
            $sede_old = $_POST['sede_old'] ?? '';
            $sede_new = $_POST['sede_new'] ?? '';
            $user_id = $_SESSION['user']['id'] ?? null;

            if ($id_empresa > 0 && !empty($sede_old) && !empty($sede_new)) {
                $sql = "UPDATE empresa_memoria_tecnica SET sede = ?, actualizado_por = ? WHERE id_empresa = ? AND sede = ? AND activo = 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('siis', $sede_new, $user_id, $id_empresa, $sede_old);
                $stmt->execute();
                
                if (function_exists('logActivity')) {
                    logActivity("Renombró sede: '$sede_old' a '$sede_new'", 'empresa_memoria_tecnica', null, "Sede anterior: $sede_old", $id_empresa);
                }

                ob_clean();
                echo json_encode(['success' => true]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Datos insuficientes']);
            }
            break;
        }

        if ($action === 'delete_sede') {
            $id_empresa = isset($_POST['id_empresa']) ? intval($_POST['id_empresa']) : 0;
            $sede = $_POST['sede'] ?? '';
            $user_id = $_SESSION['user']['id'] ?? null;

            if ($id_empresa > 0 && !empty($sede)) {
                $sql = "UPDATE empresa_memoria_tecnica SET activo = 0, actualizado_por = ? WHERE id_empresa = ? AND sede = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('iis', $user_id, $id_empresa, $sede);
                $stmt->execute();
                
                if (function_exists('logActivity')) {
                    logActivity("Eliminó sede: '$sede'", 'empresa_memoria_tecnica', null, "Sede eliminada: $sede y su contenido", $id_empresa);
                }

                ob_clean();
                echo json_encode(['success' => true]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Datos insuficientes']);
            }
            break;
        }

        if ($action === 'delete_diagrama') {
            $id_empresa = isset($_POST['id_empresa']) ? intval($_POST['id_empresa']) : 0;
            $sede = $_POST['sede'] ?? '';
            $user_id = $_SESSION['user']['id'] ?? null;

            if ($id_empresa > 0 && !empty($sede)) {
                $sql = "UPDATE empresa_memoria_tecnica SET activo = 0, actualizado_por = ? WHERE id_empresa = ? AND sede = ? AND tipo = 'diagrama'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('iis', $user_id, $id_empresa, $sede);
                $stmt->execute();
                
                if (function_exists('logActivity')) {
                    logActivity("Eliminó diagrama de la sede: '$sede'", 'empresa_memoria_tecnica', null, "Diagrama removido en $sede", $id_empresa);
                }

                ob_clean();
                echo json_encode(['success' => true]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Datos insuficientes']);
            }
            break;
        }

        if ($action === 'crear_sede') {
            $id_empresa = isset($_POST['id_empresa']) ? intval($_POST['id_empresa']) : 0;
            $sede = $_POST['sede'] ?? '';
            $user_id = $_SESSION['user']['id'] ?? null;

            if ($id_empresa > 0 && !empty($sede)) {
                // Verificar si ya existe para no duplicar
                $check = $conn->query("SELECT id FROM empresa_memoria_tecnica WHERE id_empresa = $id_empresa AND sede = '$sede' AND activo = 1 LIMIT 1");
                if ($check->num_rows === 0) {
                    $sql = "INSERT INTO empresa_memoria_tecnica (id_empresa, sede, tipo, titulo, creado_por, actualizado_por) VALUES (?, ?, 'config_sede', 'Registro de Sede', ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('isii', $id_empresa, $sede, $user_id, $user_id);
                    $stmt->execute();

                    if (function_exists('logActivity')) {
                        logActivity("Creó sede: '$sede'", 'empresa_memoria_tecnica', null, "Empresa ID: $id_empresa", $id_empresa);
                    }
                }
                ob_clean();
                echo json_encode(['success' => true]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Datos insuficientes']);
            }
            break;
        }

        if ($action === 'save_order') {
            $ids = $_POST['ids'] ?? [];
            if (!empty($ids)) {
                $count = count($ids);
                foreach ($ids as $index => $id) {
                    $new_order = $count - $index;
                    $id = intval($id);
                    $conn->query("UPDATE empresa_memoria_tecnica SET orden = $new_order WHERE id = $id");
                }
                ob_clean();
                echo json_encode(['success' => true]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'No IDs provided']);
            }
            break;
        }

        if ($action === 'reorder') {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $direccion = $_POST['direccion'] ?? ''; // 'up' o 'down'
            
            if ($id > 0 && ($direccion === 'up' || $direccion === 'down')) {
                // Obtener info de la nota actual
                $curr = $conn->query("SELECT id_empresa, orden, fijado FROM empresa_memoria_tecnica WHERE id = $id")->fetch_assoc();
                $id_empresa = $curr['id_empresa'];
                $orden_curr = $curr['orden'];
                $fijado = $curr['fijado'];

                // En orden DESC: UP es orden mayor, DOWN es orden menor
                if ($direccion === 'up') {
                    $neighbor = $conn->query("SELECT id, orden FROM empresa_memoria_tecnica WHERE id_empresa = $id_empresa AND activo = 1 AND fijado = $fijado AND orden > $orden_curr ORDER BY orden ASC LIMIT 1")->fetch_assoc();
                } else {
                    $neighbor = $conn->query("SELECT id, orden FROM empresa_memoria_tecnica WHERE id_empresa = $id_empresa AND activo = 1 AND fijado = $fijado AND orden < $orden_curr ORDER BY orden DESC LIMIT 1")->fetch_assoc();
                }

                if ($neighbor) {
                    $id_neighbor = $neighbor['id'];
                    $orden_neighbor = $neighbor['orden'];
                    
                    // Intercambiar
                    $conn->query("UPDATE empresa_memoria_tecnica SET orden = $orden_neighbor WHERE id = $id");
                    $conn->query("UPDATE empresa_memoria_tecnica SET orden = $orden_curr WHERE id = $id_neighbor");
                    
                    ob_clean();
                    echo json_encode(['success' => true]);
                } else {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'No hay más notas en esa dirección']);
                }
            }
            break;
        }

        $id_empresa = isset($_POST['id_empresa']) ? intval($_POST['id_empresa']) : 0;
        $tipo = $_POST['tipo'] ?? '';
        $titulo = $_POST['titulo'] ?? null;
        $descripcion = $_POST['descripcion'] ?? null;
        $sede = $_POST['sede'] ?? 'General';
        $archivo_url = $_POST['archivo_url'] ?? null;
        $archivo_nombre = $_POST['archivo_nombre'] ?? null;
        $user_id = $_SESSION['user']['id'] ?? null;

        if ($id_empresa > 0 && $tipo) {
            $sql = "INSERT INTO empresa_memoria_tecnica (id_empresa, sede, tipo, titulo, descripcion, creado_por, actualizado_por, orden) VALUES (?, ?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(orden), 0) + 1 FROM empresa_memoria_tecnica m2 WHERE id_empresa = ?))";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('issssiii', $id_empresa, $sede, $tipo, $titulo, $descripcion, $user_id, $user_id, $id_empresa);
            $stmt->execute();

            if (function_exists('logActivity')) {
                logActivity("Agregó nota en '$sede': $titulo", "empresa_memoria_tecnica", $stmt->insert_id, $descripcion, $id_empresa);
            }

            ob_clean();
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Datos insuficientes']);
        }
        break;

    case 'DELETE':
        parse_str(file_get_contents('php://input'), $_DELETE);
        $id = isset($_DELETE['id']) ? intval($_DELETE['id']) : 0;
        if ($id > 0) {
            // Obtener info para el log antes de borrar
            $info = $conn->query("SELECT titulo, id_empresa, sede, tipo FROM empresa_memoria_tecnica WHERE id = $id")->fetch_assoc();
            $titulo = $info['titulo'] ?? 'Desconocida';
            $id_empresa = $info['id_empresa'] ?? 0;
            $tipo_txt = ($info['tipo'] ?? '') === 'diagrama' ? 'diagrama' : 'nota';
            $sede_txt = $info['sede'] ?? 'Sin sede';

            $user_id = $_SESSION['user']['id'] ?? null;
            $sql = "UPDATE empresa_memoria_tecnica SET activo = 0, actualizado_por = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $user_id, $id);
            $stmt->execute();

            if (function_exists('logActivity')) {
                logActivity("Eliminó $tipo_txt en '$sede_txt': $titulo", "empresa_memoria_tecnica", $id, "ID Registro: $id", $id_empresa);
            }

            ob_clean();
            echo json_encode(['success' => true]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
        }
        break;
    case 'PUT':
        parse_str(file_get_contents('php://input'), $_PUT);
        $id = isset($_PUT['id']) ? intval($_PUT['id']) : 0;
        $titulo = $_PUT['titulo'] ?? null;
        $descripcion = $_PUT['descripcion'] ?? null;
        $sede = $_PUT['sede'] ?? 'General';
        $user_id = $_SESSION['user']['id'] ?? null;

        if ($id > 0) {
            // Obtener id_empresa para el log
            $info = $conn->query("SELECT id_empresa FROM empresa_memoria_tecnica WHERE id = $id")->fetch_assoc();
            $id_empresa = $info['id_empresa'] ?? 0;

            $sql = "UPDATE empresa_memoria_tecnica SET titulo = ?, descripcion = ?, sede = ?, actualizado_por = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssii', $titulo, $descripcion, $sede, $user_id, $id);
            $stmt->execute();

            if (function_exists('logActivity')) {
                logActivity("Editó nota en '$sede': $titulo", "empresa_memoria_tecnica", $id, $descripcion, $id_empresa);
            }

            ob_clean();
            echo json_encode(['success' => true]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
        }
        break;
    case 'PATCH':
        parse_str(file_get_contents('php://input'), $_PATCH);
        $id = isset($_PATCH['id']) ? intval($_PATCH['id']) : 0;
        $fijado = isset($_PATCH['fijado']) ? intval($_PATCH['fijado']) : 0;

        if ($id > 0) {
            // Si se va a fijar (1), primero desfijar todas las demás de esta empresa
            if ($fijado == 1) {
                $info = $conn->query("SELECT id_empresa FROM empresa_memoria_tecnica WHERE id = $id")->fetch_assoc();
                $id_empresa = $info['id_empresa'] ?? 0;
                if ($id_empresa > 0) {
                    $conn->query("UPDATE empresa_memoria_tecnica SET fijado = 0 WHERE id_empresa = $id_empresa");
                }
            }

            $sql = "UPDATE empresa_memoria_tecnica SET fijado = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $fijado, $id);
            $stmt->execute();
            ob_clean();
            echo json_encode(['success' => true]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
        }
        break;
}