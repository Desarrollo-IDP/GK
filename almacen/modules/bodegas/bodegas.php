<?php
require_once '../../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
checkAuth();
$pageTitle = 'Gestión de Bodegas';
require_once '../../includes/header.php';

$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa en bodegas
$check_column = $conn->query("SHOW COLUMNS FROM bodegas LIKE 'id_empresa'");
$has_empresa_column_bodegas = $check_column && $check_column->num_rows > 0;

$check_column_catalogos = $conn->query("SHOW COLUMNS FROM catalogos LIKE 'id_empresa'");
$has_empresa_column_catalogos = $check_column_catalogos && $check_column_catalogos->num_rows > 0;

// Procesar acciones
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nombre = limpiarDatos($_POST['nombre']);
        $ubicacion = !empty($_POST['ubicacion']) ? limpiarDatos($_POST['ubicacion']) : '';
        $direccion = limpiarDatos($_POST['direccion']);
        $ciudad = !empty($_POST['ciudad']) ? limpiarDatos($_POST['ciudad']) : '';
        $estado = !empty($_POST['estado']) ? limpiarDatos($_POST['estado']) : '';
        $codigo_postal = !empty($_POST['codigo_postal']) ? limpiarDatos($_POST['codigo_postal']) : '';
        $telefono = !empty($_POST['telefono']) ? limpiarDatos($_POST['telefono']) : '';
        $responsable = limpiarDatos($_POST['responsable']);
        $capacidad_total = !empty($_POST['capacidad_total']) ? floatval($_POST['capacidad_total']) : 0;
        $unidad_medida = !empty($_POST['unidad_medida']) ? limpiarDatos($_POST['unidad_medida']) : 'm²';
        $estado_bodega = !empty($_POST['estado_bodega']) ? limpiarDatos($_POST['estado_bodega']) : 'Activa';
        $notas = !empty($_POST['notas']) ? limpiarDatos($_POST['notas']) : '';
        
        // Asegurar que ubicacion no esté vacío si es requerido
        if (empty($ubicacion)) {
            $ubicacion = $direccion; // Usar dirección como ubicación si está vacío
        }
        
        // Construir query según si existe columna id_empresa
        if ($has_empresa_column_bodegas && $user_empresa_id !== null) {
            $sql = "INSERT INTO bodegas (nombre, ubicacion, direccion, ciudad, estado, codigo_postal, telefono, responsable, capacidad_total, unidad_medida, estado_bodega, notas, id_empresa) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ssssssssdsssi', $nombre, $ubicacion, $direccion, $ciudad, $estado, $codigo_postal, $telefono, $responsable, $capacidad_total, $unidad_medida, $estado_bodega, $notas, $user_empresa_id);
                
                if ($stmt->execute()) {
                    $success_message = 'Bodega creada exitosamente';
                } else {
                    $error_message = 'Error al crear la bodega: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = 'Error al preparar la consulta: ' . $conn->error;
            }
        } else {
            $sql = "INSERT INTO bodegas (nombre, ubicacion, direccion, ciudad, estado, codigo_postal, telefono, responsable, capacidad_total, unidad_medida, estado_bodega, notas) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ssssssssdsss', $nombre, $ubicacion, $direccion, $ciudad, $estado, $codigo_postal, $telefono, $responsable, $capacidad_total, $unidad_medida, $estado_bodega, $notas);
                
                if ($stmt->execute()) {
                    $success_message = 'Bodega creada exitosamente';
                } else {
                    $error_message = 'Error al crear la bodega: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = 'Error al preparar la consulta: ' . $conn->error;
            }
        }
    }
    
    if ($action === 'update') {
        $id_bodega = intval($_POST['id_bodega']);
        
        // Verificar permisos: si el usuario tiene empresa asignada, verificar que la bodega pertenezca a su empresa
        if ($has_empresa_column_bodegas && $user_empresa_id !== null) {
            $verify_query = "SELECT id_bodega FROM bodegas WHERE id_bodega = ? AND id_empresa = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bind_param("ii", $id_bodega, $user_empresa_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                $error_message = 'No tienes permiso para editar esta bodega';
                $verify_stmt->close();
            } else {
                $verify_stmt->close();
                
                $nombre = limpiarDatos($_POST['nombre']);
                $ubicacion = !empty($_POST['ubicacion']) ? limpiarDatos($_POST['ubicacion']) : '';
                $direccion = limpiarDatos($_POST['direccion']);
                $ciudad = !empty($_POST['ciudad']) ? limpiarDatos($_POST['ciudad']) : '';
                $estado = !empty($_POST['estado']) ? limpiarDatos($_POST['estado']) : '';
                $codigo_postal = !empty($_POST['codigo_postal']) ? limpiarDatos($_POST['codigo_postal']) : '';
                $telefono = !empty($_POST['telefono']) ? limpiarDatos($_POST['telefono']) : '';
                $responsable = limpiarDatos($_POST['responsable']);
                $capacidad_total = !empty($_POST['capacidad_total']) ? floatval($_POST['capacidad_total']) : 0;
                $unidad_medida = !empty($_POST['unidad_medida']) ? limpiarDatos($_POST['unidad_medida']) : 'm²';
                $estado_bodega = !empty($_POST['estado_bodega']) ? limpiarDatos($_POST['estado_bodega']) : 'Activa';
                $notas = !empty($_POST['notas']) ? limpiarDatos($_POST['notas']) : '';
                
                // Asegurar que ubicacion no esté vacío si es requerido
                if (empty($ubicacion)) {
                    $ubicacion = $direccion; // Usar dirección como ubicación si está vacío
                }
                
                $sql = "UPDATE bodegas SET nombre=?, ubicacion=?, direccion=?, ciudad=?, estado=?, codigo_postal=?, telefono=?, responsable=?, capacidad_total=?, unidad_medida=?, estado_bodega=?, notas=? WHERE id_bodega=? AND id_empresa=?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('ssssssssdsssii', $nombre, $ubicacion, $direccion, $ciudad, $estado, $codigo_postal, $telefono, $responsable, $capacidad_total, $unidad_medida, $estado_bodega, $notas, $id_bodega, $user_empresa_id);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Bodega actualizada exitosamente';
                    } else {
                        $error_message = 'Error al actualizar la bodega: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = 'Error al preparar la consulta: ' . $conn->error;
                }
            }
        } else {
            $nombre = limpiarDatos($_POST['nombre']);
            $ubicacion = !empty($_POST['ubicacion']) ? limpiarDatos($_POST['ubicacion']) : '';
            $direccion = limpiarDatos($_POST['direccion']);
            $ciudad = !empty($_POST['ciudad']) ? limpiarDatos($_POST['ciudad']) : '';
            $estado = !empty($_POST['estado']) ? limpiarDatos($_POST['estado']) : '';
            $codigo_postal = !empty($_POST['codigo_postal']) ? limpiarDatos($_POST['codigo_postal']) : '';
            $telefono = !empty($_POST['telefono']) ? limpiarDatos($_POST['telefono']) : '';
            $responsable = limpiarDatos($_POST['responsable']);
            $capacidad_total = !empty($_POST['capacidad_total']) ? floatval($_POST['capacidad_total']) : 0;
            $unidad_medida = !empty($_POST['unidad_medida']) ? limpiarDatos($_POST['unidad_medida']) : 'm²';
            $estado_bodega = !empty($_POST['estado_bodega']) ? limpiarDatos($_POST['estado_bodega']) : 'Activa';
            $notas = !empty($_POST['notas']) ? limpiarDatos($_POST['notas']) : '';
            
            // Asegurar que ubicacion no esté vacío si es requerido
            if (empty($ubicacion)) {
                $ubicacion = $direccion; // Usar dirección como ubicación si está vacío
            }
            
            $sql = "UPDATE bodegas SET nombre=?, ubicacion=?, direccion=?, ciudad=?, estado=?, codigo_postal=?, telefono=?, responsable=?, capacidad_total=?, unidad_medida=?, estado_bodega=?, notas=? WHERE id_bodega=?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ssssssssdsssi', $nombre, $ubicacion, $direccion, $ciudad, $estado, $codigo_postal, $telefono, $responsable, $capacidad_total, $unidad_medida, $estado_bodega, $notas, $id_bodega);
                
                if ($stmt->execute()) {
                    $success_message = 'Bodega actualizada exitosamente';
                } else {
                    $error_message = 'Error al actualizar la bodega: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = 'Error al preparar la consulta: ' . $conn->error;
            }
        }
    }
    
    if ($action === 'delete') {
        $id_bodega = intval($_POST['id_bodega']);
        
        // Verificar permisos: si el usuario tiene empresa asignada, verificar que la bodega pertenezca a su empresa
        if ($has_empresa_column_bodegas && $user_empresa_id !== null) {
            $verify_query = "SELECT id_bodega FROM bodegas WHERE id_bodega = ? AND id_empresa = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bind_param("ii", $id_bodega, $user_empresa_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                $error_message = 'No tienes permiso para eliminar esta bodega';
                $verify_stmt->close();
            } else {
                $verify_stmt->close();
                
                // Verificar si tiene almacenes asociados
                $check_query = "SELECT COUNT(*) as total FROM almacenes WHERE id_bodega = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param('i', $id_bodega);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $row = $check_result->fetch_assoc();
                $check_stmt->close();
                
                if ($row['total'] > 0) {
                    $error_message = 'No se puede eliminar la bodega porque tiene almacenes asociados';
                } else {
                    $sql = "DELETE FROM bodegas WHERE id_bodega = ? AND id_empresa = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ii', $id_bodega, $user_empresa_id);
                    
                    if ($stmt->execute() && $stmt->affected_rows === 1) {
                        $success_message = 'Bodega eliminada exitosamente';
                    } else {
                        $error_message = 'Error al eliminar la bodega: ' . $conn->error;
                    }
                }
            }
        } else {
            // Verificar si tiene almacenes asociados
            $check = $conn->query("SELECT COUNT(*) as total FROM almacenes WHERE id_bodega = $id_bodega");
            $row = $check->fetch_assoc();
            
            if ($row['total'] > 0) {
                $error_message = 'No se puede eliminar la bodega porque tiene almacenes asociados';
            } else {
                $sql = "DELETE FROM bodegas WHERE id_bodega = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $id_bodega);
                
                if ($stmt->execute()) {
                    $success_message = 'Bodega eliminada exitosamente';
                } else {
                    $error_message = 'Error al eliminar la bodega: ' . $conn->error;
                }
            }
        }
    }
}

// Obtener lista de bodegas con filtro por empresa
$bodegas_query = "SELECT b.*, 
                  (SELECT COUNT(*) FROM almacenes WHERE id_bodega = b.id_bodega) as total_almacenes
                  FROM bodegas b";
$params = [];
$types = '';
$where_added = false;

// Agregar filtro por empresa del usuario si la columna existe
if ($has_empresa_column_bodegas) {
    if ($user_empresa_id !== null) {
        // Usuario con empresa asignada: solo ver sus bodegas
        $bodegas_query .= " WHERE b.id_empresa = ?";
        $params[] = $user_empresa_id;
        $types = 'i';
        $where_added = true;
    }
    // Si es admin (user_empresa_id === null), no agregar filtro (verá todo)
}

if (!$where_added) {
    $bodegas_query .= " WHERE 1=1";
}

$bodegas_query .= " ORDER BY b.fecha_creacion DESC";

if (!empty($params)) {
    $bodegas_stmt = $conn->prepare($bodegas_query);
    if ($bodegas_stmt) {
        $bodegas_stmt->bind_param($types, ...$params);
        $bodegas_stmt->execute();
        $bodegas = $bodegas_stmt->get_result();
    } else {
        $error_message = 'Error en consulta: ' . $conn->error;
        $bodegas = $conn->query("SELECT b.*, (SELECT COUNT(*) FROM almacenes WHERE id_bodega = b.id_bodega) as total_almacenes FROM bodegas b ORDER BY b.fecha_creacion DESC");
    }
} else {
    $bodegas = $conn->query($bodegas_query);
}
?>

<style>
    /* Estilos mejorados para bodegas.php */
    .bodegas-header {
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
    
    .bodegas-header h1 {
        color: #1E2A3B;
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .bodegas-header h1 i {
        color: #1E2A3B;
        font-size: 1.5rem;
    }
    
    .bodegas-header .btn-primary {
        background-color: #1E2A3B;
        color: #FFFFFF;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(30, 42, 59, 0.2);
    }
    
    .bodegas-header .btn-primary:hover {
        background-color: #000000;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(30, 42, 59, 0.3);
    }
    
    .search-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        border: 1px solid #E5E7EB;
    }
    
    .search-card .form-control {
        border: 2px solid #D1D5DB;
        border-radius: 8px;
        padding: 0.875rem 1.25rem;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
    }
    
    .search-card .form-control:focus {
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
        max-width: 700px;
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
        .bodegas-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        
        .bodegas-header .btn-primary {
            width: 100%;
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

    <div class="bodegas-header">
        <h1>
            <i class="fas fa-warehouse"></i> Gestión de Bodegas
        </h1>
        <button onclick="openModal('modal-nueva-bodega')" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nueva Bodega
        </button>
    </div>

    <!-- Buscador -->
    <div class="search-card">
        <input type="text" id="search-bodegas" class="form-control" placeholder="Buscar bodegas por nombre, ubicación, responsable..." data-table-search="table-bodegas">
    </div>

    <!-- Tabla de Bodegas -->
    <div class="table-container">
        <table id="table-bodegas">
            <thead>
                <tr>
                    <!--<th data-sortable>ID</th> -->
                    <th data-sortable>Nombre</th>
                    <!-- <th class="hide-mobile" data-sortable>Ubicación</th> -->
                    <th class="hide-mobile">Ciudad</th>
                    <th>Responsable</th>
                    <!-- <th class="hide-mobile">Capacidad</th> -->
                    <th class="hide-mobile">Almacenes</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($bodegas->num_rows > 0): ?>
                    <?php while ($bodega = $bodegas->fetch_assoc()): ?>
                    <tr>
                        <!--<td><?php echo $bodega['id_bodega']; ?></td> -->   
                        <td><strong><?php echo htmlspecialchars($bodega['nombre']); ?></strong></td>
                        <!-- <td class="hide-mobile"><?php echo htmlspecialchars($bodega['ubicacion']); ?></td> -->
                        <td class="hide-mobile"><?php echo htmlspecialchars($bodega['ciudad']); ?></td>
                        <td><?php echo htmlspecialchars($bodega['responsable']); ?></td>
                        <!-- <td class="hide-mobile"><?php echo $bodega['capacidad_total'] . ' ' . $bodega['unidad_medida']; ?></td> -->
                        <td class="hide-mobile">
                            <span class="badge badge-info"><?php echo $bodega['total_almacenes']; ?> almacenes</span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $bodega['estado_bodega'] == 'Activa' ? 'success' : 'warning'; ?>">
                                <?php echo $bodega['estado_bodega']; ?>
                            </span>
                        </td>
                        <td>
                            <button onclick='editarBodega(<?php echo json_encode($bodega); ?>)' class="btn btn-sm btn-info">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="confirmarEliminar(<?php echo $bodega['id_bodega']; ?>)" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No hay bodegas registradas</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Nueva Bodega -->
<div class="modal-overlay" id="modal-nueva-bodega">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-warehouse"></i> Nueva Bodega</h3>
            <button class="modal-close" onclick="closeModal('modal-nueva-bodega')" aria-label="Cerrar">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="ubicacion" value="">
            <input type="hidden" name="capacidad_total" value="0">
            <input type="hidden" name="unidad_medida" value="m²">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nombre de la Bodega *</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
                
                <!-- <div class="form-group">
                    <label class="form-label">Ubicación *</label>
                    <input type="text" name="ubicacion" class="form-control" placeholder="Ej: Zona Industrial Norte" required>
                </div> -->
                
                <div class="form-group">
                    <label class="form-label">Dirección *</label>
                    <input type="text" name="direccion" class="form-control" placeholder="Calle, número, colonia" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <input type="text" name="estado" class="form-control">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Código Postal</label>
                        <input type="text" name="codigo_postal" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Responsable *</label>
                    <input type="text" name="responsable" class="form-control" required>
                </div>
                
                <!-- <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Capacidad Total *</label>
                        <input type="number" step="0.01" name="capacidad_total" class="form-control" required>
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
                </div> -->
                
                <div class="form-group">
                    <label class="form-label">Estado de la Bodega</label>
                    <select name="estado_bodega" class="form-control" required>
                        <option value="">Seleccione un estado</option>
                        <?php
                        // Sin filtro por empresa - mostrar todos los estados
                        $estados_bodega_query = "SELECT valor FROM catalogos WHERE modulo='bodega' AND campo='estado_bodega' AND activo=1 ORDER BY orden, valor";
                        $estados_bodega = $conn->query($estados_bodega_query);
                        while ($est = $estados_bodega->fetch_assoc()):
                        ?>
                            <option value="<?php echo htmlspecialchars($est['valor']); ?>" <?php echo ($est['valor'] == 'Activa') ? 'selected' : ''; ?>><?php echo htmlspecialchars($est['valor']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notas</label>
                    <textarea name="notas" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-nueva-bodega')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Bodega</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Bodega -->
<div class="modal-overlay" id="modal-editar-bodega">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Editar Bodega</h3>
            <button class="modal-close" onclick="closeModal('modal-editar-bodega')" aria-label="Cerrar">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id_bodega" id="edit_id_bodega">
            <input type="hidden" name="ubicacion" id="edit_ubicacion" value="">
            <input type="hidden" name="capacidad_total" id="edit_capacidad_total" value="0">
            <input type="hidden" name="unidad_medida" id="edit_unidad_medida" value="m²">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nombre de la Bodega *</label>
                    <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                </div>
                
                <!-- <div class="form-group">
                    <label class="form-label">Ubicación *</label>
                    <input type="text" name="ubicacion" id="edit_ubicacion" class="form-control" required>
                </div> -->
                
                <div class="form-group">
                    <label class="form-label">Dirección *</label>
                    <input type="text" name="direccion" id="edit_direccion" class="form-control" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Ciudad</label>
                        <input type="text" name="ciudad" id="edit_ciudad" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <input type="text" name="estado" id="edit_estado" class="form-control">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Código Postal</label>
                        <input type="text" name="codigo_postal" id="edit_codigo_postal" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" id="edit_telefono" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Responsable *</label>
                    <input type="text" name="responsable" id="edit_responsable" class="form-control" required>
                </div>
                
                <!-- <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Capacidad Total *</label>
                        <input type="number" step="0.01" name="capacidad_total" id="edit_capacidad_total" class="form-control" required>
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
                </div> -->
                
                <div class="form-group">
                    <label class="form-label">Estado de la Bodega</label>
                    <select name="estado_bodega" id="edit_estado_bodega" class="form-control">
                        <?php
                        // Sin filtro por empresa - mostrar todos los estados
                        $estados_bodega_edit_query = "SELECT valor FROM catalogos WHERE modulo='bodega' AND campo='estado_bodega' AND activo=1 ORDER BY orden, valor";
                        $estados_bodega_edit = $conn->query($estados_bodega_edit_query);
                        while ($est = $estados_bodega_edit->fetch_assoc()):
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
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-editar-bodega')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar Bodega</button>
            </div>
        </form>
    </div>
</div>

<!-- Form para eliminar -->
<form id="form-eliminar" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id_bodega" id="delete_id_bodega">
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

function editarBodega(bodega) {
    document.getElementById('edit_id_bodega').value = bodega.id_bodega;
    document.getElementById('edit_nombre').value = bodega.nombre || '';
    document.getElementById('edit_ubicacion').value = bodega.ubicacion || '';
    document.getElementById('edit_direccion').value = bodega.direccion || '';
    document.getElementById('edit_ciudad').value = bodega.ciudad || '';
    document.getElementById('edit_estado').value = bodega.estado || '';
    document.getElementById('edit_codigo_postal').value = bodega.codigo_postal || '';
    document.getElementById('edit_telefono').value = bodega.telefono || '';
    document.getElementById('edit_responsable').value = bodega.responsable || '';
    document.getElementById('edit_capacidad_total').value = bodega.capacidad_total || 0;
    document.getElementById('edit_unidad_medida').value = bodega.unidad_medida || 'm²';
    
    // Si el valor actual no existe en las opciones, agregarlo temporalmente
    const selectEstado = document.getElementById('edit_estado_bodega');
    if (selectEstado) {
        let optionExists = false;
        for (let option of selectEstado.options) {
            if (option.value === bodega.estado_bodega) {
                optionExists = true;
                break;
            }
        }
        if (!optionExists && bodega.estado_bodega) {
            const newOption = document.createElement('option');
            newOption.value = bodega.estado_bodega;
            newOption.text = bodega.estado_bodega + ' (Inactivo)';
            selectEstado.add(newOption);
        }
        selectEstado.value = bodega.estado_bodega || '';
    }
    
    document.getElementById('edit_notas').value = bodega.notas || '';
    
    // Abrir modal después de un pequeño delay para asegurar que los valores se establezcan
    setTimeout(function() {
        openModal('modal-editar-bodega');
    }, 50);
}

function confirmarEliminar(id) {
    if (confirm('¿Está seguro de que desea eliminar esta bodega? Esta acción no se puede deshacer.')) {
        document.getElementById('delete_id_bodega').value = id;
        document.getElementById('form-eliminar').submit();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>

