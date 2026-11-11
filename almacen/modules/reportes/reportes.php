<?php
require_once '../../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
checkAuth();
$pageTitle = 'Reportes y Estadísticas';
require_once '../../includes/header.php';

// Obtener empresa del usuario
$user_empresa_id = getCurrentUserEmpresaId();

// Verificar columnas id_empresa
$has_historial_column = false;
$has_productos_column = false;
$has_almacenes_column = false;
$has_cartas_column = false;

$check_historial = $conn->query("SHOW COLUMNS FROM historial_movimientos LIKE 'id_empresa'");
if ($check_historial && $check_historial->num_rows > 0) {
    $has_historial_column = true;
}

$check_productos = $conn->query("SHOW COLUMNS FROM productos_almacen LIKE 'id_empresa'");
if ($check_productos && $check_productos->num_rows > 0) {
    $has_productos_column = true;
}

$check_almacenes = $conn->query("SHOW COLUMNS FROM almacenes LIKE 'id_empresa'");
if ($check_almacenes && $check_almacenes->num_rows > 0) {
    $has_almacenes_column = true;
}

$check_cartas = $conn->query("SHOW COLUMNS FROM cartas_entrega LIKE 'id_empresa'");
if ($check_cartas && $check_cartas->num_rows > 0) {
    $has_cartas_column = true;
}

// Obtener estadísticas generales
$stats = [];

// Total de movimientos por tipo
$stats['movimientos'] = [];
$movimientos_query = "SELECT tipo_movimiento, COUNT(*) as total FROM historial_movimientos";
if ($has_historial_column && $user_empresa_id !== null) {
    $movimientos_query .= " WHERE id_empresa = " . intval($user_empresa_id);
}
$movimientos_query .= " GROUP BY tipo_movimiento";
$result = $conn->query($movimientos_query);
while ($row = $result->fetch_assoc()) {
    $stats['movimientos'][$row['tipo_movimiento']] = $row['total'];
}

// Productos más movidos
$productos_movidos_query = "
    SELECT p.nombre_producto, p.codigo_producto, COUNT(*) as total_movimientos
    FROM historial_movimientos hm
    LEFT JOIN productos_almacen p ON hm.id_producto = p.id_producto
    WHERE p.id_producto IS NOT NULL";
if ($has_historial_column && $user_empresa_id !== null) {
    $productos_movidos_query .= " AND hm.id_empresa = " . intval($user_empresa_id);
}
$productos_movidos_query .= "
    GROUP BY p.id_producto
    ORDER BY total_movimientos DESC
    LIMIT 10";
$productos_movidos = $conn->query($productos_movidos_query);

// Productos con stock bajo
$stock_bajo_query = "
    SELECT p.*, a.nombre as almacen_nombre
    FROM productos_almacen p
    LEFT JOIN almacenes a ON p.id_almacen = a.id_almacen
    WHERE p.cantidad <= p.stock_minimo AND p.stock_minimo > 0";
if ($has_productos_column && $user_empresa_id !== null) {
    $stock_bajo_query .= " AND p.id_empresa = " . intval($user_empresa_id);
}
$stock_bajo_query .= " ORDER BY p.cantidad ASC";
$stock_bajo = $conn->query($stock_bajo_query);

// Valor total por almacén
$valor_almacenes_query = "
    SELECT a.nombre, a.codigo, SUM(p.valor_total) as valor_total
    FROM almacenes a
    LEFT JOIN productos_almacen p ON a.id_almacen = p.id_almacen";
if ($has_almacenes_column && $user_empresa_id !== null) {
    $valor_almacenes_query .= " WHERE a.id_empresa = " . intval($user_empresa_id);
}
if ($has_productos_column && $user_empresa_id !== null) {
    $valor_almacenes_query .= ($has_almacenes_column && $user_empresa_id !== null ? " AND" : " WHERE") . " p.id_empresa = " . intval($user_empresa_id);
}
$valor_almacenes_query .= "
    GROUP BY a.id_almacen
    ORDER BY valor_total DESC";
$valor_almacenes = $conn->query($valor_almacenes_query);

// Cartas de entrega de los últimos 30 días (fecha calendario, zona horaria del almacén)
$cartas_mes_query = "
    SELECT COUNT(DISTINCT numero_carta) as total
    FROM cartas_entrega
    WHERE fecha_entrega >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      AND fecha_entrega <= CURDATE()";
if ($has_cartas_column && $user_empresa_id !== null) {
    $cartas_mes_query .= " AND id_empresa = " . intval($user_empresa_id);
}
$cartas_mes = $conn->query($cartas_mes_query)->fetch_assoc()['total'];
?>

<style>
    /* Estilos mejorados para reportes.php */
    .reportes-header {
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
    
    .reportes-header h1 {
        color: #1E2A3B;
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .reportes-header h1 i {
        color: #1E2A3B;
        font-size: 1.5rem;
    }
    
    .reportes-header .btn,
    .reportes-header .btn-primary,
    .reportes-header .btn-secondary {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border: none;
        cursor: pointer;
    }
    
    .reportes-header .btn-secondary {
        background-color: #414D5C;
        color: #FFFFFF;
    }
    
    .reportes-header .btn-primary {
        background-color: #1E2A3B;
        color: #FFFFFF;
    }
    
    .reportes-header .btn-secondary:hover,
    .reportes-header .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }
    
    .stats-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 1.5rem;
        border: 1px solid #E5E7EB;
        overflow: hidden;
    }
    
    .stats-card-header {
        background-color: #1E2A3B;
        color: #FFFFFF;
        padding: 1.25rem 1.5rem;
        border-bottom: 2px solid #000000;
    }
    
    .stats-card-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .stats-card-header h3 i {
        font-size: 1.1rem;
    }
    
    .stats-card-body {
        padding: 2rem;
    }
    
    .stat-item {
        text-align: center;
        padding: 1.5rem;
        background: #F9FAFB;
        border-radius: 8px;
        border: 1px solid #E5E7EB;
        transition: all 0.3s ease;
    }
    
    .stat-item:hover {
        background: #F3F4F6;
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #1E2A3B;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        font-size: 0.875rem;
        color: #6B7280;
        font-weight: 500;
    }
    
    .table-container {
        background: #FFFFFF;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #E5E7EB;
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
    
    .btn-secondary {
        background-color: #414D5C;
        color: #FFFFFF;
    }
    
    .btn-secondary:hover {
        background-color: #1E2A3B;
    }
    
    .btn-warning {
        background-color: #f59e0b;
        color: #FFFFFF;
    }
    
    .btn-warning:hover {
        background-color: #d97706;
    }
    
    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    .stock-bajo-header {
        background-color: #ef4444 !important;
    }
    
    .stock-bajo-row {
        background-color: #FEE2E2 !important;
    }
    
    .stock-bajo-row:hover {
        background-color: #FECACA !important;
    }
    
    .stock-bajo-value {
        color: #dc2626;
        font-weight: 700;
    }
    
    .carta-resumen {
        text-align: center;
        padding: 2rem;
    }
    
    .carta-resumen-value {
        font-size: 3rem;
        font-weight: 700;
        color: #1E2A3B;
        margin-bottom: 0.5rem;
    }
    
    .carta-resumen-label {
        font-size: 1.125rem;
        color: #6B7280;
        margin-bottom: 1.5rem;
    }
    
    @media (max-width: 768px) {
        .reportes-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        
        .reportes-header .btn-secondary {
            width: 100%;
            justify-content: center;
        }
        
        .stats-grid {
            grid-template-columns: 1fr !important;
        }
        
        .stats-card-body > div {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }
</style>

<div class="container">
    <div class="reportes-header">
        <div>
            <h1>
                <i class="fas fa-chart-bar"></i> Reportes y Estadísticas
            </h1>
            <p class="text-muted mb-0" style="font-size: 0.9rem; margin-top: 0.35rem;">
                <i class="fas fa-clock"></i> Consulta al: <?php echo htmlspecialchars(fechaHoraActualAlmacen()); ?>
                <span class="text-muted">(<?php echo htmlspecialchars(ALMACEN_TIMEZONE); ?>)</span>
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <!--<a href="./historial.php" class="btn btn-secondary">
                <i class="fas fa-history"></i> Ver Historial
            </a>-->
            <a href="./reportes_pdf.php" target="_blank" class="btn btn-primary">
                <i class="fas fa-file-pdf"></i> Descargar reporte PDF
            </a>
        </div>
    </div>

    <!-- Estadísticas de Movimientos -->
    <div class="stats-card">
        <div class="stats-card-header">
            <h3><i class="fas fa-exchange-alt"></i> Movimientos por Tipo</h3>
        </div>
        <div class="stats-card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <?php 
                $tipos_movimiento = ['Entrada', 'Salida', 'Transferencia', 'Ajuste', 'Entrega', 'Devolución'];
                foreach ($tipos_movimiento as $tipo):
                    $total = $stats['movimientos'][$tipo] ?? 0;
                ?>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $total; ?></div>
                    <div class="stat-label"><?php echo $tipo; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Productos más Movidos y Valor por Almacén -->
    <div class="stats-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- Productos Más Movidos -->
        <div class="stats-card">
            <div class="stats-card-header">
                <h3><i class="fas fa-fire"></i> Productos Más Movidos</h3>
            </div>
            <div class="stats-card-body" style="padding: 0;">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Movimientos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($productos_movidos->num_rows > 0): ?>
                                <?php while ($prod = $productos_movidos->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars($prod['codigo_producto']); ?></code><br>
                                        <small><?php echo htmlspecialchars($prod['nombre_producto']); ?></small>
                                    </td>
                                    <td><strong><?php echo $prod['total_movimientos']; ?></strong></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">Sin datos</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Valor por Almacén -->
        <div class="stats-card">
            <div class="stats-card-header">
                <h3><i class="fas fa-dollar-sign"></i> Valor Total por Almacén</h3>
            </div>
            <div class="stats-card-body" style="padding: 0;">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Almacén</th>
                                <th>Valor Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($valor_almacenes->num_rows > 0): ?>
                                <?php while ($alm = $valor_almacenes->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars($alm['codigo']); ?></code><br>
                                        <small><?php echo htmlspecialchars($alm['nombre']); ?></small>
                                    </td>
                                    <td><strong>$<?php echo number_format($alm['valor_total'] ?? 0, 2); ?></strong></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">Sin datos</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Productos con Stock Bajo -->
    <?php if ($stock_bajo->num_rows > 0): ?>
    <div class="stats-card">
        <div class="stats-card-header stock-bajo-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Productos con Stock Bajo</h3>
        </div>
        <div class="stats-card-body" style="padding: 0;">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Almacén</th>
                            <th>Cantidad Actual</th>
                            <th>Stock Mínimo</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($prod = $stock_bajo->fetch_assoc()): ?>
                        <tr class="stock-bajo-row">
                            <td><code><?php echo htmlspecialchars($prod['codigo_producto']); ?></code></td>
                            <td><?php echo htmlspecialchars($prod['nombre_producto']); ?></td>
                            <td><?php echo htmlspecialchars($prod['almacen_nombre']); ?></td>
                            <td><strong class="stock-bajo-value"><?php echo $prod['cantidad']; ?></strong></td>
                            <td><?php echo $prod['stock_minimo']; ?></td>
                            <td>
                                <a href="../productos/productos.php" class="btn btn-sm btn-warning">
                                    <i class="fas fa-plus"></i> Reabastecer
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Resumen de Cartas de Entrega -->
    <div class="stats-card">
        <div class="stats-card-header">
            <h3><i class="fas fa-file-alt"></i> Resumen de Cartas de Entrega</h3>
        </div>
        <div class="stats-card-body">
            <div class="carta-resumen">
                <div class="carta-resumen-value"><?php echo $cartas_mes; ?></div>
                <div class="carta-resumen-label">Cartas con fecha de entrega en los últimos 30 días (hasta hoy)</div>
                <a href="../cartas/cartas-entrega.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> Ver Todas las Cartas
                </a>
            </div>
        </div>
    </div>
</div>

<script src="../../js/main.js"></script>

<?php require_once '../../includes/footer.php'; ?>

