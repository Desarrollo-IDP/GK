<?php
require_once '../../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
checkAuth();
$pageTitle = 'Cartas de Entrega';
require_once '../../includes/header.php';

// Obtener empresa del usuario
$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa
$has_empresa_column = false;
$check_column = $conn->query("SHOW COLUMNS FROM cartas_entrega LIKE 'id_empresa'");
if ($check_column && $check_column->num_rows > 0) {
    $has_empresa_column = true;
}

// Filtros
$filter_estado = $_GET['estado'] ?? '';
$filter_fecha_desde = $_GET['fecha_desde'] ?? '';
$filter_fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Si no hay filtros de fecha, mostrar solo las cartas del día actual
$mostrar_solo_hoy = empty($filter_fecha_desde) && empty($filter_fecha_hasta);
$fecha_hoy = fechaHoyAlmacen();

// Obtener cartas de entrega
$cartas_query = "SELECT ce.*, p.nombre_producto, p.codigo_producto, a.nombre as almacen_nombre
                 FROM cartas_entrega ce
                 LEFT JOIN productos_almacen p ON ce.id_producto = p.id_producto
                 LEFT JOIN almacenes a ON p.id_almacen = a.id_almacen";
$cartas_params = [];
$cartas_types = '';
$where_added = false;

// Aplicar filtro de empresa si existe la columna y el usuario tiene empresa asignada
if ($has_empresa_column) {
    if ($user_empresa_id !== null) {
        // Usuario con empresa asignada: solo ver sus cartas
        $cartas_query .= " WHERE ce.id_empresa = ?";
        $cartas_params[] = $user_empresa_id;
        $cartas_types = 'i';
        $where_added = true;
    }
    // Si es admin (user_empresa_id === null), no agregar filtro (verá todo)
}

if (!$where_added) {
    $cartas_query .= " WHERE 1=1";
}

// Si no hay filtros de fecha, mostrar solo las cartas del día actual (CURDATE = misma zona que MySQL)
if ($mostrar_solo_hoy) {
    $cartas_query .= " AND DATE(ce.fecha_creacion) = CURDATE()";
}

// Aplicar filtros adicionales
if ($filter_estado) {
    $cartas_query .= " AND ce.estado_entrega = ?";
    $cartas_params[] = $filter_estado;
    $cartas_types .= 's';
}
if ($filter_fecha_desde) {
    $cartas_query .= " AND DATE(ce.fecha_creacion) >= ?";
    $cartas_params[] = $filter_fecha_desde;
    $cartas_types .= 's';
}
if ($filter_fecha_hasta) {
    $cartas_query .= " AND DATE(ce.fecha_creacion) <= ?";
    $cartas_params[] = $filter_fecha_hasta;
    $cartas_types .= 's';
}

$cartas_query .= " ORDER BY ce.fecha_creacion DESC";

// Ejecutar consulta con prepared statements si hay parámetros
if (!empty($cartas_params)) {
    $cartas_stmt = $conn->prepare($cartas_query);
    if ($cartas_stmt) {
        $cartas_stmt->bind_param($cartas_types, ...$cartas_params);
        $cartas_stmt->execute();
        $cartas = $cartas_stmt->get_result();
    } else {
        $error_message = 'Error en consulta: ' . $conn->error;
        $cartas = $conn->query("SELECT ce.*, p.nombre_producto, p.codigo_producto, a.nombre as almacen_nombre FROM cartas_entrega ce LEFT JOIN productos_almacen p ON ce.id_producto = p.id_producto LEFT JOIN almacenes a ON p.id_almacen = a.id_almacen ORDER BY ce.fecha_creacion DESC");
    }
} else {
    $cartas = $conn->query($cartas_query);
}

// Agrupar por número de carta
$cartas_agrupadas = [];
while ($carta = $cartas->fetch_assoc()) {
    $numero = $carta['numero_carta'];
    if (!isset($cartas_agrupadas[$numero])) {
        // Asegurar que el estado_entrega esté presente
        if (empty($carta['estado_entrega']) || trim($carta['estado_entrega']) === '') {
            $carta['estado_entrega'] = 'Pendiente';
        }
        $cartas_agrupadas[$numero] = [
            'info' => $carta,
            'productos' => []
        ];
    } else {
        // Si ya existe, actualizar el estado si el nuevo registro tiene un estado válido
        // Priorizar estados no vacíos sobre estados vacíos
        if (!empty($carta['estado_entrega']) && trim($carta['estado_entrega']) !== '') {
            $cartas_agrupadas[$numero]['info']['estado_entrega'] = $carta['estado_entrega'];
        }
        // Si el estado actual está vacío y el nuevo tiene un estado válido, actualizarlo
        if (empty($cartas_agrupadas[$numero]['info']['estado_entrega']) || trim($cartas_agrupadas[$numero]['info']['estado_entrega']) === '') {
            if (!empty($carta['estado_entrega']) && trim($carta['estado_entrega']) !== '') {
                $cartas_agrupadas[$numero]['info']['estado_entrega'] = $carta['estado_entrega'];
            } else {
                $cartas_agrupadas[$numero]['info']['estado_entrega'] = 'Pendiente';
            }
        }
    }
    $cartas_agrupadas[$numero]['productos'][] = $carta;
}
?>

<style>
    /* Estilos mejorados para cartas-entrega.php */
    .cartas-container {
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .page-header {
        background: #FFFFFF;
        padding: 1.5rem 2rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border-left: 4px solid #1E2A3B;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .page-header h1 {
        color: #1E2A3B;
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .page-header h1 i {
        font-size: 1.5rem;
        color: #1E2A3B;
    }
    
    .page-header .btn-success {
        background-color: #1E2A3B;
        color: #FFFFFF;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(30, 42, 59, 0.2);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .page-header .btn-success:hover {
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
        width: 100%;
    }
    
    .filters-card .form-control:focus {
        border-color: #1E2A3B;
        box-shadow: 0 0 0 3px rgba(30, 42, 59, 0.1);
        outline: none;
    }
    
    .filters-card .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        border: none;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .filters-card .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
    }
    
    .filters-card .btn-primary {
        background-color: #1E2A3B;
        color: #FFFFFF;
    }
    
    .filters-card .btn-primary:hover {
        background-color: #000000;
    }
    
    .filters-card .btn-secondary {
        background-color: #414D5C;
        color: #FFFFFF;
    }
    
    .filters-card .btn-secondary:hover {
        background-color: #1E2A3B;
    }
    
    .carta-item {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
        border: 1px solid #E5E7EB;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .carta-item:hover {
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        transform: translateY(-2px);
    }
    
    .carta-header {
        background: #F9FAFB;
        padding: 1.25rem 1.5rem;
        border-bottom: 2px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 4px solid #1E2A3B;
    }
    
    .carta-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: #1E2A3B;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .carta-header h3 i {
        color: #1E2A3B;
        font-size: 1.1rem;
    }
    
    .carta-header small {
        display: block;
        margin-top: 0.25rem;
        color: #6B7280;
        font-size: 0.875rem;
    }
    
    .estado-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .carta-body {
        padding: 1.5rem;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
        padding: 1.25rem;
        background: #F9FAFB;
        border-radius: 8px;
        border: 1px solid #E5E7EB;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .info-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #6B7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .info-value {
        font-size: 1rem;
        color: #1E2A3B;
        font-weight: 500;
    }
    
    .info-value small {
        display: block;
        margin-top: 0.25rem;
        color: #9CA3AF;
        font-weight: 400;
    }
    
    .productos-table {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #E5E7EB;
        margin-bottom: 1.5rem;
    }
    
    .productos-table table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .productos-table thead {
        background: #1E2A3B;
        border-bottom: 2px solid #000000;
    }
    
    .productos-table th {
        padding: 1rem;
        text-align: left;
        color: white;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .productos-table tbody tr {
        border-bottom: 1px solid #E5E7EB;
        transition: background-color 0.2s ease;
    }
    
    .productos-table tbody tr:hover {
        background-color: #F9FAFB;
    }
    
    .productos-table tbody tr:last-child {
        border-bottom: none;
    }
    
    .productos-table td {
        padding: 1rem;
        color: #374151;
    }
    
    .productos-table td code {
        background: #F3F4F6;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.875rem;
        color: #1E2A3B;
        font-weight: 600;
    }
    
    .observaciones-box {
        margin-top: 1.5rem;
        padding: 1rem 1.25rem;
        background: #FEF3C7;
        border-left: 4px solid #F59E0B;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(245, 158, 11, 0.1);
    }
    
    .observaciones-box strong {
        color: #92400E;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: block;
        margin-bottom: 0.5rem;
    }
    
    .observaciones-box p {
        color: #78350F;
        margin: 0;
        line-height: 1.6;
    }
    
    .acciones-buttons {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 2px solid #E5E7EB;
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .btn-action {
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border: none;
        cursor: pointer;
        text-decoration: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    .btn-action.btn-info {
        background-color: #3b82f6;
        color: #FFFFFF;
    }
    
    .btn-action.btn-info:hover {
        background-color: #2563eb;
    }
    
    .btn-action.btn-success {
        background-color: #10b981;
        color: #FFFFFF;
    }
    
    .btn-action.btn-success:hover {
        background-color: #059669;
    }
    
    .btn-action.btn-danger {
        background-color: #ef4444;
        color: #FFFFFF;
    }
    
    .btn-action.btn-danger:hover {
        background-color: #dc2626;
    }
    
    .btn-action.btn-secondary {
        background-color: #414D5C;
        color: #FFFFFF;
    }
    
    .btn-action.btn-secondary:hover {
        background-color: #1E2A3B;
    }
    
    .empty-state {
        background: white;
        border-radius: 12px;
        padding: 4rem 2rem;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border: 1px solid #E5E7EB;
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #D1D5DB;
        margin-bottom: 1rem;
    }
    
    .empty-state h3 {
        color: #414D5C;
        margin-bottom: 0.5rem;
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .empty-state p {
        color: #9CA3AF;
        margin-bottom: 1.5rem;
        font-size: 1rem;
    }
    
    .empty-state .btn {
        padding: 0.875rem 1.75rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .empty-state .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    .empty-state .btn-primary {
        background-color: #1E2A3B;
        color: #FFFFFF;
    }
    
    .empty-state .btn-primary:hover {
        background-color: #000000;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        
        .page-header .btn-success {
            width: 100%;
            justify-content: center;
        }
        
        .filters-card > div {
            grid-template-columns: 1fr !important;
        }
        
        .info-grid {
            grid-template-columns: 1fr !important;
        }
        
        .acciones-buttons {
            flex-direction: column;
        }
        
        .acciones-buttons .btn-action {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="container cartas-container">
    <?php if (isset($error_message) && $error_message): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="page-header">
        <h1>
            <i class="fas fa-file-contract"></i> Cartas de Entrega
        </h1>
        <a href="./generar-carta.php" class="btn-success">
            <i class="fas fa-plus"></i> Nueva Carta de Entrega
        </a>
    </div>

    <!-- Filtros -->
    <form method="get" class="filters-card">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group mb-0">
                <label class="form-label" style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">
                    <i class="fas fa-filter" style="margin-right: 0.5rem;"></i>Estado
                </label>
                <select name="estado" class="form-control">
                    <option value="">Todos los estados</option>
                    <option value="Pendiente" <?php echo $filter_estado === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="Entregado" <?php echo $filter_estado === 'Entregado' ? 'selected' : ''; ?>>Entregado</option>
                    <option value="Devuelto" <?php echo $filter_estado === 'Devuelto' ? 'selected' : ''; ?>>Devuelto</option>
                    <option value="Cancelado" <?php echo $filter_estado === 'Cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    <option value="Vencido" <?php echo $filter_estado === 'Vencido' ? 'selected' : ''; ?>>Vencido</option>
                </select>
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label" style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">
                    <i class="fas fa-calendar-alt" style="margin-right: 0.5rem;"></i>Desde
                </label>
                <input type="date" name="fecha_desde" class="form-control" value="<?php echo $filter_fecha_desde; ?>">
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label" style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">
                    <i class="fas fa-calendar-check" style="margin-right: 0.5rem;"></i>Hasta
                </label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?php echo $filter_fecha_hasta; ?>">
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label" style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">
                    &nbsp;
                </label>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <?php if ($filter_fecha_desde || $filter_fecha_hasta || $filter_estado): ?>
                        <a href="./cartas-entrega.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>

    <!-- Lista de Cartas -->
    <?php if (count($cartas_agrupadas) > 0): ?>
        <?php foreach ($cartas_agrupadas as $numero_carta => $data): ?>
            <?php 
            $info = $data['info'];
            $estado_actual = !empty($info['estado_entrega']) ? $info['estado_entrega'] : 'Pendiente';
            ?>
            <div class="carta-item">
                <div class="carta-header">
                    <div>
                        <h3>
                            <i class="fas fa-file-contract"></i> <?php echo htmlspecialchars($numero_carta); ?>
                        </h3>
                        <small>
                            <i class="fas fa-clock" style="margin-right: 0.25rem;"></i>
                            Creado: <?php echo formatearFechaAlmacen($info['fecha_creacion'], 'd/m/Y H:i'); ?>
                        </small>
                    </div>
                    <div>
                        <span class="estado-badge badge-<?php 
                            if ($estado_actual == 'Entregado') {
                                echo 'success';
                            } elseif ($estado_actual == 'Pendiente') {
                                echo 'warning';
                            } elseif ($estado_actual == 'Cancelado') {
                                echo 'danger';
                            } elseif ($estado_actual == 'Devuelto') {
                                echo 'info';
                            } else {
                                echo 'secondary';
                            }
                        ?>">
                            <?php echo htmlspecialchars($estado_actual); ?>
                        </span>
                    </div>
                </div>
                
                <div class="carta-body">
                    <!-- Información del Receptor -->
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-user" style="margin-right: 0.25rem;"></i>Receptor
                            </span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($info['nombre_receptor']); ?>
                                <small><i class="fas fa-id-card" style="margin-right: 0.25rem;"></i>ID: <?php echo htmlspecialchars($info['identificacion_receptor']); ?></small>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-building" style="margin-right: 0.25rem;"></i>Empresa
                            </span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($info['empresa_receptor']); ?>
                                <?php if ($info['area_receptor']): ?>
                                    <small><i class="fas fa-sitemap" style="margin-right: 0.25rem;"></i><?php echo htmlspecialchars($info['area_receptor']); ?></small>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-calendar-day" style="margin-right: 0.25rem;"></i>Fecha de Entrega
                            </span>
                            <span class="info-value">
                                <?php echo formatearFechaAlmacen($info['fecha_entrega']); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-tag" style="margin-right: 0.25rem;"></i>Tipo
                            </span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($info['tipo_entrega']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Productos -->
                    <div class="productos-table">
                        <table>
                            <thead>
                                <tr>
                                    <th><i class="fas fa-barcode" style="margin-right: 0.5rem;"></i>Código</th>
                                    <th><i class="fas fa-box" style="margin-right: 0.5rem;"></i>Producto</th>
                                    <th class="hide-mobile"><i class="fas fa-warehouse" style="margin-right: 0.5rem;"></i>Almacén</th>
                                    <th><i class="fas fa-hashtag" style="margin-right: 0.5rem;"></i>Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['productos'] as $producto): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($producto['codigo_producto']); ?></code></td>
                                    <td><?php echo htmlspecialchars($producto['nombre_producto']); ?></td>
                                    <td class="hide-mobile"><?php echo htmlspecialchars($producto['almacen_nombre']); ?></td>
                                    <td><strong style="color: #1E2A3B; font-size: 1.1rem;"><?php echo $producto['cantidad_entregada']; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($info['observaciones']): ?>
                    <div class="observaciones-box">
                        <strong><i class="fas fa-sticky-note" style="margin-right: 0.5rem;"></i>Observaciones</strong>
                        <p><?php echo nl2br(htmlspecialchars($info['observaciones'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Acciones -->
                    <div class="acciones-buttons">
                        <a href="./ver-carta-pdf.php?numero=<?php echo urlencode($numero_carta); ?>" target="_blank" class="btn-action btn-info">
                            <i class="fas fa-file-pdf"></i> Ver PDF
                        </a>
                        
                        <?php if ($estado_actual == 'Pendiente'): ?>
                        <button onclick="cambiarEstado('<?php echo $numero_carta; ?>', 'Entregado')" class="btn-action btn-success">
                            <i class="fas fa-check-circle"></i> Marcar como Entregado
                        </button>
                        <button onclick="cambiarEstado('<?php echo $numero_carta; ?>', 'Cancelado')" class="btn-action btn-danger">
                            <i class="fas fa-times-circle"></i> Cancelar
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($estado_actual == 'Entregado'): ?>
                        <?php if ($info['tipo_entrega'] != 'Definitiva'): ?>
                        <button onclick="cambiarEstado('<?php echo $numero_carta; ?>', 'Devuelto')" class="btn-action btn-secondary">
                            <i class="fas fa-undo-alt"></i> Marcar como Devuelto
                        </button>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($estado_actual == 'Devuelto'): ?>
                        <button onclick="cambiarEstado('<?php echo $numero_carta; ?>', 'Cancelado')" class="btn-action btn-danger">
                            <i class="fas fa-times-circle"></i> Cancelar
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No se encontraron cartas de entrega</h3>
            <p>Comienza generando una nueva carta de entrega para registrar productos entregados</p>
            <a href="./generar-carta.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nueva Carta de Entrega
            </a>
        </div>
    <?php endif; ?>
</div>

<script src="../../js/main.js"></script>
<script>
function cambiarEstado(numero_carta, nuevo_estado) {
    let mensaje = '¿Está seguro de cambiar el estado de esta carta?';
    if (nuevo_estado === 'Cancelado') {
        mensaje = '¿Está seguro de cancelar esta carta? Los productos serán devueltos al inventario.';
    }
    
    if (confirm(mensaje)) {
        const formData = new FormData();
        formData.append('numero_carta', numero_carta);
        formData.append('nuevo_estado', nuevo_estado);
        formData.append('action', 'cambiar_estado');
        
        fetch('./acciones-carta.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message || 'Estado actualizado correctamente', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('Error al actualizar: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showAlert('Error en la conexión', 'error');
        });
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>

