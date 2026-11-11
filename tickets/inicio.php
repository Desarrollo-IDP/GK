<?php
include 'includes/common.php';
include 'conexion/db.php';

$total_query = "SELECT COUNT(*) as total FROM tickets";
$total_result = $conn->query($total_query);
$total_tickets = $total_result->fetch_assoc()['total'];

$status_query = "SELECT s.name, COUNT(t.id) as count 
                FROM tickets t 
                JOIN statuses s ON t.status_id = s.id 
                GROUP BY t.status_id";
$status_result = $conn->query($status_query);
$category_query = "SELECT c.name, COUNT(t.id) as count 
                  FROM tickets t 
                  JOIN categories c ON t.category_id = c.id 
                  GROUP BY t.category_id";
$category_result = $conn->query($category_query);
 
$technician_query = "SELECT tech.name, COUNT(t.id) as count 
                    FROM tickets t 
                    JOIN technicians tech ON t.technician_id = tech.id 
                    GROUP BY t.technician_id";
$technician_result = $conn->query($technician_query);

$recent_query = "SELECT t.id, t.created_at, t.description, s.name as status_name, 
                c.name as category_name, r.name as requester_name 
                FROM tickets t
                LEFT JOIN statuses s ON t.status_id = s.id
                LEFT JOIN categories c ON t.category_id = c.id
                LEFT JOIN requesters r ON t.requester_id = r.id
                ORDER BY t.created_at DESC LIMIT 5";
$recent_result = $conn->query($recent_query);

$daily_query = "SELECT DATE(created_at) as date, COUNT(*) as count 
               FROM tickets 
               WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
               GROUP BY DATE(created_at)";
$daily_result = $conn->query($daily_query);

$status_labels = [];
$status_data = [];
while ($row = $status_result->fetch_assoc()) {
    $status_labels[] = $row['name'];
    $status_data[] = $row['count'];
}

$category_labels = [];
$category_data = [];
while ($row = $category_result->fetch_assoc()) {
    $category_labels[] = $row['name'];
    $category_data[] = $row['count'];
}

$technician_labels = [];
$technician_data = [];
while ($row = $technician_result->fetch_assoc()) {
    $technician_labels[] = $row['name'];
    $technician_data[] = $row['count'];
}

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

$daily_data = array_values($dates);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/responsive-sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshDashboard">
                                <i class="fas fa-sync-alt"></i> Actualizar
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="printDashboard">
                                <i class="fas fa-print"></i> Imprimir
                            </button>
                        </div>
                        <a href="modules/tickets/new.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Nuevo Ticket
                        </a>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Tickets</h6>
                                        <h2 class="mb-0"><?php echo $total_tickets; ?></h2>
                                    </div>
                                    <i class="fas fa-ticket-alt fa-3x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="modules/tickets/tickets.php?filter=unassigned" class="text-white stretched-link">Ver detalles</a>
                                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    $open_query = "SELECT COUNT(*) as count FROM tickets WHERE status_id IN (SELECT id FROM statuses WHERE name IN ('Abierto', 'Nuevo', 'En Progreso', 'Pendiente'))";
                    $open_result = $conn->query($open_query);
                    $open_tickets = $open_result->fetch_assoc()['count'];
                    ?>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Tickets Abiertos</h6>
                                        <h2 class="mb-0"><?php echo $open_tickets; ?></h2>
                                    </div>
                                    <i class="fas fa-clock fa-3x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a class="small text-white stretched-link" href="modules/tickets/tickets.php?status=Abierto">Ver detalles</a>
                                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    $closed_query = "SELECT COUNT(*) as count FROM tickets WHERE closed_at IS NOT NULL";
                    $closed_result = $conn->query($closed_query);
                    $closed_tickets = $closed_result->fetch_assoc()['count'];
                    ?>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Tickets Cerrados</h6>
                                        <h2 class="mb-0"><?php echo $closed_tickets; ?></h2>
                                    </div>
                                    <i class="fas fa-check-circle fa-3x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="modules/tickets/closed_tickets.php" class="text-white stretched-link">Ver detalles</a>
                                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    $unassigned_query = "SELECT COUNT(*) as count FROM tickets WHERE technician_id IS NULL";
                    $unassigned_result = $conn->query($unassigned_query);
                    $unassigned_tickets = $unassigned_result->fetch_assoc()['count'];
                    ?>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Sin Asignar</h6>
                                        <h2 class="mb-0"><?php echo $unassigned_tickets; ?></h2>
                                    </div>
                                    <i class="fas fa-user-times fa-3x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="modules/tickets/tickets.php?status=Sin Asignar" class="text-white stretched-link">Ver detalles</a>
                                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title">Tickets por Estado</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title">Tickets por Categoría</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title">Tickets Creados (Últimos 7 días)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="dailyChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title">Tickets por Técnico</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="technicianChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

<!--                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-ticket-alt me-1"></i>
                        Tickets Abiertos
                    </div>
                    <div class="card-body">
                        <p class="mb-0">Tickets que requieren atención.</p>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a class="small text-white stretched-link" href="modules/tickets/tickets.php?status=Abierto">Ver detalles</a>
                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                    </div>
                </div> -->
                <div class="card-body d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Tickets Recientes</h5>
                    <a href="modules/tickets/tickets.php" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Descripción</th>
                                    <th>Solicitante</th>
                                    <th>Categoría</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_result->num_rows > 0): ?>
                                    <?php while ($row = $recent_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['id']; ?></td>
                                            <td><?php echo substr(htmlspecialchars($row['description']), 0, 50) . (strlen($row['description']) > 50 ? '...' : ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['requester_name'] ?? 'No asignado'); ?></td>
                                            <td><?php echo htmlspecialchars($row['category_name'] ?? 'No asignada'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusColor($row['status_name']); ?>">
                                                    <?php echo htmlspecialchars($row['status_name'] ?? 'Sin estado'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                            <td>
                                                <a href="modules/tickets/view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="modules/tickets/edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No hay tickets recientes</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script>
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_data); ?>,
                    backgroundColor: [
                        '#0d6efd', 
                        '#6c757d', 
                        '#198754', 
                        '#dc3545', 
                        '#ffffff', 
                        '#0dcaf0', 
                        '#6610f2', 
                        '#fd7e14', 
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });


        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($category_labels); ?>,
                datasets: [{
                    label: 'Tickets',
                    data: <?php echo json_encode($category_data); ?>,
                    backgroundColor: '#0d6efd',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

      
        const technicianCtx = document.getElementById('technicianChart').getContext('2d');
        const technicianChart = new Chart(technicianCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($technician_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($technician_data); ?>,
                    backgroundColor: [
                        '#0d6efd', 
                        '#198754', 
                        '#ffc107', 
                        '#dc3545', 
                        '#6c757d', 
                        '#0dcaf0', 
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($daily_labels); ?>,
                datasets: [{
                    label: 'Tickets Creados',
                    data: <?php echo json_encode($daily_data); ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.2)',
                    borderColor: '#0d6efd',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        document.getElementById('refreshDashboard').addEventListener('click', function() {
            location.reload();
        });

        document.getElementById('printDashboard').addEventListener('click', function() {
            window.print();
        });
    </script>
</body>
</html>

<?php
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'abierto':
        case 'nuevo':
            return 'primary';
        case 'en progreso':
        case 'asignado':
            return 'info';
        case 'pendiente':
            return 'warning';
        case 'resuelto':
            return 'success';
        case 'cerrado':
            return 'secondary';
        case 'cancelado':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>