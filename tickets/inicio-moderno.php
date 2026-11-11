<?php
include 'includes/common.php';
include 'conexion/db.php';
include '../auth.php';
require_once '../includes/empresa_helper.php';

// Obtener empresa del usuario actual
$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa en tickets
$check_column = $conn->query("SHOW COLUMNS FROM tickets LIKE 'id_empresa'");
$has_empresa_column = $check_column && $check_column->num_rows > 0;

// Total de Tickets: Todos los tickets no eliminados (incluyendo cerrados)
$total_query = "SELECT COUNT(*) as total FROM tickets WHERE deleted != 1";
$total_params = [];
$total_types = '';
if ($has_empresa_column && $user_empresa_id !== null) {
    $total_query .= " AND id_empresa = ?";
    $total_params[] = $user_empresa_id;
    $total_types = 'i';
}
$total_stmt = $conn->prepare($total_query);
if (!empty($total_params)) {
    $total_stmt->bind_param($total_types, ...$total_params);
}
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_tickets = $total_result->fetch_assoc()['total'];
$total_stmt->close();

// Gráfico de Estados - Solo tickets NO eliminados
$status_query = "SELECT s.name, COUNT(t.id) as count 
                FROM tickets t 
                JOIN statuses s ON t.status_id = s.id 
                WHERE t.deleted != 1";
$status_params = [];
$status_types = '';
if ($has_empresa_column && $user_empresa_id !== null) {
    $status_query .= " AND t.id_empresa = ?";
    $status_params[] = $user_empresa_id;
    $status_types = 'i';
}
$status_query .= " GROUP BY t.status_id";
$status_stmt = $conn->prepare($status_query);
if (!empty($status_params)) {
    $status_stmt->bind_param($status_types, ...$status_params);
}
$status_stmt->execute();
$status_result = $status_stmt->get_result();

// Gráfico de Categorías - Solo tickets NO eliminados
$category_query = "SELECT c.name, COUNT(t.id) as count 
                  FROM tickets t 
                  JOIN categories c ON t.category_id = c.id 
                  WHERE t.deleted != 1";
$category_params = [];
$category_types = '';
if ($has_empresa_column && $user_empresa_id !== null) {
    $category_query .= " AND t.id_empresa = ?";
    $category_params[] = $user_empresa_id;
    $category_types = 'i';
}
$category_query .= " GROUP BY t.category_id";
$category_stmt = $conn->prepare($category_query);
if (!empty($category_params)) {
    $category_stmt->bind_param($category_types, ...$category_params);
}
$category_stmt->execute();
$category_result = $category_stmt->get_result();
 
// Gráfico de Técnicos - Solo tickets NO eliminados
$technician_query = "SELECT tech.name, COUNT(t.id) as count 
                    FROM tickets t 
                    JOIN technicians tech ON t.technician_id = tech.id 
                    WHERE t.deleted != 1";
$technician_params = [];
$technician_types = '';
if ($has_empresa_column && $user_empresa_id !== null) {
    $technician_query .= " AND t.id_empresa = ?";
    $technician_params[] = $user_empresa_id;
    $technician_types = 'i';
}
$technician_query .= " GROUP BY t.technician_id";
$technician_stmt = $conn->prepare($technician_query);
if (!empty($technician_params)) {
    $technician_stmt->bind_param($technician_types, ...$technician_params);
}
$technician_stmt->execute();
$technician_result = $technician_stmt->get_result();

// Tickets Recientes - Solo tickets NO eliminados
$recent_query = "SELECT t.id, t.ticket_number, t.created_at, t.description, s.name as status_name, 
                c.name as category_name, r.name as requester_name 
                FROM tickets t
                LEFT JOIN statuses s ON t.status_id = s.id
                LEFT JOIN categories c ON t.category_id = c.id
                LEFT JOIN requesters r ON t.requester_id = r.id
                WHERE t.deleted != 1";
$recent_params = [];
$recent_types = '';
if ($has_empresa_column && $user_empresa_id !== null) {
    $recent_query .= " AND t.id_empresa = ?";
    $recent_params[] = $user_empresa_id;
    $recent_types = 'i';
}
$recent_query .= " ORDER BY t.created_at DESC LIMIT 5";
$recent_stmt = $conn->prepare($recent_query);
if (!empty($recent_params)) {
    $recent_stmt->bind_param($recent_types, ...$recent_params);
}
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();

// Actividad Diaria - Solo tickets NO eliminados
$daily_query = "SELECT DATE(created_at) as date, COUNT(*) as count 
               FROM tickets 
               WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
               AND deleted != 1";
$daily_params = [];
$daily_types = '';
if ($has_empresa_column && $user_empresa_id !== null) {
    $daily_query .= " AND id_empresa = ?";
    $daily_params[] = $user_empresa_id;
    $daily_types = 'i';
}
$daily_query .= " GROUP BY DATE(created_at)";
$daily_stmt = $conn->prepare($daily_query);
if (!empty($daily_params)) {
    $daily_stmt->bind_param($daily_types, ...$daily_params);
}
$daily_stmt->execute();
$daily_result = $daily_stmt->get_result();

$status_labels = [];
$status_data = [];
$status_colors = [];
while ($row = $status_result->fetch_assoc()) {
    $status_labels[] = $row['name'];
    $status_data[] = $row['count'];
    $name = trim(mb_strtolower($row['name'], 'UTF-8'));
    if ($name === 'abierto') {
        $status_colors[] = '#22c55e'; 
    } elseif ($name === 'cerrado') {
        $status_colors[] = '#202654'; 
    } elseif ($name === 'sin asignar') {
        $status_colors[] = '#f59e0b'; 
    } elseif (strpos($name, 'atención') !== false || strpos($name, 'espera') !== false) {
        $status_colors[] = '#ef4444'; 
    } else {
        $status_colors[] = '#6b7280'; 
    }
}
$status_stmt->close();

$category_labels = [];
$category_data = [];
while ($row = $category_result->fetch_assoc()) {
    $category_labels[] = $row['name'];
    $category_data[] = $row['count'];
}
$category_stmt->close();

$technician_labels = [];
$technician_data = [];
while ($row = $technician_result->fetch_assoc()) {
    $technician_labels[] = $row['name'];
    $technician_data[] = $row['count'];
}
$technician_stmt->close();

$daily_labels = [];
$daily_data = [];
$dates = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[$date] = 0;
    $daily_labels[] = date('d/m', strtotime($date));
}

while ($row = $daily_result->fetch_assoc()) {
    $dates[$row['date']] = $row['count'];
}
$daily_stmt->close();

$daily_data = array_values($dates);

// ============================================
// ESTADÍSTICAS PARA LAS TARJETAS DEL DASHBOARD
// ============================================

// Tickets Abiertos: status_id = 2 y no eliminados (deleted != 1)
$open_query = "SELECT COUNT(*) as count FROM tickets WHERE status_id = 2 AND deleted != 1";
$open_params = [];
$open_types = '';
if ($has_empresa_column && $user_empresa_id !== null) {
    $open_query .= " AND id_empresa = ?";
    $open_params[] = $user_empresa_id;
    $open_types = 'i';
}
$open_stmt = $conn->prepare($open_query);
if (!empty($open_params)) {
    $open_stmt->bind_param($open_types, ...$open_params);
}
$open_stmt->execute();
$open_result = $open_stmt->get_result();
$open_tickets = $open_result->fetch_assoc()['count'];
$open_stmt->close();

// Sin Asignar: status_id = 1 y no eliminados (deleted != 1)
$unassigned_query = "SELECT COUNT(*) as count FROM tickets WHERE status_id = 1 AND deleted != 1";
$unassigned_params = [];
$unassigned_types = '';
if ($has_empresa_column && $user_empresa_id !== null) {
    $unassigned_query .= " AND id_empresa = ?";
    $unassigned_params[] = $user_empresa_id;
    $unassigned_types = 'i';
}
$unassigned_stmt = $conn->prepare($unassigned_query);
if (!empty($unassigned_params)) {
    $unassigned_stmt->bind_param($unassigned_types, ...$unassigned_params);
}
$unassigned_stmt->execute();
$unassigned_result = $unassigned_stmt->get_result();
$unassigned_tickets = $unassigned_result->fetch_assoc()['count'];
$unassigned_stmt->close();

// En Proceso (En atención/Espera): status_id = 10 y no eliminados (deleted != 1)
$in_progress_query = "SELECT COUNT(*) as count FROM tickets WHERE status_id = 10 AND deleted != 1";
$in_progress_params = [];
$in_progress_types = '';
if ($has_empresa_column && $user_empresa_id !== null) {
    $in_progress_query .= " AND id_empresa = ?";
    $in_progress_params[] = $user_empresa_id;
    $in_progress_types = 'i';
}
$in_progress_stmt = $conn->prepare($in_progress_query);
if (!empty($in_progress_params)) {
    $in_progress_stmt->bind_param($in_progress_types, ...$in_progress_params);
}
$in_progress_stmt->execute();
$in_progress_result = $in_progress_stmt->get_result();
$in_progress_tickets = $in_progress_result->fetch_assoc()['count'];
$in_progress_stmt->close();


?>

<?php include 'includes/modern-header.php'; ?>
<style>
.body-bg {
    background: #f7f8fa;
    min-height: 100vh;
    width: 100vw;
    overflow-x: hidden;
}
.main-content-boxed {
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
    padding: 2.5rem 2rem 2.5rem 2rem;
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
}
</style>
<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <!-- Header del Dashboard -->
            <div class="d-flex justify-content-between align-items-center mb-6">
                <div>
                    <h1 class="h2 mb-2">
                        Dashboard
                        <!-- <?php echo getEmpresaBadge(); ?> -->
                    </h1>
                    <!-- <p class="text-muted mb-0">
                        Vista general del sistema de tickets
                        <?php if ($user_empresa_id !== null): ?>
                            <br><small>Mostrando estadísticas de tu empresa</small>
                        <?php else: ?>
                            <br><small>Mostrando estadísticas de todas las empresas</small>
                        <?php endif; ?>
                    </p> -->
                </div>
                <div class="d-flex gap-3">
                    <button type="button" class="btn btn-secondary" id="refreshDashboard">
                        <i class="fas fa-sync-alt me-2"></i>Actualizar
                    </button>
                    <button type="button" class="btn btn-secondary" id="printDashboard">
                        <i class="fas fa-print me-2"></i>Imprimir
                    </button>
                    <a href="modules/tickets/new.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Nuevo Ticket
                    </a>
                </div>
            </div>

            <!-- Tarjetas de Estadísticas -->
            <div class="row mb-6">
                <!-- Total de Tickets - Color: #202654 (azul oscuro) -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-sm text-muted mb-1">Total de Tickets</div>
                                    <div class="h3 font-bold" style="color: #202654;"><?php echo $total_tickets; ?></div>
                                </div>
                                <div class="p-3 rounded" style="background-color: rgba(32, 38, 84, 0.1);">
                                    <i class="fas fa-ticket-alt fa-2x" style="color: #202654;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="modules/tickets/all_tickets.php" class="text-decoration-none" style="color: #202654;">
                                Ver todos los tickets <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Tickets Abiertos - Color: #22c55e (verde) -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-sm text-muted mb-1">Tickets Abiertos</div>
                                    <div class="h3 font-bold" style="color: #22c55e;"><?php echo $open_tickets; ?></div>
                                </div>
                                <div class="p-3 rounded" style="background-color: rgba(34, 197, 94, 0.1);">
                                    <i class="fas fa-folder-open fa-2x" style="color: #22c55e;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="modules/tickets/tickets.php?status_id=2" class="text-decoration-none" style="color: #22c55e;">
                                Ver tickets abiertos <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- En Proceso - Color: #ef4444 (rojo) -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-sm text-muted mb-1">En Proceso</div>
                                    <div class="h3 font-bold" style="color: #ef4444;"><?php echo $in_progress_tickets; ?></div>
                                </div>
                                <div class="p-3 rounded" style="background-color: rgba(239, 68, 68, 0.1);">
                                    <i class="fas fa-cogs fa-2x" style="color: #ef4444;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="modules/tickets/tickets.php?status_id=10" class="text-decoration-none" style="color: #ef4444;">
                                Ver en proceso <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Sin Asignar - Color: #f59e0b (naranja/amarillo) -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-sm text-muted mb-1">Sin Asignar</div>
                                    <div class="h3 font-bold" style="color: #f59e0b;"><?php echo $unassigned_tickets; ?></div>
                                </div>
                                <div class="p-3 rounded" style="background-color: rgba(245, 158, 11, 0.1);">
                                    <i class="fas fa-user-clock fa-2x" style="color: #f59e0b;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="modules/tickets/tickets.php?filter=unassigned" class="text-decoration-none" style="color: #f59e0b;">
                                Asignar tickets <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos y Contenido -->
            <div class="row mb-6">
                <!-- Gráfico de Tickets por Estado -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Tickets por Estado</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart" height="450"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Tickets Recientes -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Tickets Recientes</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($recent_result->num_rows > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php while ($row = $recent_result->fetch_assoc()): ?>
                                        <div class="list-group-item border-0">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar me-3">
                                                    <i class="fas fa-ticket-alt"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1">Ticket #<?php echo htmlspecialchars($row['ticket_number'] ?? $row['id']); ?></h6>
                                                            <p class="text-sm text-muted mb-1">
                                                                <?php echo htmlspecialchars(substr($row['description'], 0, 50)) . '...'; ?>
                                                            </p>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($row['requester_name']); ?> • 
                                                                <?php echo date('d/m/Y', strtotime($row['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                        <span class="badge badge-<?php echo getStatusBadgeClass($row['status_name']); ?>">
                                                            <?php echo htmlspecialchars($row['status_name']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No hay tickets recientes</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="modules/tickets/tickets.php" class="btn btn-outline btn-sm w-100">
                                Ver todos los tickets
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php 
            // Cerrar statement de recent_result después de usarlo
            if (isset($recent_stmt)) {
                $recent_stmt->close();
            }
            ?>

            <!-- Gráficos Adicionales -->
            <div class="row">
                <!-- Gráfico de Tickets por Categoría -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Tickets por Categoría</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico de Actividad Diaria -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Actividad de los Últimos 7 Días</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="dailyChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Configuración de Chart.js
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#6b7280';

// Gráfico de Estados
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($status_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($status_data); ?>,
            backgroundColor: <?php echo json_encode($status_colors); ?>,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            }
        }
    }
});

// Gráfico de Categorías
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($category_labels); ?>,
        datasets: [{
            label: 'Tickets',
            data: <?php echo json_encode($category_data); ?>,
            backgroundColor: '#202654',
            borderRadius: 8,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#e5e7eb'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Gráfico Diario
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($daily_labels); ?>,
        datasets: [{
            label: 'Tickets Creados',
            data: <?php echo json_encode($daily_data); ?>,
            borderColor: '#202654',
            backgroundColor: 'rgba(32, 38, 84, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#202654',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#e5e7eb'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Funcionalidad de actualización
document.getElementById('refreshDashboard').addEventListener('click', function() {
    location.reload();
});

// Funcionalidad de impresión
document.getElementById('printDashboard').addEventListener('click', function() {
    window.print();
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
?> 