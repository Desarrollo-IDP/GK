<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

checkAuth();

// ============================================
// FILTROS
// ============================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$fecha_desde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';

// ============================================
// PAGINACIÓN
// ============================================
$registros_por_pagina = 50;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) {
    $pagina = 1;
}
$offset = ($pagina - 1) * $registros_por_pagina;

// ============================================
// CONDICIONES DE FILTRO (compartidas listado + count)
// ============================================
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(
        t.id LIKE ?
        OR t.ticket_number LIKE ?
        OR t.description LIKE ?
        OR r.name LIKE ?
        OR tech.name LIKE ?
        OR c.name LIKE ?
        OR s.name LIKE ?
    )";
    $search_param = '%' . $search . '%';
    for ($i = 0; $i < 7; $i++) {
        $params[] = $search_param;
        $types .= 's';
    }
}

if ($fecha_desde !== '') {
    $where[] = 'DATE(t.created_at) >= ?';
    $params[] = $fecha_desde;
    $types .= 's';
}

if ($fecha_hasta !== '') {
    $where[] = 'DATE(t.created_at) <= ?';
    $params[] = $fecha_hasta;
    $types .= 's';
}

$where_sql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

$joins_count = "
    FROM tickets t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN statuses s ON t.status_id = s.id
    LEFT JOIN requesters r ON t.requester_id = r.id
    LEFT JOIN technicians tech ON t.technician_id = tech.id
";

$joins_list = $joins_count . "
    LEFT JOIN contact_modes cm ON t.contact_mode_id = cm.id
    LEFT JOIN empresas_usuarios e ON t.id_empresa = e.id_empresa
";

// ============================================
// CONTAR TOTAL
// ============================================
$count_query = 'SELECT COUNT(*) ' . $joins_count . $where_sql;
$count_params = $params;
$count_types = $types;
$count_query = addEmpresaFilter($count_query, 't.id_empresa', $count_params, $count_types);

$count_stmt = $conn->prepare($count_query);
if (!$count_stmt) {
    die('Error al preparar conteo: ' . $conn->error);
}
if ($count_types !== '') {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$total_registros = (int)$count_stmt->get_result()->fetch_row()[0];
$count_stmt->close();

$total_paginas = max(1, (int)ceil($total_registros / $registros_por_pagina));
if ($pagina > $total_paginas) {
    $pagina = $total_paginas;
    $offset = ($pagina - 1) * $registros_por_pagina;
}

// ============================================
// CONSULTA LISTADO
// ============================================
$query = "
    SELECT t.*,
           c.name AS category_name,
           s.name AS status_name,
           cm.name AS contact_mode_name,
           r.name AS requester_name,
           tech.name AS technician_name,
           e.nombre AS empresa_nombre
    {$joins_list}
    {$where_sql}
";
$query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
$query .= ' ORDER BY t.created_at DESC LIMIT ? OFFSET ?';
$params[] = $registros_por_pagina;
$types .= 'i';
$params[] = $offset;
$types .= 'i';

$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Error al preparar listado: ' . $conn->error);
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$registro_inicio = $total_registros > 0 ? $offset + 1 : 0;
$registro_fin = min($offset + $registros_por_pagina, $total_registros);

// ============================================
// CONTADORES RESUMEN
// ============================================
function allTicketsCountByCondition($conn, $extraWhere = '1=1')
{
    $q = "SELECT COUNT(*) FROM tickets WHERE {$extraWhere}";
    $p = [];
    $t = '';
    $q = addEmpresaFilter($q, 'id_empresa', $p, $t);
    $s = $conn->prepare($q);
    if (!$s) {
        return 0;
    }
    if ($t !== '') {
        $s->bind_param($t, ...$p);
    }
    $s->execute();
    $n = (int)$s->get_result()->fetch_row()[0];
    $s->close();
    return $n;
}

$total_count = allTicketsCountByCondition($conn, '1=1');
$open_count = allTicketsCountByCondition($conn, 'status_id = 2 AND (deleted IS NULL OR deleted != 1)');
$closed_count = allTicketsCountByCondition($conn, 'status_id = 3 AND (deleted IS NULL OR deleted != 1)');
$deleted_count = allTicketsCountByCondition($conn, 'deleted = 1');

function allTicketsPageUrl(array $base, int $page): string
{
    $base['pagina'] = $page;
    return '?' . http_build_query($base);
}

$query_params_base = [];
if ($search !== '') {
    $query_params_base['search'] = $search;
}
if ($fecha_desde !== '') {
    $query_params_base['fecha_desde'] = $fecha_desde;
}
if ($fecha_hasta !== '') {
    $query_params_base['fecha_hasta'] = $fecha_hasta;
}

function getStatusColor($status)
{
    switch (strtolower((string)$status)) {
        case 'abierto':
            return 'primary';
        case 'cerrado':
            return 'secondary';
        case 'sin asignar':
            return 'warning';
        default:
            return 'secondary';
    }
}

function getStatusIcon($status)
{
    switch (strtolower((string)$status)) {
        case 'abierto':
            return 'folder-open';
        case 'en proceso':
            return 'cogs';
        case 'cerrado':
            return 'check-circle';
        case 'sin asignar':
            return 'user-clock';
        default:
            return 'question-circle';
    }
}
?>
<?php include '../../includes/modern-header.php'; ?>
<style>
.body-bg {
    background: #f7f8fa;
    min-height: 100vh;
    width: 100%;
    overflow-x: hidden;
}
.main-content-boxed {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2.5rem 2rem;
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 24px 0 rgba(32,38,84,0.07);
    min-height: 80vh;
    margin-top: 2rem;
    margin-bottom: 2rem;
}
@media (max-width: 991.98px) {
    .main-content-boxed {
        padding: 1rem 0.5rem;
        border-radius: 0.75rem;
        margin-top: 1rem;
        margin-bottom: 1rem;
    }
}
@media (max-width: 767.98px) {
    .main-content-boxed {
        padding: 0.5rem 0.2rem;
        border-radius: 0.5rem;
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
    }
    .table th, .table td {
        padding: 0.5rem;
        font-size: 0.95rem;
    }
    .table-responsive { overflow-x: auto; }
}
.table-container {
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 2px 8px 0 rgba(32,38,84,0.04);
    margin-bottom: 1.5rem;
    padding: 0.5rem;
}
.badge-status {
    font-size: 0.95em;
    padding: 0.45em 1em;
    border-radius: 0.7em;
    font-weight: 500;
    letter-spacing: 0.02em;
}
.pagination-meta {
    font-size: 0.875rem;
    color: #64748b;
}
</style>
<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-2">Historial de Tickets</h1>
                    <p class="text-muted mb-0">
                        <?php if ($search !== '' || $fecha_desde !== '' || $fecha_hasta !== ''): ?>
                            Mostrando <?php echo number_format($total_registros); ?> tickets con los filtros aplicados
                        <?php else: ?>
                            Consulta el historial completo de tickets del sistema
                        <?php endif; ?>
                    </p>
                </div>
                <div class="tickets-page-actions">
                    <a href="tickets.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver a Tickets Activos
                    </a>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card text-center shadow-sm border-0">
                        <div class="card-body py-3">
                            <div class="h4 mb-1" style="color:#202654;"><i class="fas fa-ticket-alt me-2"></i><?php echo $total_count; ?></div>
                            <div class="text-muted small">Total de Tickets</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center shadow-sm border-0">
                        <div class="card-body py-3">
                            <div class="h4 mb-1 text-primary"><i class="fas fa-folder-open me-2"></i><?php echo $open_count; ?></div>
                            <div class="text-muted small">Abiertos</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center shadow-sm border-0">
                        <div class="card-body py-3">
                            <div class="h4 mb-1 text-secondary"><i class="fas fa-archive me-2"></i><?php echo $closed_count; ?></div>
                            <div class="text-muted small">Cerrados</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center shadow-sm border-0">
                        <div class="card-body py-3">
                            <div class="h4 mb-1 text-danger"><i class="fas fa-trash-alt me-2"></i><?php echo $deleted_count; ?></div>
                            <div class="text-muted small">Eliminados</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-12 col-md-4">
                            <label for="search" class="form-label fw-semibold">Buscar</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="search" name="search"
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Ticket, descripción, solicitante...">
                            </div>
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="fecha_desde" class="form-label fw-semibold">Desde</label>
                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                                   value="<?php echo htmlspecialchars($fecha_desde); ?>">
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="fecha_hasta" class="form-label fw-semibold">Hasta</label>
                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                                   value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                        </div>
                        <div class="col-12 col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-filter me-1"></i>Filtrar
                            </button>
                            <a href="?" class="btn btn-outline-secondary" title="Limpiar filtros">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </form>

                    <div class="mt-3 pagination-meta">
                        <?php if ($total_registros > 0): ?>
                            Mostrando <strong><?php echo $registro_inicio; ?></strong>–<strong><?php echo $registro_fin; ?></strong>
                            de <strong><?php echo number_format($total_registros); ?></strong> tickets
                            <?php if ($total_paginas > 1): ?>
                                · Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            Sin resultados
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div class="table-responsive">
                    <table class="table align-middle tickets-list-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Categoría</th>
                                <th>Estado</th>
                                <th>Solicitante</th>
                                <th class="tickets-col-desktop-only">Técnico</th>
                                <th>Fecha de Creación</th>
                                <th>¿Eliminado?</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td data-label="Ticket">
                                            <span class="font-semibold" style="color: #202654;">
                                                #<?php echo htmlspecialchars($row['ticket_number'] ?? $row['id']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Categoría">
                                            <div class="d-flex align-items-center justify-content-end justify-content-md-start">
                                                <i class="fas fa-tag text-muted me-2 d-none d-md-inline"></i>
                                                <?php echo htmlspecialchars($row['category_name'] ?? 'No asignada'); ?>
                                            </div>
                                        </td>
                                        <td data-label="Estado">
                                            <span class="badge badge-status bg-<?php echo getStatusColor($row['status_name'] ?? ''); ?>">
                                                <i class="fas fa-<?php echo getStatusIcon($row['status_name'] ?? ''); ?> me-1"></i>
                                                <?php echo htmlspecialchars($row['status_name'] ?? 'Sin estado'); ?>
                                            </span>
                                        </td>
                                        <td data-label="Solicitante"><?php echo htmlspecialchars($row['requester_name'] ?? 'No asignado'); ?></td>
                                        <td class="tickets-col-desktop-only" data-label="Técnico"><?php echo htmlspecialchars($row['technician_name'] ?? 'No asignado'); ?></td>
                                        <td data-label="Fecha"><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                        <td data-label="Estado registro">
                                            <?php if (!empty($row['deleted'])): ?>
                                                <span class="badge bg-danger">Eliminado</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Acciones">
                                            <div class="tickets-list-actions">
                                                <a href="view.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline" title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if (canViewTicketHistory()): ?>
                                                    <a href="history.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-info" title="Ver historial">
                                                        <i class="fas fa-history"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No hay tickets registrados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación de tickets">
                <ul class="pagination justify-content-center flex-wrap">
                    <li class="page-item <?php echo ($pagina <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars(allTicketsPageUrl($query_params_base, max(1, $pagina - 1))); ?>">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    </li>

                    <?php
                    $inicio_paginas = max(1, $pagina - 3);
                    $fin_paginas = min($total_paginas, $pagina + 3);
                    ?>

                    <?php if ($inicio_paginas > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo htmlspecialchars(allTicketsPageUrl($query_params_base, 1)); ?>">1</a>
                        </li>
                        <?php if ($inicio_paginas > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): ?>
                        <li class="page-item <?php echo ($i === $pagina) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars(allTicketsPageUrl($query_params_base, $i)); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($fin_paginas < $total_paginas): ?>
                        <?php if ($fin_paginas < $total_paginas - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo htmlspecialchars(allTicketsPageUrl($query_params_base, $total_paginas)); ?>">
                                <?php echo $total_paginas; ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="page-item <?php echo ($pagina >= $total_paginas) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars(allTicketsPageUrl($query_params_base, min($total_paginas, $pagina + 1))); ?>">
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </main>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
