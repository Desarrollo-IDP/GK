<?php
require_once '../../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
checkAuth();
$pageTitle = 'Gestión de Almacenes';
require_once '../../includes/header.php';

$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa en almacenes y bodegas
$check_column_almacenes = $conn->query("SHOW COLUMNS FROM almacenes LIKE 'id_empresa'");
$has_empresa_column_almacenes = $check_column_almacenes && $check_column_almacenes->num_rows > 0;

$check_column_bodegas = $conn->query("SHOW COLUMNS FROM bodegas LIKE 'id_empresa'");
$has_empresa_column_bodegas = $check_column_bodegas && $check_column_bodegas->num_rows > 0;

$check_column_catalogos = $conn->query("SHOW COLUMNS FROM catalogos LIKE 'id_empresa'");
$has_empresa_column_catalogos = ($check_column_catalogos && $check_column_catalogos->num_rows > 0) ? true : false;

// Procesar acciones
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $id_bodega = intval($_POST['id_bodega']);
        
        // Verificar que la bodega pertenezca a la empresa del usuario
        if ($has_empresa_column_bodegas && $user_empresa_id !== null) {
            $verify_bodega_query = "SELECT id_bodega FROM bodegas WHERE id_bodega = ? AND id_empresa = ?";
            $verify_bodega_stmt = $conn->prepare($verify_bodega_query);
            $verify_bodega_stmt->bind_param("ii", $id_bodega, $user_empresa_id);
            $verify_bodega_stmt->execute();
            $verify_bodega_result = $verify_bodega_stmt->get_result();
            
            if ($verify_bodega_result->num_rows === 0) {
                $error_message = 'No tienes permiso para crear almacenes en esta bodega';
                $verify_bodega_stmt->close();
            } else {
                $verify_bodega_stmt->close();
                
                $nombre = limpiarDatos($_POST['nombre']);
                $ubicacion_especifica = !empty($_POST['ubicacion_especifica']) ? limpiarDatos($_POST['ubicacion_especifica']) : '';
                $pasillo = !empty($_POST['pasillo']) ? limpiarDatos($_POST['pasillo']) : '';
                $seccion = !empty($_POST['seccion']) ? limpiarDatos($_POST['seccion']) : '';
                $nivel = !empty($_POST['nivel']) ? limpiarDatos($_POST['nivel']) : '';
                $capacidad = !empty($_POST['capacidad']) ? floatval($_POST['capacidad']) : 0;
                $unidad_medida = !empty($_POST['unidad_medida']) ? limpiarDatos($_POST['unidad_medida']) : 'm²';
                $temperatura = !empty($_POST['temperatura']) ? limpiarDatos($_POST['temperatura']) : '';
                $humedad = !empty($_POST['humedad']) ? limpiarDatos($_POST['humedad']) : '';
                $tipo_almacen = limpiarDatos($_POST['tipo_almacen']);
                $estado_almacen = limpiarDatos($_POST['estado_almacen']);
                $notas = !empty($_POST['notas']) ? limpiarDatos($_POST['notas']) : '';
                
                // Generar código único automáticamente basado en el ID de bodega
                $count_query = "SELECT COUNT(*) as total FROM almacenes WHERE id_bodega = ?";
                $count_stmt = $conn->prepare($count_query);
                $count_stmt->bind_param('i', $id_bodega);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                $count = $count_result->fetch_assoc()['total'];
                $count_stmt->close();
                
                $codigo = 'BOD' . $id_bodega . '-A' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
                
                // Construir query según si existe columna id_empresa
                if ($has_empresa_column_almacenes && $user_empresa_id !== null) {
                    $sql = "INSERT INTO almacenes (id_bodega, nombre, codigo, ubicacion_especifica, pasillo, seccion, nivel, capacidad, unidad_medida, temperatura, humedad, tipo_almacen, estado_almacen, notas, id_empresa) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('issssssdssssssi', $id_bodega, $nombre, $codigo, $ubicacion_especifica, $pasillo, $seccion, $nivel, $capacidad, $unidad_medida, $temperatura, $humedad, $tipo_almacen, $estado_almacen, $notas, $user_empresa_id);
                } else {
                    $sql = "INSERT INTO almacenes (id_bodega, nombre, codigo, ubicacion_especifica, pasillo, seccion, nivel, capacidad, unidad_medida, temperatura, humedad, tipo_almacen, estado_almacen, notas) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('issssssdssssss', $id_bodega, $nombre, $codigo, $ubicacion_especifica, $pasillo, $seccion, $nivel, $capacidad, $unidad_medida, $temperatura, $humedad, $tipo_almacen, $estado_almacen, $notas);
                }
                
                if ($stmt->execute()) {
                    $success_message = 'Almacén creado exitosamente con código: ' . $codigo;
                } else {
                    $error_message = 'Error al crear el almacén: ' . $conn->error;
                }
            }
        } else {
            $nombre = limpiarDatos($_POST['nombre']);
            $ubicacion_especifica = !empty($_POST['ubicacion_especifica']) ? limpiarDatos($_POST['ubicacion_especifica']) : '';
            $pasillo = !empty($_POST['pasillo']) ? limpiarDatos($_POST['pasillo']) : '';
            $seccion = !empty($_POST['seccion']) ? limpiarDatos($_POST['seccion']) : '';
            $nivel = !empty($_POST['nivel']) ? limpiarDatos($_POST['nivel']) : '';
            $capacidad = !empty($_POST['capacidad']) ? floatval($_POST['capacidad']) : 0;
            $unidad_medida = !empty($_POST['unidad_medida']) ? limpiarDatos($_POST['unidad_medida']) : 'm²';
            $temperatura = !empty($_POST['temperatura']) ? limpiarDatos($_POST['temperatura']) : '';
            $humedad = !empty($_POST['humedad']) ? limpiarDatos($_POST['humedad']) : '';
            $tipo_almacen = limpiarDatos($_POST['tipo_almacen']);
            $estado_almacen = limpiarDatos($_POST['estado_almacen']);
            $notas = !empty($_POST['notas']) ? limpiarDatos($_POST['notas']) : '';
            
            // Generar código único automáticamente basado en el ID de bodega
            $count = $conn->query("SELECT COUNT(*) as total FROM almacenes WHERE id_bodega = $id_bodega")->fetch_assoc()['total'];
            $codigo = 'BOD' . $id_bodega . '-A' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
            
            $sql = "INSERT INTO almacenes (id_bodega, nombre, codigo, ubicacion_especifica, pasillo, seccion, nivel, capacidad, unidad_medida, temperatura, humedad, tipo_almacen, estado_almacen, notas) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('issssssdssssss', $id_bodega, $nombre, $codigo, $ubicacion_especifica, $pasillo, $seccion, $nivel, $capacidad, $unidad_medida, $temperatura, $humedad, $tipo_almacen, $estado_almacen, $notas);
            
            if ($stmt->execute()) {
                $success_message = 'Almacén creado exitosamente con código: ' . $codigo;
            } else {
                $error_message = 'Error al crear el almacén: ' . $conn->error;
            }
        }
    }
    
    if ($action === 'update') {
        $id_almacen = intval($_POST['id_almacen']);
        
        // Verificar permisos: si el usuario tiene empresa asignada, verificar que el almacén pertenezca a su empresa
        if ($has_empresa_column_almacenes && $user_empresa_id !== null) {
            $verify_query = "SELECT id_almacen FROM almacenes WHERE id_almacen = ? AND id_empresa = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bind_param("ii", $id_almacen, $user_empresa_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                $error_message = 'No tienes permiso para editar este almacén';
                $verify_stmt->close();
            } else {
                $verify_stmt->close();
                
                $id_bodega = intval($_POST['id_bodega']);
                
                // Verificar que la bodega también pertenezca a la empresa del usuario
                if ($has_empresa_column_bodegas) {
                    $verify_bodega_query = "SELECT id_bodega FROM bodegas WHERE id_bodega = ? AND id_empresa = ?";
                    $verify_bodega_stmt = $conn->prepare($verify_bodega_query);
                    $verify_bodega_stmt->bind_param("ii", $id_bodega, $user_empresa_id);
                    $verify_bodega_stmt->execute();
                    $verify_bodega_result = $verify_bodega_stmt->get_result();
                    
                    if ($verify_bodega_result->num_rows === 0) {
                        $error_message = 'No tienes permiso para asignar este almacén a esa bodega';
                        $verify_bodega_stmt->close();
                    } else {
                        $verify_bodega_stmt->close();
                        
                        $nombre = limpiarDatos($_POST['nombre']);
                        $codigo = !empty($_POST['codigo']) ? limpiarDatos($_POST['codigo']) : '';
                        $ubicacion_especifica = !empty($_POST['ubicacion_especifica']) ? limpiarDatos($_POST['ubicacion_especifica']) : '';
                        $pasillo = !empty($_POST['pasillo']) ? limpiarDatos($_POST['pasillo']) : '';
                        $seccion = !empty($_POST['seccion']) ? limpiarDatos($_POST['seccion']) : '';
                        $nivel = !empty($_POST['nivel']) ? limpiarDatos($_POST['nivel']) : '';
                        $capacidad = !empty($_POST['capacidad']) ? floatval($_POST['capacidad']) : 0;
                        $unidad_medida = !empty($_POST['unidad_medida']) ? limpiarDatos($_POST['unidad_medida']) : 'm²';
                        $temperatura = !empty($_POST['temperatura']) ? limpiarDatos($_POST['temperatura']) : '';
                        $humedad = !empty($_POST['humedad']) ? limpiarDatos($_POST['humedad']) : '';
                        $tipo_almacen = limpiarDatos($_POST['tipo_almacen']);
                        $estado_almacen = limpiarDatos($_POST['estado_almacen']);
                        $notas = !empty($_POST['notas']) ? limpiarDatos($_POST['notas']) : '';
                        
                        $sql = "UPDATE almacenes SET id_bodega=?, nombre=?, codigo=?, ubicacion_especifica=?, pasillo=?, seccion=?, nivel=?, capacidad=?, unidad_medida=?, temperatura=?, humedad=?, tipo_almacen=?, estado_almacen=?, notas=? WHERE id_almacen=? AND id_empresa=?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('issssssdssssssii', $id_bodega, $nombre, $codigo, $ubicacion_especifica, $pasillo, $seccion, $nivel, $capacidad, $unidad_medida, $temperatura, $humedad, $tipo_almacen, $estado_almacen, $notas, $id_almacen, $user_empresa_id);
                        
                        if ($stmt->execute() && $stmt->affected_rows === 1) {
                            $success_message = 'Almacén actualizado exitosamente';
                        } else {
                            $error_message = 'Error al actualizar el almacén: ' . $conn->error;
                        }
                    }
                } else {
                    $id_bodega = intval($_POST['id_bodega']);
                    $nombre = limpiarDatos($_POST['nombre']);
                    $codigo = !empty($_POST['codigo']) ? limpiarDatos($_POST['codigo']) : '';
                    $ubicacion_especifica = !empty($_POST['ubicacion_especifica']) ? limpiarDatos($_POST['ubicacion_especifica']) : '';
                    $pasillo = !empty($_POST['pasillo']) ? limpiarDatos($_POST['pasillo']) : '';
                    $seccion = !empty($_POST['seccion']) ? limpiarDatos($_POST['seccion']) : '';
                    $nivel = !empty($_POST['nivel']) ? limpiarDatos($_POST['nivel']) : '';
                    $capacidad = !empty($_POST['capacidad']) ? floatval($_POST['capacidad']) : 0;
                    $unidad_medida = !empty($_POST['unidad_medida']) ? limpiarDatos($_POST['unidad_medida']) : 'm²';
                    $temperatura = !empty($_POST['temperatura']) ? limpiarDatos($_POST['temperatura']) : '';
                    $humedad = !empty($_POST['humedad']) ? limpiarDatos($_POST['humedad']) : '';
                    $tipo_almacen = limpiarDatos($_POST['tipo_almacen']);
                    $estado_almacen = limpiarDatos($_POST['estado_almacen']);
                    $notas = !empty($_POST['notas']) ? limpiarDatos($_POST['notas']) : '';
                    
                    $sql = "UPDATE almacenes SET id_bodega=?, nombre=?, codigo=?, ubicacion_especifica=?, pasillo=?, seccion=?, nivel=?, capacidad=?, unidad_medida=?, temperatura=?, humedad=?, tipo_almacen=?, estado_almacen=?, notas=? WHERE id_almacen=? AND id_empresa=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('issssssdssssssii', $id_bodega, $nombre, $codigo, $ubicacion_especifica, $pasillo, $seccion, $nivel, $capacidad, $unidad_medida, $temperatura, $humedad, $tipo_almacen, $estado_almacen, $notas, $id_almacen, $user_empresa_id);
                    
                    if ($stmt->execute() && $stmt->affected_rows === 1) {
                        $success_message = 'Almacén actualizado exitosamente';
                    } else {
                        $error_message = 'Error al actualizar el almacén: ' . $conn->error;
                    }
                }
            }
        } else {
            $id_bodega = intval($_POST['id_bodega']);
            $nombre = limpiarDatos($_POST['nombre']);
            $codigo = !empty($_POST['codigo']) ? limpiarDatos($_POST['codigo']) : '';
            $ubicacion_especifica = !empty($_POST['ubicacion_especifica']) ? limpiarDatos($_POST['ubicacion_especifica']) : '';
            $pasillo = !empty($_POST['pasillo']) ? limpiarDatos($_POST['pasillo']) : '';
            $seccion = !empty($_POST['seccion']) ? limpiarDatos($_POST['seccion']) : '';
            $nivel = !empty($_POST['nivel']) ? limpiarDatos($_POST['nivel']) : '';
            $capacidad = !empty($_POST['capacidad']) ? floatval($_POST['capacidad']) : 0;
            $unidad_medida = !empty($_POST['unidad_medida']) ? limpiarDatos($_POST['unidad_medida']) : 'm²';
            $temperatura = !empty($_POST['temperatura']) ? limpiarDatos($_POST['temperatura']) : '';
            $humedad = !empty($_POST['humedad']) ? limpiarDatos($_POST['humedad']) : '';
            $tipo_almacen = limpiarDatos($_POST['tipo_almacen']);
            $estado_almacen = limpiarDatos($_POST['estado_almacen']);
            $notas = !empty($_POST['notas']) ? limpiarDatos($_POST['notas']) : '';
            
            $sql = "UPDATE almacenes SET id_bodega=?, nombre=?, codigo=?, ubicacion_especifica=?, pasillo=?, seccion=?, nivel=?, capacidad=?, unidad_medida=?, temperatura=?, humedad=?, tipo_almacen=?, estado_almacen=?, notas=? WHERE id_almacen=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('issssssdssssssi', $id_bodega, $nombre, $codigo, $ubicacion_especifica, $pasillo, $seccion, $nivel, $capacidad, $unidad_medida, $temperatura, $humedad, $tipo_almacen, $estado_almacen, $notas, $id_almacen);
            
            if ($stmt->execute()) {
                $success_message = 'Almacén actualizado exitosamente';
            } else {
                $error_message = 'Error al actualizar el almacén: ' . $conn->error;
            }
        }
    }
    
    if ($action === 'delete') {
        $id_almacen = intval($_POST['id_almacen']);
        
        // Verificar permisos: si el usuario tiene empresa asignada, verificar que el almacén pertenezca a su empresa
        if ($has_empresa_column_almacenes && $user_empresa_id !== null) {
            $verify_query = "SELECT id_almacen FROM almacenes WHERE id_almacen = ? AND id_empresa = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bind_param("ii", $id_almacen, $user_empresa_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                $error_message = 'No tienes permiso para eliminar este almacén';
                $verify_stmt->close();
            } else {
                $verify_stmt->close();
                
                // Verificar si tiene productos asociados
                $check_query = "SELECT COUNT(*) as total FROM productos_almacen WHERE id_almacen = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param('i', $id_almacen);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $row = $check_result->fetch_assoc();
                $check_stmt->close();
                
                if ($row['total'] > 0) {
                    $error_message = 'No se puede eliminar el almacén porque tiene productos asociados';
                } else {
                    $sql = "DELETE FROM almacenes WHERE id_almacen = ? AND id_empresa = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ii', $id_almacen, $user_empresa_id);
                    
                    if ($stmt->execute() && $stmt->affected_rows === 1) {
                        $success_message = 'Almacén eliminado exitosamente';
                    } else {
                        $error_message = 'Error al eliminar el almacén: ' . $conn->error;
                    }
                }
            }
        } else {
            // Verificar si tiene productos asociados
            $check = $conn->query("SELECT COUNT(*) as total FROM productos_almacen WHERE id_almacen = $id_almacen");
            $row = $check->fetch_assoc();
            
            if ($row['total'] > 0) {
                $error_message = 'No se puede eliminar el almacén porque tiene productos asociados';
            } else {
                $sql = "DELETE FROM almacenes WHERE id_almacen = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $id_almacen);
                
                if ($stmt->execute()) {
                    $success_message = 'Almacén eliminado exitosamente';
                } else {
                    $error_message = 'Error al eliminar el almacén: ' . $conn->error;
                }
            }
        }
    }
}

// Obtener lista de almacenes con filtro por empresa
$almacenes_query = "SELECT a.*, b.nombre as bodega_nombre, 
                    (SELECT COUNT(*) FROM productos_almacen WHERE id_almacen = a.id_almacen) as total_productos,
                    ROUND((a.capacidad_actual / a.capacidad) * 100, 2) as porcentaje_ocupacion
                    FROM almacenes a 
                    LEFT JOIN bodegas b ON a.id_bodega = b.id_bodega";
$almacenes_params = [];
$almacenes_types = '';
$where_added = false;

// Agregar filtro por empresa del usuario si la columna existe
if ($has_empresa_column_almacenes) {
    if ($user_empresa_id !== null) {
        // Usuario con empresa asignada: solo ver sus almacenes
        $almacenes_query .= " WHERE a.id_empresa = ?";
        $almacenes_params[] = $user_empresa_id;
        $almacenes_types = 'i';
        $where_added = true;
    }
    // Si es admin (user_empresa_id === null), no agregar filtro (verá todo)
}

if (!$where_added) {
    $almacenes_query .= " WHERE 1=1";
}

$almacenes_query .= " ORDER BY a.fecha_creacion DESC";

if (!empty($almacenes_params)) {
    $almacenes_stmt = $conn->prepare($almacenes_query);
    if ($almacenes_stmt) {
        $almacenes_stmt->bind_param($almacenes_types, ...$almacenes_params);
        $almacenes_stmt->execute();
        $almacenes = $almacenes_stmt->get_result();
    } else {
        $error_message = 'Error en consulta: ' . $conn->error;
        $almacenes = $conn->query("SELECT a.*, b.nombre as bodega_nombre, (SELECT COUNT(*) FROM productos_almacen WHERE id_almacen = a.id_almacen) as total_productos, ROUND((a.capacidad_actual / a.capacidad) * 100, 2) as porcentaje_ocupacion FROM almacenes a LEFT JOIN bodegas b ON a.id_bodega = b.id_bodega ORDER BY a.fecha_creacion DESC");
    }
} else {
    $almacenes = $conn->query($almacenes_query);
}

// Obtener bodegas para el selector con filtro por empresa
$bodegas_query = "SELECT * FROM bodegas WHERE estado_bodega = 'Activa'";
if ($has_empresa_column_bodegas && $user_empresa_id !== null) {
    $bodegas_query .= " AND id_empresa = " . intval($user_empresa_id);
}
$bodegas_query .= " ORDER BY nombre";
$bodegas = $conn->query($bodegas_query);
?>

<style>
    /* Estilos mejorados para almacenes.php */
    .almacenes-header {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 1.5rem 2rem;
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 4px solid #1E2A3B;
    }
    
    .almacenes-header h1 {
        color: #1E2A3B;
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .almacenes-header h1 i {
        color: #1E2A3B;
        font-size: 1.5rem;
    }
    
    .almacenes-header .btn-primary {
        background-color: #1E2A3B;
        color: #FFFFFF;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(30, 42, 59, 0.2);
    }
    
    .almacenes-header .btn-primary:hover {
        background-color: #000000;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(30, 42, 59, 0.3);
    }
    
    .filters-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        border: 1px solid #E5E7EB;
    }
    
    .filters-card .form-control {
        border: 2px solid #D1D5DB;
        border-radius: 8px;
        padding: 0.875rem 1.25rem;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
    }
    
    .filters-card .form-control:focus {
        border-color: #1E2A3B;
        box-shadow: 0 0 0 3px rgba(30, 42, 59, 0.1);
        outline: none;
    }
    
    .table-container {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        border: 1px solid #E5E7EB;
    }
    
    .table-container table thead {
        background-color: #1E2A3B;
    }
    
    .table-container table thead th {
        padding: 1rem 1.25rem;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #FFFFFF;
        border-bottom: 2px solid #000000;
    }
    
    .table-container table tbody tr {
        border-bottom: 1px solid #E5E7EB;
        transition: background-color 0.2s ease;
    }
    
    .table-container table tbody tr:hover {
        background-color: #F9FAFB;
    }
    
    .table-container table tbody tr:last-child {
        border-bottom: none;
    }
    
    .table-container table tbody td {
        padding: 1rem 1.25rem;
        font-size: 0.9375rem;
        color: #1E2A3B;
    }
    
    .table-container table tbody td strong {
        color: #1E2A3B;
        font-weight: 600;
    }
    
    .badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8125rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .btn-sm {
        padding: 0.5rem 0.875rem;
        border-radius: 6px;
        font-size: 0.875rem;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-right: 0.5rem;
    }
    
    .btn-sm:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
    }
    
    .btn-sm:last-child {
        margin-right: 0;
    }
    
    .modal-overlay {
        z-index: 9999;
        backdrop-filter: blur(2px);
    }
    
    .modal {
        max-width: 800px;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        z-index: 10000;
        animation: modalSlideIn 0.3s ease-out;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-30px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    .modal-header {
        background-color: #1E2A3B;
        padding: 1.5rem 2rem;
        border-bottom: 2px solid #000000;
    }
    
    .modal-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .modal-header h3 i {
        font-size: 1.25rem;
    }
    
    .modal-close {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        transition: all 0.3s ease;
    }
    
    .modal-close:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    .modal-body {
        padding: 2rem;
        max-height: calc(90vh - 200px);
        overflow-y: auto;
    }
    
    .modal-footer {
        padding: 1.25rem 2rem;
        background-color: #F9FAFB;
        border-top: 1px solid #E5E7EB;
    }
    
    .modal-footer .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .modal-footer .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        color: #1E2A3B;
        font-weight: 600;
        font-size: 0.9375rem;
        margin-bottom: 0.5rem;
        display: block;
    }
    
    .form-control {
        border: 2px solid #D1D5DB;
        border-radius: 8px;
        padding: 0.875rem 1rem;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
    }
    
    .form-control:focus {
        border-color: #1E2A3B;
        box-shadow: 0 0 0 3px rgba(30, 42, 59, 0.1);
        outline: none;
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 100px;
    }
    
    .alert {
        border-radius: 8px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        border-left: 4px solid;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    @media (max-width: 768px) {
        .almacenes-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        
        .almacenes-header .btn-primary {
            width: 100%;
        }
        
        .filters-card > div {
            grid-template-columns: 1fr !important;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            flex-direction: column;
        }
        
        .modal-footer .btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }
    }
</style>

<div class="container">
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="almacenes-header">
        <h1>
            <i class="fas fa-boxes"></i> Gestión de Almacenes
        </h1>
        <button onclick="openModal('modal-nuevo-almacen')" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Almacén
        </button>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
            <input type="text" id="search-almacenes" class="form-control" placeholder="Buscar almacenes..." data-table-search="table-almacenes">
            
            <select id="filter-bodega" class="form-control" onchange="filtrarPorBodega()">
                <option value="">Todas las bodegas</option>
                <?php 
                // Obtener bodegas filtradas por empresa
                $bodegas_filter_query = "SELECT * FROM bodegas";
                if ($has_empresa_column_bodegas && $user_empresa_id !== null) {
                    $bodegas_filter_query .= " WHERE id_empresa = " . intval($user_empresa_id);
                }
                $bodegas_filter_query .= " ORDER BY nombre";
                $bodegas_filter = $conn->query($bodegas_filter_query);
                while ($b = $bodegas_filter->fetch_assoc()): 
                ?>
                    <option value="<?php echo $b['id_bodega']; ?>"><?php echo htmlspecialchars($b['nombre']); ?></option>
                <?php endwhile; ?>
            </select>
            
            <select id="filter-estado" class="form-control" onchange="filtrarPorEstado()">
                <option value="">Todos los estados</option>
                <?php
                // Sin filtro por empresa - mostrar todos los estados
                $estados_filter_query = "SELECT valor FROM catalogos WHERE modulo='almacen' AND campo='estado_almacen' AND activo=1 ORDER BY orden, valor";
                $estados_filter = $conn->query($estados_filter_query);
                while ($est = $estados_filter->fetch_assoc()):
                ?>
                    <option value="<?php echo htmlspecialchars($est['valor']); ?>"><?php echo htmlspecialchars($est['valor']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <!-- Tabla de Almacenes -->
    <div class="table-container">
        <table id="table-almacenes">
            <thead>
                <tr>
                    <!-- <th data-sortable>Código</th> -->
                    <th data-sortable>Nombre</th>
                    <th class="hide-mobile">Bodega</th>
                    <!-- <th class="hide-mobile">Ubicación</th> -->
                    <th>Tipo</th>
                    <!-- <th class="hide-mobile">Capacidad</th> -->
                    <th class="hide-mobile">Ocupación</th>
                    <th class="hide-mobile">Productos</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($almacenes->num_rows > 0): ?>
                    <?php while ($almacen = $almacenes->fetch_assoc()): ?>
                    <tr data-bodega="<?php echo $almacen['id_bodega']; ?>" data-estado="<?php echo $almacen['estado_almacen']; ?>">
                        <!-- <td><code><?php echo htmlspecialchars($almacen['codigo']); ?></code></td> -->
                        <td><strong><?php echo htmlspecialchars($almacen['nombre']); ?></strong></td>
                        <td class="hide-mobile"><?php echo htmlspecialchars($almacen['bodega_nombre']); ?></td>
                        <!-- <td class="hide-mobile"><?php echo htmlspecialchars($almacen['ubicacion_especifica']); ?></td> -->
                        <td>
                            <span class="badge badge-secondary"><?php echo $almacen['tipo_almacen']; ?></span>
                        </td>
                        <!-- <td class="hide-mobile"><?php echo $almacen['capacidad'] . ' ' . $almacen['unidad_medida']; ?></td> -->
                        <td class="hide-mobile">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="flex: 1; background: #E5E7EB; height: 8px; border-radius: 4px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);">
                                    <div style="width: <?php echo min($almacen['porcentaje_ocupacion'] ?? 0, 100); ?>%; 
                                                height: 100%; 
                                                background: <?php echo ($almacen['porcentaje_ocupacion'] ?? 0) > 80 ? '#ef4444' : (($almacen['porcentaje_ocupacion'] ?? 0) > 50 ? '#f59e0b' : '#10b981'); ?>; 
                                                transition: width 0.3s ease;"></div>
                                </div>
                                <small style="font-weight: 600; color: #1E2A3B; min-width: 45px; text-align: right;"><?php echo number_format($almacen['porcentaje_ocupacion'] ?? 0, 1); ?>%</small>
                            </div>
                        </td>
                        <td class="hide-mobile">
                            <span class="badge badge-info"><?php echo $almacen['total_productos']; ?></span>
                        </td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $almacen['estado_almacen'] == 'Disponible' ? 'success' : 
                                    ($almacen['estado_almacen'] == 'Ocupado' ? 'warning' : 'secondary'); 
                            ?>">
                                <?php echo $almacen['estado_almacen']; ?>
                            </span>
                        </td>
                        <td>
                            <button onclick='editarAlmacen(<?php echo json_encode($almacen); ?>)' class="btn btn-sm btn-info">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="confirmarEliminar(<?php echo $almacen['id_almacen']; ?>)" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No hay almacenes registrados</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Nuevo Almacén -->
<div class="modal-overlay" id="modal-nuevo-almacen">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-boxes"></i> Nuevo Almacén</h3>
            <button class="modal-close" onclick="closeModal('modal-nuevo-almacen')" aria-label="Cerrar">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="codigo" value="">
            <input type="hidden" name="ubicacion_especifica" value="">
            <input type="hidden" name="pasillo" value="">
            <input type="hidden" name="seccion" value="">
            <input type="hidden" name="nivel" value="">
            <input type="hidden" name="capacidad" value="0">
            <input type="hidden" name="unidad_medida" value="m²">
            <input type="hidden" name="temperatura" value="">
            <input type="hidden" name="humedad" value="">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Bodega *</label>
                        <select name="id_bodega" class="form-control" required>
                            <option value="">Seleccione una bodega</option>
                            <?php 
                            // Obtener bodegas activas filtradas por empresa
                            $bodegas_create_query = "SELECT * FROM bodegas WHERE estado_bodega = 'Activa'";
                            if ($has_empresa_column_bodegas && $user_empresa_id !== null) {
                                $bodegas_create_query .= " AND id_empresa = " . intval($user_empresa_id);
                            }
                            $bodegas_create_query .= " ORDER BY nombre";
                            $bodegas_create = $conn->query($bodegas_create_query);
                            while ($b = $bodegas_create->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $b['id_bodega']; ?>"><?php echo htmlspecialchars($b['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nombre del Almacén *</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                </div>
                
                <!-- <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Código *</label>
                        <input type="text" name="codigo" class="form-control" placeholder="Ej: BC-A1" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Almacén</label>
                        <select name="tipo_almacen" class="form-control">
                            <option value="General">General</option>
                            <option value="Refrigerado">Refrigerado</option>
                            <option value="Congelado">Congelado</option>
                            <option value="Peligroso">Peligroso</option>
                            <option value="Seguro">Seguro</option>
                        </select>
                    </div>
                </div> -->
                
                <div class="form-group">
                    <label class="form-label">Tipo de Almacén</label>
                    <select name="tipo_almacen" class="form-control">
                        <option value="">Seleccione un tipo</option>
                        <?php
                        // Debug: Verificar variables
                        $debug_tipos = "<!-- DEBUG Tipos: has_empresa_column_catalogos=" . ($has_empresa_column_catalogos ? 'true' : 'false') . ", user_empresa_id=" . ($user_empresa_id ?? 'NULL') . " -->";
                        echo $debug_tipos;
                        
                        $tipos_almacen_query = "SELECT valor FROM catalogos WHERE modulo='almacen' AND campo='tipo_almacen' AND activo=1";
                        if ($has_empresa_column_catalogos && $user_empresa_id !== null) {
                            // Solo mostrar catálogos de la empresa del usuario
                            $tipos_almacen_query .= " AND id_empresa = " . intval($user_empresa_id);
                        }
                        $tipos_almacen_query .= " ORDER BY orden, valor";
                        
                        // Debug: Mostrar query
                        echo "<!-- DEBUG Query Tipos: " . htmlspecialchars($tipos_almacen_query) . " -->";
                        
                        $tipos_almacen = $conn->query($tipos_almacen_query);
                        if ($tipos_almacen) {
                            $tipos_count = $tipos_almacen->num_rows;
                            echo "<!-- DEBUG Tipos encontrados: " . $tipos_count . " -->";
                            while ($tipo = $tipos_almacen->fetch_assoc()):
                        ?>
                            <option value="<?php echo htmlspecialchars($tipo['valor']); ?>"><?php echo htmlspecialchars($tipo['valor']); ?></option>
                        <?php 
                            endwhile;
                        } else {
                            echo "<!-- DEBUG Error Tipos: " . htmlspecialchars($conn->error) . " -->";
                        }
                        ?>
                    </select>
                </div>
                
                <!-- <div class="form-group">
                    <label class="form-label">Ubicación Específica</label>
                    <input type="text" name="ubicacion_especifica" class="form-control" placeholder="Ej: Planta Baja - Zona A">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Pasillo</label>
                        <input type="text" name="pasillo" class="form-control" placeholder="Ej: P1">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Sección</label>
                        <input type="text" name="seccion" class="form-control" placeholder="Ej: S1">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nivel</label>
                        <input type="text" name="nivel" class="form-control" placeholder="Ej: N1">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Capacidad *</label>
                        <input type="number" step="0.01" name="capacidad" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Unidad</label>
                        <select name="unidad_medida" class="form-control">
                            <option value="m²">m²</option>
                            <option value="m³">m³</option>
                            <option value="ft²">ft²</option>
                            <option value="pallets">Pallets</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Temperatura</label>
                        <input type="text" name="temperatura" class="form-control" placeholder="Ej: 2-8°C">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Humedad</label>
                        <input type="text" name="humedad" class="form-control" placeholder="Ej: 40-60%">
                    </div>
                </div> -->
                
                <div class="form-group">
                    <label class="form-label">Estado del Almacén</label>
                    <select name="estado_almacen" class="form-control">
                        <option value="">Seleccione un estado</option>
                        <?php
                        // Sin filtro por empresa - mostrar todos los estados
                        $estados_almacen_query = "SELECT valor FROM catalogos WHERE modulo='almacen' AND campo='estado_almacen' AND activo=1 ORDER BY orden, valor";
                        $estados_almacen = $conn->query($estados_almacen_query);
                        if ($estados_almacen) {
                            while ($est = $estados_almacen->fetch_assoc()):
                        ?>
                            <option value="<?php echo htmlspecialchars($est['valor']); ?>"><?php echo htmlspecialchars($est['valor']); ?></option>
                        <?php 
                            endwhile;
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notas</label>
                    <textarea name="notas" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-nuevo-almacen')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Almacén</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Almacén -->
<div class="modal-overlay" id="modal-editar-almacen">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Editar Almacén</h3>
            <button class="modal-close" onclick="closeModal('modal-editar-almacen')" aria-label="Cerrar">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id_almacen" id="edit_id_almacen">
            <input type="hidden" name="codigo" id="edit_codigo" value="">
            <input type="hidden" name="ubicacion_especifica" id="edit_ubicacion_especifica" value="">
            <input type="hidden" name="pasillo" id="edit_pasillo" value="">
            <input type="hidden" name="seccion" id="edit_seccion" value="">
            <input type="hidden" name="nivel" id="edit_nivel" value="">
            <input type="hidden" name="capacidad" id="edit_capacidad" value="0">
            <input type="hidden" name="unidad_medida" id="edit_unidad_medida" value="m²">
            <input type="hidden" name="temperatura" id="edit_temperatura" value="">
            <input type="hidden" name="humedad" id="edit_humedad" value="">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Bodega *</label>
                        <select id="edit_id_bodega_display" class="form-control" disabled style="background-color: #f3f4f6; cursor: not-allowed;">
                            <?php 
                            // Obtener bodegas activas filtradas por empresa
                            $bodegas_edit_query = "SELECT * FROM bodegas WHERE estado_bodega = 'Activa'";
                            if ($has_empresa_column_bodegas && $user_empresa_id !== null) {
                                $bodegas_edit_query .= " AND id_empresa = " . intval($user_empresa_id);
                            }
                            $bodegas_edit_query .= " ORDER BY nombre";
                            $bodegas_edit = $conn->query($bodegas_edit_query);
                            while ($b = $bodegas_edit->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $b['id_bodega']; ?>"><?php echo htmlspecialchars($b['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <input type="hidden" name="id_bodega" id="edit_id_bodega">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nombre del Almacén *</label>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                    </div>
                </div>
                
                <!-- <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Código *</label>
                        <input type="text" name="codigo" id="edit_codigo" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Almacén</label>
                        <select name="tipo_almacen" id="edit_tipo_almacen" class="form-control">
                            <option value="General">General</option>
                            <option value="Refrigerado">Refrigerado</option>
                            <option value="Congelado">Congelado</option>
                            <option value="Peligroso">Peligroso</option>
                            <option value="Seguro">Seguro</option>
                        </select>
                    </div>
                </div> -->
                
                <div class="form-group">
                    <label class="form-label">Tipo de Almacén</label>
                    <select name="tipo_almacen" id="edit_tipo_almacen" class="form-control">
                        <?php
                        $tipos_almacen_edit_query = "SELECT valor FROM catalogos WHERE modulo='almacen' AND campo='tipo_almacen' AND activo=1";
                        if ($has_empresa_column_catalogos && $user_empresa_id !== null) {
                            // Solo mostrar catálogos de la empresa del usuario
                            $tipos_almacen_edit_query .= " AND id_empresa = " . intval($user_empresa_id);
                        }
                        $tipos_almacen_edit_query .= " ORDER BY orden, valor";
                        $tipos_almacen_edit = $conn->query($tipos_almacen_edit_query);
                        while ($tipo = $tipos_almacen_edit->fetch_assoc()):
                        ?>
                            <option value="<?php echo htmlspecialchars($tipo['valor']); ?>"><?php echo htmlspecialchars($tipo['valor']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <!-- <div class="form-group">
                    <label class="form-label">Ubicación Específica</label>
                    <input type="text" name="ubicacion_especifica" id="edit_ubicacion_especifica" class="form-control">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Pasillo</label>
                        <input type="text" name="pasillo" id="edit_pasillo" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Sección</label>
                        <input type="text" name="seccion" id="edit_seccion" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nivel</label>
                        <input type="text" name="nivel" id="edit_nivel" class="form-control">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Capacidad *</label>
                        <input type="number" step="0.01" name="capacidad" id="edit_capacidad" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Unidad</label>
                        <select name="unidad_medida" id="edit_unidad_medida" class="form-control">
                            <option value="m²">m²</option>
                            <option value="m³">m³</option>
                            <option value="ft²">ft²</option>
                            <option value="pallets">Pallets</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Temperatura</label>
                        <input type="text" name="temperatura" id="edit_temperatura" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Humedad</label>
                        <input type="text" name="humedad" id="edit_humedad" class="form-control">
                    </div>
                </div> -->
                
                    <div class="form-group">
                        <label class="form-label">Estado del Almacén</label>
                        <select name="estado_almacen" id="edit_estado_almacen" class="form-control">
                            <?php
                            // Sin filtro por empresa - mostrar todos los estados
                            $estados_almacen_edit_query = "SELECT valor FROM catalogos WHERE modulo='almacen' AND campo='estado_almacen' AND activo=1 ORDER BY orden, valor";
                            $estados_almacen_edit = $conn->query($estados_almacen_edit_query);
                            while ($est = $estados_almacen_edit->fetch_assoc()):
                            ?>
                            <option value="<?php echo htmlspecialchars($est['valor']); ?>"><?php echo htmlspecialchars($est['valor']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notas</label>
                    <textarea name="notas" id="edit_notas" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-editar-almacen')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar Almacén</button>
            </div>
        </form>
    </div>
</div>

<!-- Form para eliminar -->
<form id="form-eliminar" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id_almacen" id="delete_id_almacen">
</form>

<script src="../../js/main.js"></script>
<script>
// Asegurar que las funciones de modal estén disponibles
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevenir scroll del body
    } else {
        console.error('Modal no encontrado: ' + modalId);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = ''; // Restaurar scroll del body
    }
}

// Cerrar modal al hacer clic fuera de él
document.addEventListener('DOMContentLoaded', function() {
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });
    
    // Cerrar modal con tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            modals.forEach(modal => {
                if (modal.classList.contains('active')) {
                    closeModal(modal.id);
                }
            });
        }
    });
});

function editarAlmacen(almacen) {
    document.getElementById('edit_id_almacen').value = almacen.id_almacen || '';
    document.getElementById('edit_id_bodega').value = almacen.id_bodega || '';
    const bodegaDisplay = document.getElementById('edit_id_bodega_display');
    if (bodegaDisplay) {
        bodegaDisplay.value = almacen.id_bodega || '';
    }
    document.getElementById('edit_nombre').value = almacen.nombre || '';
    document.getElementById('edit_codigo').value = almacen.codigo || '';
    document.getElementById('edit_ubicacion_especifica').value = almacen.ubicacion_especifica || '';
    document.getElementById('edit_pasillo').value = almacen.pasillo || '';
    document.getElementById('edit_seccion').value = almacen.seccion || '';
    document.getElementById('edit_nivel').value = almacen.nivel || '';
    document.getElementById('edit_capacidad').value = almacen.capacidad || 0;
    document.getElementById('edit_unidad_medida').value = almacen.unidad_medida || 'm²';
    document.getElementById('edit_temperatura').value = almacen.temperatura || '';
    document.getElementById('edit_humedad').value = almacen.humedad || '';
    
    // Si el valor actual de tipo no existe en las opciones, agregarlo temporalmente
    const selectTipo = document.getElementById('edit_tipo_almacen');
    if (selectTipo) {
        let optionExistsTipo = false;
        for (let option of selectTipo.options) {
            if (option.value === almacen.tipo_almacen) {
                optionExistsTipo = true;
                break;
            }
        }
        if (!optionExistsTipo && almacen.tipo_almacen) {
            const newOption = document.createElement('option');
            newOption.value = almacen.tipo_almacen;
            newOption.text = almacen.tipo_almacen + ' (Inactivo)';
            selectTipo.add(newOption);
        }
        selectTipo.value = almacen.tipo_almacen || '';
    }
    
    // Si el valor actual de estado no existe en las opciones, agregarlo temporalmente
    const selectEstado = document.getElementById('edit_estado_almacen');
    if (selectEstado) {
        let optionExistsEstado = false;
        for (let option of selectEstado.options) {
            if (option.value === almacen.estado_almacen) {
                optionExistsEstado = true;
                break;
            }
        }
        if (!optionExistsEstado && almacen.estado_almacen) {
            const newOption = document.createElement('option');
            newOption.value = almacen.estado_almacen;
            newOption.text = almacen.estado_almacen + ' (Inactivo)';
            selectEstado.add(newOption);
        }
        selectEstado.value = almacen.estado_almacen || '';
    }
    
    document.getElementById('edit_notas').value = almacen.notas || '';
    
    // Abrir modal después de un pequeño delay para asegurar que los valores se establezcan
    setTimeout(function() {
        openModal('modal-editar-almacen');
    }, 50);
}

function confirmarEliminar(id) {
    if (confirm('¿Está seguro de que desea eliminar este almacén? Esta acción no se puede deshacer.')) {
        document.getElementById('delete_id_almacen').value = id;
        document.getElementById('form-eliminar').submit();
    }
}

function filtrarPorBodega() {
    const bodegaId = document.getElementById('filter-bodega').value;
    const estado = document.getElementById('filter-estado').value;
    const rows = document.querySelectorAll('#table-almacenes tbody tr');
    
    rows.forEach(row => {
        const rowBodega = row.getAttribute('data-bodega');
        const rowEstado = row.getAttribute('data-estado');
        
        let showRow = true;
        
        if (bodegaId && rowBodega !== bodegaId) {
            showRow = false;
        }
        
        if (estado && rowEstado !== estado) {
            showRow = false;
        }
        
        row.style.display = showRow ? '' : 'none';
    });
}

function filtrarPorEstado() {
    const bodegaId = document.getElementById('filter-bodega').value;
    const estado = document.getElementById('filter-estado').value;
    const rows = document.querySelectorAll('#table-almacenes tbody tr');
    
    rows.forEach(row => {
        const rowBodega = row.getAttribute('data-bodega');
        const rowEstado = row.getAttribute('data-estado');
        
        let showRow = true;
        
        if (bodegaId && rowBodega !== bodegaId) {
            showRow = false;
        }
        
        if (estado && rowEstado !== estado) {
            showRow = false;
        }
        
        row.style.display = showRow ? '' : 'none';
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>

