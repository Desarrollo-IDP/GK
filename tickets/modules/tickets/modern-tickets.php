<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/ticket_history.php';

if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Verificar que el ticket pertenece a la empresa del usuario antes de eliminar
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
        $query = "UPDATE tickets SET deleted = 1 WHERE id = ?";
        $params = [$id];
        $types = 'i';
        $query = addEmpresaFilter($query, 'id_empresa', $params, $types);
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            logTicketDeleted($conn, $id, getUserName());
            $message = "Ticket eliminado correctamente";
            $messageType = "success";
        } else {
            $message = "Error al eliminar el ticket: " . $conn->error;
            $messageType = "danger";
        }
        $stmt->close();
    } else {
        $message = "No se encontró el ticket o no tiene permiso para eliminarlo";
        $messageType = "danger";
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$status_id_filter = isset($_GET['status_id']) ? intval($_GET['status_id']) : 0;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$params = [];
$types = '';

if ($q !== '' && is_numeric($q)) {
    $query = "SELECT t.*, 
          c.name as category_name, 
          s.name as status_name,
          cm.name as contact_mode_name,
          r.name as requester_name,
          tech.name as technician_name,
          eu.nombre as empresa_nombre
          FROM tickets t
          LEFT JOIN categories c ON t.category_id = c.id
          LEFT JOIN statuses s ON t.status_id = s.id
          LEFT JOIN contact_modes cm ON t.contact_mode_id = cm.id
          LEFT JOIN requesters r ON t.requester_id = r.id
          LEFT JOIN technicians tech ON t.technician_id = tech.id
          LEFT JOIN empresas_usuarios eu ON t.id_empresa = eu.id_empresa
          WHERE t.id = ? AND t.deleted != 1";
    $params[] = $q;
    $types .= 'i';
} else {
    $query = "SELECT t.*, 
          c.name as category_name, 
          s.name as status_name,
          cm.name as contact_mode_name,
          r.name as requester_name,
          tech.name as technician_name,
          eu.nombre as empresa_nombre
          FROM tickets t
          LEFT JOIN categories c ON t.category_id = c.id
          LEFT JOIN statuses s ON t.status_id = s.id
          LEFT JOIN contact_modes cm ON t.contact_mode_id = cm.id
          LEFT JOIN requesters r ON t.requester_id = r.id
          LEFT JOIN technicians tech ON t.technician_id = tech.id
          LEFT JOIN empresas_usuarios eu ON t.id_empresa = eu.id_empresa
          WHERE t.closed_at IS NULL AND t.deleted != 1";
}

// Agregar filtro de empresa
$query = addEmpresaFilter($query, 't.id_empresa', $params, $types);

// FILTROS ADICIONALES
// Filtro: Sin Asignar (status_id = 1)
if ($filter === 'unassigned') {
    $query .= " AND t.status_id = 1";
} 
// Filtro: Por status_id directo (más confiable)
elseif ($status_id_filter > 0) {
    $query .= " AND t.status_id = ?";
    $params[] = $status_id_filter;
    $types .= 'i';
}
// Filtro: Por nombre de status (Abierto, En Proceso, etc.) - compatibilidad
elseif (!empty($status_filter)) {
    $query .= " AND s.name = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Obtener estadísticas (solo tickets NO eliminados) con filtro de empresa
$stats_query = "SELECT 
    COUNT(*) as total_tickets,
    SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as unassigned_tickets,
    SUM(CASE WHEN status_id = 2 THEN 1 ELSE 0 END) as open_tickets,
    SUM(CASE WHEN status_id = 10 THEN 1 ELSE 0 END) as in_progress_tickets
FROM tickets WHERE closed_at IS NULL AND deleted != 1";
$stats_params = [];
$stats_types = '';
$stats_query = addEmpresaFilter($stats_query, 'id_empresa', $stats_params, $stats_types);
$stats_stmt = $conn->prepare($stats_query);
if (!empty($stats_params)) {
    $stats_stmt->bind_param($stats_types, ...$stats_params);
}
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();
?>

<?php include '../../includes/modern-header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Header de la página -->
            <div class="d-flex justify-content-between align-items-center mb-6">
                <div>
                    <h1 class="h2 mb-2">Gestión de Tickets</h1>
                    <p class="text-muted mb-0">Administra y da seguimiento a todos los tickets del sistema</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="closed_tickets.php" class="btn btn-secondary">
                        <i class="fas fa-archive me-2"></i>Tickets Cerrados
                    </a>
                    <a href="all_tickets.php" class="btn btn-secondary">
                        <i class="fas fa-list me-2"></i>Historial
                    </a>
                    <a href="new.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Nuevo Ticket
                    </a>
                </div>
            </div>

            <!-- Alertas -->
            <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Tarjetas de estadísticas -->
            <div class="row mb-6">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-sm text-muted mb-1">Total de Tickets</div>
                                    <div class="h3 font-bold text-primary"><?php echo $stats['total_tickets']; ?></div>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded">
                                    <i class="fas fa-ticket-alt fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-sm text-muted mb-1">Sin Asignar</div>
                                    <div class="h3 font-bold text-warning"><?php echo $stats['unassigned_tickets']; ?></div>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded">
                                    <i class="fas fa-user-clock fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-sm text-muted mb-1">Abiertos</div>
                                    <div class="h3 font-bold text-info"><?php echo $stats['open_tickets']; ?></div>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded">
                                    <i class="fas fa-folder-open fa-2x text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-sm text-muted mb-1">En Proceso</div>
                                    <div class="h3 font-bold text-success"><?php echo $stats['in_progress_tickets']; ?></div>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded">
                                    <i class="fas fa-cogs fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-6">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <label class="form-label">Filtrar por:</label>
                            <select class="form-select" id="statusFilter">
                                <option value="">Todos los estados</option>
                                <option value="Abierto" <?php echo $status_filter == 'Abierto' ? 'selected' : ''; ?>>Abierto</option>
                                <option value="En Proceso" <?php echo $status_filter == 'En Proceso' ? 'selected' : ''; ?>>En Proceso</option>
                                <option value="Cerrado" <?php echo $status_filter == 'Cerrado' ? 'selected' : ''; ?>>Cerrado</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo:</label>
                            <select class="form-select" id="typeFilter">
                                <option value="">Todos</option>
                                <option value="unassigned" <?php echo $filter == 'unassigned' ? 'selected' : ''; ?>>Sin asignar</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Buscar:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="searchFilter" placeholder="Buscar por ID, solicitante, técnico...">
                                <button class="btn btn-outline-secondary" type="button" id="clearFilters">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de tickets -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Categoría</th>
                                <th>Estado</th>
                                <th>Solicitante</th>
                                <th>Técnico</th>
                                <th>Fecha de Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="fade-in">
                                        <td>
                                            <span class="font-semibold" style="color: #202654;">#<?php echo $row['id']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-tag text-muted me-2"></i>
                                                <?php echo htmlspecialchars($row['category_name'] ?? 'No asignada'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo getStatusBadgeClass($row['status_name']); ?>">
                                                <i class="fas fa-<?php echo getStatusIcon($row['status_name']); ?> me-1"></i>
                                                <?php echo htmlspecialchars($row['status_name'] ?? 'Sin estado'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar me-2">
                                                    <i class="fas fa-user text-muted"></i>
                                                </div>
                                                <span><?php echo htmlspecialchars($row['requester_name'] ?? 'No asignado'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar me-2">
                                                    <i class="fas fa-tools text-muted"></i>
                                                </div>
                                                <span><?php echo htmlspecialchars($row['technician_name'] ?? 'No asignado'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-sm">
                                                <div class="font-medium"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></div>
                                                <div class="text-muted"><?php echo date('H:i', strtotime($row['created_at'])); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline" title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $row['id']; ?>)" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-6">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <div class="h5">No hay tickets registrados</div>
                                            <p>Comienza creando tu primer ticket</p>
                                            <a href="new.php" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Crear Ticket
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function confirmDelete(id) {
    if (confirm('¿Está seguro de que desea eliminar este ticket? Esta acción no se puede deshacer.')) {
        window.location.href = 'modern-tickets.php?delete=' + id;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Filtros
    const statusFilter = document.getElementById('statusFilter');
    const typeFilter = document.getElementById('typeFilter');
    const searchFilter = document.getElementById('searchFilter');
    const clearFilters = document.getElementById('clearFilters');
    
    function applyFilters() {
        const params = new URLSearchParams();
        
        if (statusFilter.value) params.append('status', statusFilter.value);
        if (typeFilter.value) params.append('filter', typeFilter.value);
        if (searchFilter.value) params.append('q', searchFilter.value);
        
        const url = 'modern-tickets.php' + (params.toString() ? '?' + params.toString() : '');
        window.location.href = url;
    }
    
    statusFilter.addEventListener('change', applyFilters);
    typeFilter.addEventListener('change', applyFilters);
    
    searchFilter.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            applyFilters();
        }
    });
    
    clearFilters.addEventListener('click', function() {
        statusFilter.value = '';
        typeFilter.value = '';
        searchFilter.value = '';
        applyFilters();
    });
    
    // Animaciones de entrada
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach((row, index) => {
        row.style.animationDelay = `${index * 0.1}s`;
    });
});
</script>

<?php
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'abierto':
            return 'primary';
        case 'en proceso':
            return 'warning';
        case 'cerrado':
            return 'success';
        case 'sin asignar':
            return 'secondary';
        default:
            return 'secondary';
    }
}

function getStatusIcon($status) {
    switch (strtolower($status)) {
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