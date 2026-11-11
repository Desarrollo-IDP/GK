<?php
require_once '../../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
checkAuth();
$pageTitle = 'Inicio - Gestión de Almacén';
require_once '../../includes/header.php';

$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existen columnas id_empresa
$check_column_bodegas = $conn->query("SHOW COLUMNS FROM bodegas LIKE 'id_empresa'");
$has_empresa_column_bodegas = $check_column_bodegas && $check_column_bodegas->num_rows > 0;

$check_column_almacenes = $conn->query("SHOW COLUMNS FROM almacenes LIKE 'id_empresa'");
$has_empresa_column_almacenes = $check_column_almacenes && $check_column_almacenes->num_rows > 0;

$check_column_productos = $conn->query("SHOW COLUMNS FROM productos_almacen LIKE 'id_empresa'");
$has_empresa_column_productos = $check_column_productos && $check_column_productos->num_rows > 0;

$check_column_cartas = $conn->query("SHOW COLUMNS FROM cartas_entrega LIKE 'id_empresa'");
$has_empresa_column_cartas = $check_column_cartas && $check_column_cartas->num_rows > 0;

$check_column_historial = $conn->query("SHOW COLUMNS FROM historial_movimientos LIKE 'id_empresa'");
$has_empresa_column_historial = $check_column_historial && $check_column_historial->num_rows > 0;

// Obtener estadísticas con filtro por empresa
$stats = [];

// Construir filtro WHERE para empresa
$empresa_filter_bodegas = '';
$empresa_filter_almacenes = '';
$empresa_filter_productos = '';
$empresa_filter_cartas = '';
$empresa_filter_historial = '';

if ($has_empresa_column_bodegas && $user_empresa_id !== null) {
    $empresa_filter_bodegas = " AND id_empresa = " . intval($user_empresa_id);
}
if ($has_empresa_column_almacenes && $user_empresa_id !== null) {
    $empresa_filter_almacenes = " AND id_empresa = " . intval($user_empresa_id);
}
if ($has_empresa_column_productos && $user_empresa_id !== null) {
    $empresa_filter_productos = " AND id_empresa = " . intval($user_empresa_id);
}
if ($has_empresa_column_cartas && $user_empresa_id !== null) {
    $empresa_filter_cartas = " AND id_empresa = " . intval($user_empresa_id);
}
if ($has_empresa_column_historial && $user_empresa_id !== null) {
    $empresa_filter_historial = " AND id_empresa = " . intval($user_empresa_id);
}

// Total de bodegas
$result = $conn->query("SELECT COUNT(*) as total FROM bodegas WHERE estado_bodega = 'Activa'" . $empresa_filter_bodegas);
$stats['bodegas'] = $result->fetch_assoc()['total'] ?? 0;

// Total de almacenes
$result = $conn->query("SELECT COUNT(*) as total FROM almacenes WHERE estado_almacen IN ('Disponible', 'Ocupado')" . $empresa_filter_almacenes);
$stats['almacenes'] = $result->fetch_assoc()['total'] ?? 0;

// Total de productos
$result = $conn->query("SELECT COUNT(*) as total FROM productos_almacen WHERE estado_producto = 'Disponible'" . $empresa_filter_productos);
$stats['productos'] = $result->fetch_assoc()['total'] ?? 0;

// Total de cartas de entrega pendientes
$result = $conn->query("SELECT COUNT(*) as total FROM cartas_entrega WHERE estado_entrega = 'Pendiente'" . $empresa_filter_cartas);
$stats['cartas_pendientes'] = $result->fetch_assoc()['total'] ?? 0;

// Productos con stock bajo
$result = $conn->query("SELECT COUNT(*) as total FROM productos_almacen WHERE cantidad <= stock_minimo AND stock_minimo > 0" . $empresa_filter_productos);
$stats['stock_bajo'] = $result->fetch_assoc()['total'] ?? 0;

// Valor total del inventario
// Calcular siempre cantidad * precio_unitario en tiempo real para asegurar precisión
// Excluir productos con precio_unitario NULL, 0 o cantidad 0
$valor_total_query = "SELECT SUM(cantidad * precio_unitario) as total 
FROM productos_almacen 
WHERE estado_producto = 'Disponible' 
AND cantidad > 0 
AND precio_unitario IS NOT NULL 
AND precio_unitario > 0" . $empresa_filter_productos;
$result = $conn->query($valor_total_query);
$valor_result = $result->fetch_assoc();
$stats['valor_total'] = ($valor_result && $valor_result['total'] !== null) ? floatval($valor_result['total']) : 0;

// Actividad reciente con filtro por empresa
$recent_movements_query = "
    SELECT hm.*, pa.nombre_producto, pa.codigo_producto
    FROM historial_movimientos hm
    LEFT JOIN productos_almacen pa ON hm.id_producto = pa.id_producto
    WHERE 1=1";
if ($has_empresa_column_historial && $user_empresa_id !== null) {
    $recent_movements_query .= " AND hm.id_empresa = " . intval($user_empresa_id);
} elseif ($has_empresa_column_productos && $user_empresa_id !== null) {
    // Si no hay columna en historial pero sí en productos, filtrar por productos
    $recent_movements_query .= " AND pa.id_empresa = " . intval($user_empresa_id);
}
$recent_movements_query .= "
    ORDER BY hm.fecha_movimiento DESC
    LIMIT 10
";
$recent_movements = $conn->query($recent_movements_query);

// Productos más solicitados con filtro por empresa
$top_products_query = "
    SELECT pa.nombre_producto, pa.codigo_producto, pa.cantidad, a.nombre as almacen_nombre
    FROM productos_almacen pa
    LEFT JOIN almacenes a ON pa.id_almacen = a.id_almacen
    WHERE pa.estado_producto = 'Disponible'";
if ($has_empresa_column_productos && $user_empresa_id !== null) {
    $top_products_query .= " AND pa.id_empresa = " . intval($user_empresa_id);
}
$top_products_query .= "
    ORDER BY pa.cantidad DESC
    LIMIT 5
";
$top_products = $conn->query($top_products_query);

// Almacenes por capacidad con filtro por empresa
$almacenes_capacidad_query = "
    SELECT a.*, b.nombre as bodega_nombre,
           ROUND((a.capacidad_actual / a.capacidad) * 100, 2) as porcentaje_ocupacion
    FROM almacenes a
    LEFT JOIN bodegas b ON a.id_bodega = b.id_bodega
    WHERE a.estado_almacen != 'Mantenimiento'";
if ($has_empresa_column_almacenes && $user_empresa_id !== null) {
    $almacenes_capacidad_query .= " AND a.id_empresa = " . intval($user_empresa_id);
}
$almacenes_capacidad_query .= "
    ORDER BY porcentaje_ocupacion DESC
    LIMIT 5
";
$almacenes_capacidad = $conn->query($almacenes_capacidad_query);
?>

<style>
    /* Estilos mejorados para inicio.php */
    .inicio-header {
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
    
    .inicio-header h1 {
        color: #1E2A3B;
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
    }
    
    .inicio-header .date-info {
        color: #6B7280;
        font-size: 0.9375rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .stat-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 1.5rem;
        border: 1px solid #E5E7EB;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    .stat-card-icon {
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        color: #FFFFFF;
    }
    
    .stat-card-icon.primary {
        background-color: #1E2A3B;
    }
    
    .stat-card-icon.success {
        background-color: #10b981;
    }
    
    .stat-card-icon.warning {
        background-color: #f59e0b;
    }
    
    .stat-card-icon.danger {
        background-color: #ef4444;
    }
    
    .stat-card-title {
        font-size: 0.875rem;
        color: #6B7280;
        font-weight: 500;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-card-value {
        font-size: 2rem;
        font-weight: 700;
        color: #1E2A3B;
        margin-bottom: 0.75rem;
    }
    
    .stat-card-description {
        font-size: 0.875rem;
        color: #6B7280;
    }
    
    .stat-card-description a {
        color: #1E2A3B;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .stat-card-description a:hover {
        color: #000000;
    }
    
    .dashboard-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        border: 1px solid #E5E7EB;
        overflow: hidden;
    }
    
    .dashboard-card-header {
        background-color: #1E2A3B;
        color: #FFFFFF;
        padding: 1.25rem 1.5rem;
        border-bottom: 2px solid #000000;
    }
    
    .dashboard-card-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .dashboard-card-header h3 i {
        font-size: 1.1rem;
    }
    
    .dashboard-card-body {
        padding: 0;
    }
    
    .table-container {
        background: #FFFFFF;
        border-radius: 0;
        overflow: hidden;
        border: none;
    }
    
    .table-container table {
        width: 100%;
        border-collapse: collapse;
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
        text-align: left;
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
    
    .table-container table tbody td code {
        background: #F3F4F6;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.875rem;
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
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .badge-success {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .badge-info {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .badge-warning {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .btn {
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
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
    }
    
    .btn-primary {
        background-color: #1E2A3B;
        color: #FFFFFF;
    }
    
    .btn-primary:hover {
        background-color: #000000;
    }
    
    .btn-success {
        background-color: #10b981;
        color: #FFFFFF;
    }
    
    .btn-success:hover {
        background-color: #059669;
    }
    
    .btn-info {
        background-color: #3b82f6;
        color: #FFFFFF;
    }
    
    .btn-info:hover {
        background-color: #2563eb;
    }
    
    .btn-secondary {
        background-color: #414D5C;
        color: #FFFFFF;
    }
    
    .btn-secondary:hover {
        background-color: #1E2A3B;
    }
    
    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    .occupation-bar {
        display: flex;
        align-items: center;
        gap: 0.625rem;
    }
    
    .occupation-bar-bg {
        flex: 1;
        background: #D1D5DB;
        height: 8px;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .occupation-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s ease, background-color 0.3s ease;
    }
    
    .occupation-bar-fill.low {
        background-color: #10b981;
    }
    
    .occupation-bar-fill.medium {
        background-color: #f59e0b;
    }
    
    .occupation-bar-fill.high {
        background-color: #ef4444;
    }
    
    .quick-access-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        padding: 1.25rem;
    }
    
    @media (max-width: 768px) {
        .inicio-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        
        .quick-access-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container">
    <div class="inicio-header">
        <h1>Dashboard - Gestión de Almacén</h1>
        <div class="date-info">
            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i'); ?>
        </div>
    </div>

    <!-- Tarjetas de Estadísticas -->
    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-card-icon primary">
                <i class="fas fa-warehouse"></i>
            </div>
            <div class="stat-card-title">Bodegas Activas</div>
            <div class="stat-card-value"><?php echo $stats['bodegas']; ?></div>
            <div class="stat-card-description">
                <a href="../bodegas/bodegas.php" style="color: #1E2A3B;">Ver todas <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-icon success">
                <i class="fas fa-boxes"></i>
            </div>
            <div class="stat-card-title">Almacenes Operativos</div>
            <div class="stat-card-value"><?php echo $stats['almacenes']; ?></div>
            <div class="stat-card-description">
                <a href="../almacenes/almacenes.php" style="color: #1E2A3B;">Gestionar <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-icon primary">
                <i class="fas fa-box-open"></i>
            </div>
            <div class="stat-card-title">Productos Disponibles</div>
            <div class="stat-card-value"><?php echo $stats['productos']; ?></div>
            <div class="stat-card-description">
                <a href="../productos/productos.php" style="color: #1E2A3B;">Ver inventario <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-icon warning">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-card-title">Cartas Pendientes</div>
            <div class="stat-card-value"><?php echo $stats['cartas_pendientes']; ?></div>
            <div class="stat-card-description">
                <a href="../cartas/cartas-entrega.php" style="color: #1E2A3B;">Revisar <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <?php if ($stats['stock_bajo'] > 0): ?>
        <div class="stat-card">
            <div class="stat-card-icon danger">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-card-title">Productos Stock Bajo</div>
            <div class="stat-card-value"><?php echo $stats['stock_bajo']; ?></div>
            <div class="stat-card-description">
                <a href="../productos/productos.php?filter=stock_bajo" style="color: #991b1b;">Requiere atención</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="stat-card">
            <div class="stat-card-icon success">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-card-title">Valor Total Inventario</div>
            <div class="stat-card-value">$<?php echo number_format($stats['valor_total'] ?? 0, 2); ?></div>
            <div class="stat-card-description">MXN</div>
        </div>
    </div>

    <!-- Fila de contenido -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
        <!-- Actividad Reciente -->
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h3><i class="fas fa-history"></i> Actividad Reciente</h3>
            </div>
            <div class="dashboard-card-body">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th class="hide-mobile">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_movements->num_rows > 0): ?>
                                <?php while ($mov = $recent_movements->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $iconMap = [
                                            'Entrada' => 'arrow-down',
                                            'Salida' => 'arrow-up',
                                            'Transferencia' => 'exchange-alt',
                                            'Entrega' => 'hand-holding',
                                            'Devolución' => 'undo'
                                        ];
                                        $icon = $iconMap[$mov['tipo_movimiento']] ?? 'circle';
                                        ?>
                                        <span class="badge badge-<?php echo $mov['tipo_movimiento'] == 'Entrada' ? 'success' : 'info'; ?>">
                                            <i class="fas fa-<?php echo $icon; ?>"></i> <?php echo $mov['tipo_movimiento']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($mov['nombre_producto'] ?? 'N/A'); ?></td>
                                    <td><?php echo $mov['cantidad']; ?></td>
                                    <td class="hide-mobile"><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No hay actividad reciente</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="padding: 1rem 1.5rem; text-align: right; border-top: 1px solid #E5E7EB;">
                    <a href="../historial/historial.php" class="btn btn-sm btn-primary">Ver todo el historial <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>

        <!-- Productos Top -->
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h3><i class="fas fa-chart-line"></i> Productos con Mayor Stock</h3>
            </div>
            <div class="dashboard-card-body">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th class="hide-mobile">Almacén</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top_products->num_rows > 0): ?>
                                <?php while ($product = $top_products->fetch_assoc()): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($product['codigo_producto']); ?></code></td>
                                    <td><?php echo htmlspecialchars($product['nombre_producto']); ?></td>
                                    <td><strong><?php echo $product['cantidad']; ?></strong></td>
                                    <td class="hide-mobile"><?php echo htmlspecialchars($product['almacen_nombre'] ?? 'N/A'); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No hay productos registrados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Almacenes por Capacidad -->
    <div class="dashboard-card" style="margin-top: 1.5rem;">
        <div class="dashboard-card-header">
            <h3><i class="fas fa-chart-pie"></i> Estado de Ocupación de Almacenes</h3>
        </div>
        <div class="dashboard-card-body">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Almacén</th>
                            <th class="hide-mobile">Bodega</th>
                            <th>Capacidad</th>
                            <th>Ocupación</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($almacenes_capacidad->num_rows > 0): ?>
                            <?php while ($alm = $almacenes_capacidad->fetch_assoc()): 
                                $ocupacion_class = $alm['porcentaje_ocupacion'] > 80 ? 'high' : ($alm['porcentaje_ocupacion'] > 50 ? 'medium' : 'low');
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($alm['codigo']); ?></code></td>
                                <td><?php echo htmlspecialchars($alm['nombre']); ?></td>
                                <td class="hide-mobile"><?php echo htmlspecialchars($alm['bodega_nombre']); ?></td>
                                <td><?php echo $alm['capacidad']; ?> <?php echo $alm['unidad_medida']; ?></td>
                                <td>
                                    <div class="occupation-bar">
                                        <div class="occupation-bar-bg">
                                            <div class="occupation-bar-fill <?php echo $ocupacion_class; ?>" style="width: <?php echo min($alm['porcentaje_ocupacion'], 100); ?>%;"></div>
                                        </div>
                                        <span><?php echo $alm['porcentaje_ocupacion']; ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $alm['estado_almacen'] == 'Disponible' ? 'success' : 'warning'; ?>">
                                        <?php echo $alm['estado_almacen']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No hay almacenes registrados</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Accesos Rápidos -->
    <div class="dashboard-card" style="margin-top: 1.5rem;">
        <div class="dashboard-card-header">
            <h3><i class="fas fa-bolt"></i> Accesos Rápidos</h3>
        </div>
        <div class="dashboard-card-body">
            <div class="quick-access-grid">
                <a href="../bodegas/bodegas.php?action=new" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva Bodega
                </a>
                <a href="../almacenes/almacenes.php?action=new" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Almacén
                </a>
                <a href="../productos/productos.php?action=new" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Producto
                </a>
                <a href="../cartas/generar-carta.php" class="btn btn-success">
                    <i class="fas fa-file-export"></i> Generar Carta de Entrega
                </a>
                <a href="../reportes/reportes.php" class="btn btn-info">
                    <i class="fas fa-chart-bar"></i> Ver Reportes
                </a>
                <a href="../historial/historial.php" class="btn btn-secondary">
                    <i class="fas fa-history"></i> Historial Completo
                </a>
            </div>
        </div>
    </div>
</div>

<script src="../../js/main.js"></script>

<?php require_once '../../includes/footer.php'; ?>

