<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/ticket_display_helper.php';
require_once '../../includes/ticket_history.php';

checkAuth();

// ============================================
// ELIMINACIÓN (borrado lógico)
// ============================================
if (isset($_GET['delete']) && $_GET['delete'] !== '') {
    $id = (int)$_GET['delete'];

    $check_query = "SELECT id FROM tickets WHERE id = ? AND (deleted IS NULL OR deleted != 1)";
    $check_params = [$id];
    $check_types = 'i';
    $check_query = addEmpresaFilter($check_query, 'id_empresa', $check_params, $check_types);

    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param($check_types, ...$check_params);
    $check_stmt->execute();
    $ticket_exists = $check_stmt->get_result()->num_rows > 0;
    $check_stmt->close();

    if ($ticket_exists) {
        $stmt = $conn->prepare("UPDATE tickets SET deleted = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            logTicketDeleted($conn, $id, getUserName());
        }
        $stmt->close();
    }

    header('Location: closed_tickets.php');
    exit;
}

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
$where = [
    't.closed_at IS NOT NULL',
    '(t.deleted IS NULL OR t.deleted != 1)',
];
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
    $where[] = 'DATE(t.closed_at) >= ?';
    $params[] = $fecha_desde;
    $types .= 's';
}

if ($fecha_hasta !== '') {
    $where[] = 'DATE(t.closed_at) <= ?';
    $params[] = $fecha_hasta;
    $types .= 's';
}

$where_sql = ' WHERE ' . implode(' AND ', $where);

$joins = "
    FROM tickets t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN statuses s ON t.status_id = s.id
    LEFT JOIN requesters r ON t.requester_id = r.id
    LEFT JOIN technicians tech ON t.technician_id = tech.id
    LEFT JOIN empresas_usuarios e ON t.id_empresa = e.id_empresa
";

// ============================================
// CONTAR TOTAL
// ============================================
$count_query = 'SELECT COUNT(*) ' . $joins . $where_sql;
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
           r.name AS requester_name,
           tech.name AS technician_name,
           e.nombre AS empresa_nombre
    {$joins}
    {$where_sql}
";
$query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
$query .= ' ORDER BY t.closed_at DESC LIMIT ? OFFSET ?';
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

// Parámetros de URL para paginación (conserva filtros)
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

function closedTicketsPageUrl(array $base, int $page): string
{
    $base['pagina'] = $page;
    return '?' . http_build_query($base);
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
.closed-filters-card {
    background: #fbfcfe;
    border: 1px solid #e6ebf2;
    border-radius: 14px;
    padding: 1rem 1.15rem;
    margin-bottom: 1.25rem;
}
.closed-filters-card .form-label {
    font-size: 0.8rem;
    font-weight: 650;
    color: #334155;
    margin-bottom: 0.35rem;
}
.closed-filters-card .form-control {
    border-radius: 10px;
    border-color: #d7dee8;
    min-height: 2.4rem;
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
                    <h1 class="h2 mb-2">Tickets Cerrados</h1>
                    <p class="text-muted mb-0">Consulta el historial de tickets cerrados</p>
                </div>
                <div class="tickets-page-actions">
                    <a href="tickets.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver a Tickets Activos
                    </a>
                </div>
            </div>

            <div class="closed-filters-card">
                <form method="GET" action="closed_tickets.php" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="search" name="search"
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Ticket, solicitante, técnico...">
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_desde" class="form-label">Cierre desde</label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                               value="<?php echo htmlspecialchars($fecha_desde); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_hasta" class="form-label">Cierre hasta</label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                               value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-search me-1"></i>Filtrar
                        </button>
                        <?php if ($search !== '' || $fecha_desde !== '' || $fecha_hasta !== ''): ?>
                        <a href="closed_tickets.php" class="btn btn-outline-secondary" title="Limpiar">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <p class="pagination-meta mb-0">
                    <?php if ($total_registros > 0): ?>
                        Mostrando <?php echo $registro_inicio; ?>–<?php echo $registro_fin; ?>
                        de <?php echo number_format($total_registros); ?> tickets cerrados
                        <?php if ($total_paginas > 1): ?>
                            · Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        Sin resultados
                    <?php endif; ?>
                </p>
            </div>

            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-striped table-hover tickets-list-table align-middle">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Descripción</th>
                                <th>Solicitante</th>
                                <th class="tickets-col-desktop-only">Técnico</th>
                                <th>Categoría</th>
                                <th>Estado</th>
                                <th>Fecha de Cierre</th>
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
                                    <td data-label="Descripción">
                                        <?php
                                        $desc = (string)($row['description'] ?? '');
                                        echo htmlspecialchars(strlen($desc) > 50 ? substr($desc, 0, 50) . '...' : $desc);
                                        ?>
                                    </td>
                                    <td data-label="Solicitante"><?php echo htmlspecialchars($row['requester_name'] ?? 'No asignado'); ?></td>
                                    <td class="tickets-col-desktop-only" data-label="Técnico">
                                        <?php echo htmlspecialchars($row['technician_name'] ?? 'Sin asignar'); ?>
                                    </td>
                                    <td data-label="Categoría"><?php echo htmlspecialchars($row['category_name'] ?? '—'); ?></td>
                                    <td data-label="Estado">
                                        <span class="badge bg-<?php echo ticketGetStatusColorClass($row['status_name'] ?? '', ticketIsClosedByInactivity($row)); ?>">
                                            <?php if (ticketIsClosedByInactivity($row)): ?>
                                            <i class="fas fa-clock me-1"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars(ticketGetDisplayStatusNameFromRow($row)); ?>
                                        </span>
                                    </td>
                                    <td data-label="Cierre"><?php echo date('d/m/Y H:i', strtotime($row['closed_at'])); ?></td>
                                    <td data-label="Acciones">
                                        <div class="tickets-list-actions">
                                            <a href="view.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-info" title="Ver">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (canViewTicketHistory()): ?>
                                            <a href="history.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-info" title="Historial">
                                                <i class="fas fa-history"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No hay tickets cerrados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación de tickets cerrados">
                <ul class="pagination justify-content-center flex-wrap">
                    <li class="page-item <?php echo ($pagina <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars(closedTicketsPageUrl($query_params_base, max(1, $pagina - 1))); ?>">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    </li>

                    <?php
                    $inicio_paginas = max(1, $pagina - 3);
                    $fin_paginas = min($total_paginas, $pagina + 3);
                    ?>

                    <?php if ($inicio_paginas > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo htmlspecialchars(closedTicketsPageUrl($query_params_base, 1)); ?>">1</a>
                        </li>
                        <?php if ($inicio_paginas > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): ?>
                        <li class="page-item <?php echo ($i === $pagina) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars(closedTicketsPageUrl($query_params_base, $i)); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($fin_paginas < $total_paginas): ?>
                        <?php if ($fin_paginas < $total_paginas - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo htmlspecialchars(closedTicketsPageUrl($query_params_base, $total_paginas)); ?>">
                                <?php echo $total_paginas; ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="page-item <?php echo ($pagina >= $total_paginas) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars(closedTicketsPageUrl($query_params_base, min($total_paginas, $pagina + 1))); ?>">
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
