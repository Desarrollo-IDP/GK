<?php
require_once '../../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
checkAuth();
$pageTitle = 'Historial de Movimientos';
require_once '../../includes/header.php';

$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa en historial_movimientos y productos_almacen
$check_column_historial = $conn->query("SHOW COLUMNS FROM historial_movimientos LIKE 'id_empresa'");
$has_empresa_column_historial = $check_column_historial && $check_column_historial->num_rows > 0;

$check_column_productos = $conn->query("SHOW COLUMNS FROM productos_almacen LIKE 'id_empresa'");
$has_empresa_column_productos = $check_column_productos && $check_column_productos->num_rows > 0;

// Filtros
$filter_tipo = $_GET['tipo'] ?? '';
$filter_producto = $_GET['producto'] ?? '';
$filter_fecha_desde = $_GET['fecha_desde'] ?? '';
$filter_fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Obtener movimientos con filtro por empresa
$movimientos_query = "SELECT hm.*, p.nombre_producto, p.codigo_producto, 
                      a1.nombre as almacen_origen_nombre, a2.nombre as almacen_destino_nombre
                      FROM historial_movimientos hm
                      LEFT JOIN productos_almacen p ON hm.id_producto = p.id_producto
                      LEFT JOIN almacenes a1 ON hm.almacen_origen = a1.id_almacen
                      LEFT JOIN almacenes a2 ON hm.almacen_destino = a2.id_almacen
                      WHERE 1=1";

$movimientos_params = [];
$movimientos_types = '';

// Agregar filtro por empresa del usuario
if ($has_empresa_column_historial && $user_empresa_id !== null) {
    $movimientos_query .= " AND hm.id_empresa = ?";
    $movimientos_params[] = $user_empresa_id;
    $movimientos_types = 'i';
} elseif ($has_empresa_column_productos && $user_empresa_id !== null) {
    // Si no hay columna en historial pero sí en productos, filtrar por productos
    $movimientos_query .= " AND p.id_empresa = ?";
    $movimientos_params[] = $user_empresa_id;
    $movimientos_types = 'i';
}

if ($filter_tipo) {
    $movimientos_query .= " AND hm.tipo_movimiento = '" . $conn->real_escape_string($filter_tipo) . "'";
}
if ($filter_producto) {
    $movimientos_query .= " AND hm.id_producto = " . intval($filter_producto);
}
if ($filter_fecha_desde) {
    $movimientos_query .= " AND DATE(hm.fecha_movimiento) >= '" . $conn->real_escape_string($filter_fecha_desde) . "'";
}
if ($filter_fecha_hasta) {
    $movimientos_query .= " AND DATE(hm.fecha_movimiento) <= '" . $conn->real_escape_string($filter_fecha_hasta) . "'";
}

$movimientos_query .= " ORDER BY hm.fecha_movimiento DESC LIMIT 100";

if (!empty($movimientos_params)) {
    $movimientos_stmt = $conn->prepare($movimientos_query);
    $movimientos_stmt->bind_param($movimientos_types, ...$movimientos_params);
    $movimientos_stmt->execute();
    $movimientos = $movimientos_stmt->get_result();
} else {
    $movimientos = $conn->query($movimientos_query);
}

// Obtener productos para el filtro con filtro por empresa
$productos_query = "SELECT id_producto, nombre_producto, codigo_producto FROM productos_almacen";
$productos_params = [];
$productos_types = '';

if ($has_empresa_column_productos && $user_empresa_id !== null) {
    $productos_query .= " WHERE id_empresa = ?";
    $productos_params[] = $user_empresa_id;
    $productos_types = 'i';
}

$productos_query .= " ORDER BY nombre_producto";

if (!empty($productos_params)) {
    $productos_stmt = $conn->prepare($productos_query);
    $productos_stmt->bind_param($productos_types, ...$productos_params);
    $productos_stmt->execute();
    $productos = $productos_stmt->get_result();
} else {
    $productos = $conn->query($productos_query);
}
?>

<style>
    /* Estilos mejorados para historial.php */
    .historial-header {
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
    
    .historial-header h1 {
        color: #1E2A3B;
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .historial-header h1 i {
        color: #1E2A3B;
        font-size: 1.5rem;
    }
    
    .historial-header .btn-info {
        background-color: #3b82f6;
        color: #FFFFFF;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .historial-header .btn-info:hover {
        background-color: #2563eb;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
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
    
    .table-container {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
    
    .badge-danger {
        background-color: #fee2e2;
        color: #991b1b;
    }
    
    .badge-info {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .badge-warning {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .badge-secondary {
        background-color: #e5e7eb;
        color: #374151;
    }
    
    .form-group {
        margin-bottom: 0;
    }
    
    .form-label {
        color: #1E2A3B;
        font-weight: 600;
        font-size: 0.9375rem;
        margin-bottom: 0.5rem;
        display: block;
    }
    
    @media (max-width: 768px) {
        .historial-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        
        .historial-header .btn-info {
            width: 100%;
            justify-content: center;
        }
        
        .filters-card > div {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<div class="container">
    <div class="historial-header">
        <h1>
            <i class="fas fa-history"></i> Historial de Movimientos
        </h1>
        <!--<a href="./reportes.php" class="btn-info">
            <i class="fas fa-chart-bar"></i> Ver Reportes
        </a>-->
    </div>

    <!-- Filtros -->
    <form method="get" class="filters-card">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr 1fr; gap: 15px;">
            <div class="form-group mb-0">
                <label class="form-label">Tipo de Movimiento</label>
                <select name="tipo" class="form-control">
                    <option value="">Todos</option>
                    <option value="Entrada" <?php echo $filter_tipo === 'Entrada' ? 'selected' : ''; ?>>Entrada</option>
                    <option value="Salida" <?php echo $filter_tipo === 'Salida' ? 'selected' : ''; ?>>Salida</option>
                    <option value="Transferencia" <?php echo $filter_tipo === 'Transferencia' ? 'selected' : ''; ?>>Transferencia</option>
                    <option value="Ajuste" <?php echo $filter_tipo === 'Ajuste' ? 'selected' : ''; ?>>Ajuste</option>
                    <option value="Entrega" <?php echo $filter_tipo === 'Entrega' ? 'selected' : ''; ?>>Entrega</option>
                    <option value="Devolución" <?php echo $filter_tipo === 'Devolución' ? 'selected' : ''; ?>>Devolución</option>
                </select>
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label">Producto</label>
                <select name="producto" class="form-control">
                    <option value="">Todos</option>
                    <?php while ($p = $productos->fetch_assoc()): ?>
                        <option value="<?php echo $p['id_producto']; ?>" <?php echo $filter_producto == $p['id_producto'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['codigo_producto']); ?> - <?php echo htmlspecialchars($p['nombre_producto']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label">Desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="<?php echo $filter_fecha_desde; ?>">
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?php echo $filter_fecha_hasta; ?>">
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
            </div>
        </div>
    </form>

    <!-- Tabla de Movimientos -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Fecha/Hora</th>
                    <th>Tipo</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th class="hide-mobile">Origen</th>
                    <th class="hide-mobile">Destino</th>
                    <th class="hide-mobile">Usuario</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($movimientos->num_rows > 0): ?>
                    <?php while ($mov = $movimientos->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                        <td>
                            <?php
                            $badgeClass = '';
                            $icon = '';
                            switch ($mov['tipo_movimiento']) {
                                case 'Entrada':
                                    $badgeClass = 'badge-success';
                                    $icon = 'arrow-down';
                                    break;
                                case 'Salida':
                                    $badgeClass = 'badge-danger';
                                    $icon = 'arrow-up';
                                    break;
                                case 'Transferencia':
                                    $badgeClass = 'badge-info';
                                    $icon = 'exchange-alt';
                                    break;
                                case 'Entrega':
                                    $badgeClass = 'badge-warning';
                                    $icon = 'hand-holding';
                                    break;
                                case 'Devolución':
                                    $badgeClass = 'badge-secondary';
                                    $icon = 'undo';
                                    break;
                                case 'Ajuste':
                                    $badgeClass = 'badge-warning';
                                    $icon = 'adjust';
                                    break;
                                default:
                                    $badgeClass = 'badge-secondary';
                                    $icon = 'circle';
                            }
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>">
                                <i class="fas fa-<?php echo $icon; ?>"></i> <?php echo $mov['tipo_movimiento']; ?>
                            </span>
                        </td>
                        <td>
                            <code><?php echo htmlspecialchars($mov['codigo_producto'] ?? 'N/A'); ?></code><br>
                            <small><?php echo htmlspecialchars($mov['nombre_producto'] ?? 'Producto eliminado'); ?></small>
                        </td>
                        <td><strong><?php echo $mov['cantidad']; ?></strong></td>
                        <td class="hide-mobile"><?php echo htmlspecialchars($mov['almacen_origen_nombre'] ?? '-'); ?></td>
                        <td class="hide-mobile"><?php echo htmlspecialchars($mov['almacen_destino_nombre'] ?? '-'); ?></td>
                        <td class="hide-mobile"><?php echo htmlspecialchars($mov['usuario'] ?? 'Sistema'); ?></td>
                        <td><small><?php echo htmlspecialchars($mov['motivo'] ?? 'Sin especificar'); ?></small></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">No hay movimientos registrados</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- <div class="alert alert-info mt-4">
        <i class="fas fa-info-circle"></i> Mostrando los últimos 100 movimientos. Use los filtros para buscar movimientos específicos.
    </div> -->
</div>

<script src="../../js/main.js"></script>

<?php require_once '../../includes/footer.php'; ?>

