<?php
/**
 * Reportes detallados para el dashboard
 * Este archivo proporciona reportes avanzados y análisis del sistema
 */

include '../../includes/db.php';
include 'stats.php';

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01'); // Primer día del mes actual
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d'); // Hoy
$tipo_membresia = $_GET['tipo_membresia'] ?? '';
$metodo_pago = $_GET['metodo_pago'] ?? '';

// Obtener tipos de membresía para el filtro
try {
    $sql_tipos = "SELECT id, nombre FROM tipos_membresia ORDER BY nombre";
    $stmt_tipos = $pdo->query($sql_tipos);
    $tipos_membresia = $stmt_tipos->fetchAll();
} catch (PDOException $e) {
    $tipos_membresia = [];
}

// Obtener métodos de pago únicos
try {
    $sql_metodos = "SELECT DISTINCT metodo_pago FROM membresias WHERE metodo_pago IS NOT NULL ORDER BY metodo_pago";
    $stmt_metodos = $pdo->query($sql_metodos);
    $metodos_pago = $stmt_metodos->fetchAll();
} catch (PDOException $e) {
    $metodos_pago = [];
}

// Construir consulta base con filtros
function buildFilteredQuery($fecha_inicio, $fecha_fin, $tipo_membresia, $metodo_pago) {
    $where_conditions = ["m.created_at BETWEEN ? AND ?"];
    $params = [$fecha_inicio, $fecha_fin];
    
    if (!empty($tipo_membresia)) {
        $where_conditions[] = "m.tipo_membresia_id = ?";
        $params[] = $tipo_membresia;
    }
    
    if (!empty($metodo_pago)) {
        $where_conditions[] = "m.metodo_pago = ?";
        $params[] = $metodo_pago;
    }
    
    return [
        'where' => implode(' AND ', $where_conditions),
        'params' => $params
    ];
}

// Obtener datos filtrados
$filtros = buildFilteredQuery($fecha_inicio, $fecha_fin, $tipo_membresia, $metodo_pago);

try {
    // Estadísticas del período
    $sql_stats = "SELECT 
                    COUNT(*) as total_membresias,
                    COUNT(DISTINCT m.cliente_id) as clientes_unicos,
                    COALESCE(SUM(tm.precio), 0) as ingresos_totales,
                    COALESCE(AVG(tm.precio), 0) as precio_promedio
                   FROM membresias m 
                   JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                   WHERE {$filtros['where']}";
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute($filtros['params']);
    $estadisticas = $stmt_stats->fetch();
    
    // Membresías por día en el período
    $sql_diario = "SELECT 
                     DATE(m.created_at) as fecha,
                     COUNT(*) as cantidad,
                     COALESCE(SUM(tm.precio), 0) as ingresos
                    FROM membresias m 
                    JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                    WHERE {$filtros['where']}
                    GROUP BY DATE(m.created_at) 
                    ORDER BY fecha";
    $stmt_diario = $pdo->prepare($sql_diario);
    $stmt_diario->execute($filtros['params']);
    $datos_diarios = $stmt_diario->fetchAll();
    
    // Top clientes por gasto
    $sql_top_clientes = "SELECT 
                          c.nombre, c.apellido_paterno, c.apellido_materno,
                          COUNT(m.id) as membresias_count,
                          COALESCE(SUM(tm.precio), 0) as total_gastado
                         FROM membresias m 
                         JOIN clientes c ON m.cliente_id = c.id 
                         JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                         WHERE {$filtros['where']}
                         GROUP BY c.id, c.nombre, c.apellido_paterno, c.apellido_materno 
                         ORDER BY total_gastado DESC 
                         LIMIT 10";
    $stmt_top_clientes = $pdo->prepare($sql_top_clientes);
    $stmt_top_clientes->execute($filtros['params']);
    $top_clientes = $stmt_top_clientes->fetchAll();
    
    // Distribución por tipo de membresía
    $sql_distribucion = "SELECT 
                          tm.nombre as tipo_membresia,
                          COUNT(*) as cantidad,
                          COALESCE(SUM(tm.precio), 0) as ingresos_totales
                         FROM membresias m 
                         JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                         WHERE {$filtros['where']}
                         GROUP BY tm.id, tm.nombre 
                         ORDER BY cantidad DESC";
    $stmt_distribucion = $pdo->prepare($sql_distribucion);
    $stmt_distribucion->execute($filtros['params']);
    $distribucion_tipos = $stmt_distribucion->fetchAll();
    
    // Distribución por método de pago
    $sql_metodos_pago = "SELECT 
                          m.metodo_pago,
                          COUNT(*) as cantidad,
                          COALESCE(SUM(tm.precio), 0) as ingresos_totales
                         FROM membresias m 
                         JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                         WHERE {$filtros['where']}
                         GROUP BY m.metodo_pago 
                         ORDER BY cantidad DESC";
    $stmt_metodos_pago = $pdo->prepare($sql_metodos_pago);
    $stmt_metodos_pago->execute($filtros['params']);
    $distribucion_metodos = $stmt_metodos_pago->fetchAll();
    
} catch (PDOException $e) {
    die("Error al obtener reportes: " . $e->getMessage());
}

// Preparar datos para gráficas
$fechas = [];
$cantidades = [];
$ingresos = [];

foreach ($datos_diarios as $dato) {
    $fechas[] = date('d/m', strtotime($dato['fecha']));
    $cantidades[] = $dato['cantidad'];
    $ingresos[] = $dato['ingresos'];
}

// Colores para gráficas
$colores = [
    'rgba(102, 126, 234, 0.8)',
    'rgba(40, 167, 69, 0.8)',
    'rgba(220, 53, 69, 0.8)',
    'rgba(255, 193, 7, 0.8)',
    'rgba(23, 162, 184, 0.8)',
    'rgba(111, 66, 193, 0.8)',
    'rgba(253, 126, 20, 0.8)',
    'rgba(108, 117, 125, 0.8)'
];
?>

<?php include '../../includes/header.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.report-filters {
    background: white;
    border-radius: 0.75rem;
    padding: 1.5rem;
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.report-card {
    background: white;
    border-radius: 0.75rem;
    padding: 1.5rem;
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.report-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.report-title i {
    margin-right: 0.5rem;
    color: #5e72e4;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-item {
    text-align: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 0.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-responsive {
    border-radius: 0.5rem;
    overflow: hidden;
}

.table th {
    background: #f8f9fa;
    border: none;
    font-weight: 600;
    color: #2c3e50;
}

.table td {
    vertical-align: middle;
}

.badge-custom {
    padding: 0.5rem 1rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 600;
}

.badge-stripe {
    background: #6772e5;
    color: white;
}

.badge-cash {
    background: #28a745;
    color: white;
}

.badge-transfer {
    background: #17a2b8;
    color: white;
}
</style>

<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/sidebar.php'; ?>
        <main class="main-content">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h2 mb-2">Reportes Detallados</h1>
                    <p class="text-muted mb-0">Análisis completo de membresías e ingresos</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-2"></i>Exportar PDF
                    </button>
                    <button class="btn btn-outline-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-2"></i>Exportar Excel
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="report-filters">
                <h5 class="report-title">
                    <i class="fas fa-filter"></i>
                    Filtros de Reporte
                </h5>
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                               value="<?= htmlspecialchars($fecha_inicio) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                               value="<?= htmlspecialchars($fecha_fin) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="tipo_membresia" class="form-label">Tipo de Membresía</label>
                        <select class="form-select" id="tipo_membresia" name="tipo_membresia">
                            <option value="">Todos los tipos</option>
                            <?php foreach ($tipos_membresia as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>" <?= $tipo_membresia == $tipo['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="metodo_pago" class="form-label">Método de Pago</label>
                        <select class="form-select" id="metodo_pago" name="metodo_pago">
                            <option value="">Todos los métodos</option>
                            <?php foreach ($metodos_pago as $metodo): ?>
                                <option value="<?= $metodo['metodo_pago'] ?>" <?= $metodo_pago == $metodo['metodo_pago'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($metodo['metodo_pago'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Filtrar
                        </button>
                        <a href="reports.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Estadísticas del Período -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($estadisticas['total_membresias']) ?></div>
                    <div class="stat-label">Total Membresías</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($estadisticas['clientes_unicos']) ?></div>
                    <div class="stat-label">Clientes Únicos</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">$<?= number_format($estadisticas['ingresos_totales'], 2) ?></div>
                    <div class="stat-label">Ingresos Totales</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">$<?= number_format($estadisticas['precio_promedio'], 2) ?></div>
                    <div class="stat-label">Precio Promedio</div>
                </div>
            </div>

            <!-- Gráficas -->
            <div class="row g-4 mb-4">
                <!-- Gráfica de Membresías por Día -->
                <div class="col-xl-6">
                    <div class="report-card">
                        <h5 class="report-title">
                            <i class="fas fa-chart-bar"></i>
                            Membresías por Día
                        </h5>
                        <canvas id="membresiasChart" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Gráfica de Ingresos por Día -->
                <div class="col-xl-6">
                    <div class="report-card">
                        <h5 class="report-title">
                            <i class="fas fa-chart-line"></i>
                            Ingresos por Día
                        </h5>
                        <canvas id="ingresosChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Distribuciones -->
            <div class="row g-4 mb-4">
                <!-- Distribución por Tipo de Membresía -->
                <div class="col-xl-6">
                    <div class="report-card">
                        <h5 class="report-title">
                            <i class="fas fa-pie-chart"></i>
                            Distribución por Tipo de Membresía
                        </h5>
                        <canvas id="distribucionTiposChart" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Distribución por Método de Pago -->
                <div class="col-xl-6">
                    <div class="report-card">
                        <h5 class="report-title">
                            <i class="fas fa-credit-card"></i>
                            Distribución por Método de Pago
                        </h5>
                        <canvas id="distribucionMetodosChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tablas de Datos -->
            <div class="row g-4">
                <!-- Top Clientes -->
                <div class="col-xl-8">
                    <div class="report-card">
                        <h5 class="report-title">
                            <i class="fas fa-trophy"></i>
                            Top 10 Clientes por Gasto
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Membresías</th>
                                        <th>Total Gastado</th>
                                        <th>Promedio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_clientes as $cliente): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido_paterno'] . ' ' . $cliente['apellido_materno']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= $cliente['membresias_count'] ?></span>
                                            </td>
                                            <td>
                                                <strong>$<?= number_format($cliente['total_gastado'], 2) ?></strong>
                                            </td>
                                            <td>
                                                $<?= number_format($cliente['total_gastado'] / $cliente['membresias_count'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Resumen de Distribuciones -->
                <div class="col-xl-4">
                    <div class="report-card">
                        <h5 class="report-title">
                            <i class="fas fa-list"></i>
                            Resumen de Distribuciones
                        </h5>
                        
                        <h6 class="mt-3 mb-2">Por Tipo de Membresía</h6>
                        <?php foreach ($distribucion_tipos as $tipo): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?= htmlspecialchars($tipo['tipo_membresia']) ?></span>
                                <span class="badge bg-info"><?= $tipo['cantidad'] ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        
                        <h6 class="mt-3 mb-2">Por Método de Pago</h6>
                        <?php foreach ($distribucion_metodos as $metodo): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?= htmlspecialchars(ucfirst($metodo['metodo_pago'])) ?></span>
                                <span class="badge bg-success"><?= $metodo['cantidad'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</div>

<script>
// Gráfica de Membresías por Día
const ctxMembresias = document.getElementById('membresiasChart').getContext('2d');
new Chart(ctxMembresias, {
    type: 'bar',
    data: {
        labels: <?= json_encode($fechas) ?>,
        datasets: [{
            label: 'Membresías Creadas',
            data: <?= json_encode($cantidades) ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.8)',
            borderColor: 'rgba(102, 126, 234, 1)',
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
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Gráfica de Ingresos por Día
const ctxIngresos = document.getElementById('ingresosChart').getContext('2d');
new Chart(ctxIngresos, {
    type: 'line',
    data: {
        labels: <?= json_encode($fechas) ?>,
        datasets: [{
            label: 'Ingresos ($)',
            data: <?= json_encode($ingresos) ?>,
            backgroundColor: 'rgba(40, 167, 69, 0.2)',
            borderColor: 'rgba(40, 167, 69, 1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Gráfica de Distribución por Tipo de Membresía
const ctxDistribucionTipos = document.getElementById('distribucionTiposChart').getContext('2d');
new Chart(ctxDistribucionTipos, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($distribucion_tipos, 'tipo_membresia')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($distribucion_tipos, 'cantidad')) ?>,
            backgroundColor: <?= json_encode(array_slice($colores, 0, count($distribucion_tipos))) ?>,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Gráfica de Distribución por Método de Pago
const ctxDistribucionMetodos = document.getElementById('distribucionMetodosChart').getContext('2d');
new Chart(ctxDistribucionMetodos, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map('ucfirst', array_column($distribucion_metodos, 'metodo_pago'))) ?>,
        datasets: [{
            data: <?= json_encode(array_column($distribucion_metodos, 'cantidad')) ?>,
            backgroundColor: <?= json_encode(array_slice($colores, 0, count($distribucion_metodos))) ?>,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Funciones de exportación
function exportToPDF() {
    alert('Función de exportación a PDF en desarrollo');
}

function exportToExcel() {
    alert('Función de exportación a Excel en desarrollo');
}
</script>

<?php include '../../includes/footer.php'; ?> 