<?php
// Define current page for active menu highlighting
$current_page = 'dashboard';

include 'includes/db.php';
include 'includes/config.php';



// Inicializar configuración y sesión
initConfig();

// Debug: verificar estado de la sesión
//error_log("DEBUG: Dashboard - Session status: " . session_status());
//error_log("DEBUG: Dashboard - Session ID: " . session_id());
//error_log("DEBUG: Dashboard - Session data: " . print_r($_SESSION, true));

// Obtener la empresa del usuario logueado
$empresa_id = getCurrentUserEmpresaIdOrDefault();

// Verificar y corregir la sesión del usuario si es necesario
if (isAuthenticated() && isset($_SESSION['user']['id'])) {
    global $pdo;
    try {
        // Verificar si el empresa_id en la sesión es correcto
        $stmt = $pdo->prepare("SELECT empresa_id FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['empresa_id']) && $result['empresa_id'] !== null) {
            $db_empresa_id = (int)$result['empresa_id'];
            $session_empresa_id = isset($_SESSION['user']['empresa_id']) ? (int)$_SESSION['user']['empresa_id'] : null;
            
            // Si el empresa_id en la sesión no coincide con el de la BD, actualizarlo
            if ($session_empresa_id !== $db_empresa_id) {
                $_SESSION['user']['empresa_id'] = $db_empresa_id;
                $empresa_id = $db_empresa_id;
                error_log("DEBUG: Dashboard - Updated session empresa_id from " . $session_empresa_id . " to " . $db_empresa_id);
            }
        }
    } catch (Exception $e) {
        error_log("ERROR: Dashboard - Error verifying user empresa_id: " . $e->getMessage());
    }
}

// Si no hay empresa_id, usar 1 como valor por defecto para evitar errores
if (!$empresa_id) {
    $empresa_id = 1;
    error_log("WARNING: Dashboard - Usando empresa_id por defecto: " . $empresa_id);
}

// Debug: mostrar información de la empresa filtrada
error_log("DEBUG: Dashboard - empresa_id filtrado: " . $empresa_id);

// Obtener información de la empresa para mostrar en el dashboard
try {
    $stmt_empresa = $pdo->prepare("SELECT nombre FROM empresas WHERE id = ?");
    $stmt_empresa->execute([$empresa_id]);
    $empresa_info = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
    $nombre_empresa = $empresa_info ? $empresa_info['nombre'] : 'Empresa';
} catch (PDOException $e) {
    error_log("ERROR: Dashboard - Error obteniendo información de empresa: " . $e->getMessage());
    $nombre_empresa = 'Empresa';
}

// Obtener estadísticas generales filtradas por empresa
try {
    // Total de membresías
    $sql_total = "SELECT COUNT(*) as total FROM membresias WHERE empresa_id = ?";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute([$empresa_id]);
    $total_membresias = $stmt_total->fetch()['total'];
    
    // Membresías activas
    $sql_activas = "SELECT COUNT(*) as activas FROM membresias WHERE empresa_id = ? AND fecha_fin >= CURDATE()";
    $stmt_activas = $pdo->prepare($sql_activas);
    $stmt_activas->execute([$empresa_id]);
    $membresias_activas = $stmt_activas->fetch()['activas'];
    
    // Membresías expiradas
    $sql_expiradas = "SELECT COUNT(*) as expiradas FROM membresias WHERE empresa_id = ? AND fecha_fin < CURDATE()";
    $stmt_expiradas = $pdo->prepare($sql_expiradas);
    $stmt_expiradas->execute([$empresa_id]);
    $membresias_expiradas = $stmt_expiradas->fetch()['expiradas'];
    
    // Membresías por expirar (próximos 7 días)
    $sql_por_expirar = "SELECT COUNT(*) as por_expirar FROM membresias WHERE empresa_id = ? AND fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    $stmt_por_expirar = $pdo->prepare($sql_por_expirar);
    $stmt_por_expirar->execute([$empresa_id]);
    $membresias_por_expirar = $stmt_por_expirar->fetch()['por_expirar'];
    
    // Ingresos del día actual
    $sql_ingresos_hoy = "SELECT COALESCE(SUM(tm.precio), 0) as ingresos_hoy 
                         FROM membresias m 
                         JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                         WHERE m.empresa_id = ? AND DATE(m.created_at) = CURDATE()";
    $stmt_ingresos_hoy = $pdo->prepare($sql_ingresos_hoy);
    $stmt_ingresos_hoy->execute([$empresa_id]);
    $ingresos_hoy = $stmt_ingresos_hoy->fetch()['ingresos_hoy'];
    
    // Ingresos del mes actual
    $sql_ingresos_mes = "SELECT COALESCE(SUM(tm.precio), 0) as ingresos_mes 
                         FROM membresias m 
                         JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                         WHERE m.empresa_id = ? AND MONTH(m.created_at) = MONTH(CURDATE()) 
                         AND YEAR(m.created_at) = YEAR(CURDATE())";
    $stmt_ingresos_mes = $pdo->prepare($sql_ingresos_mes);
    $stmt_ingresos_mes->execute([$empresa_id]);
    $ingresos_mes = $stmt_ingresos_mes->fetch()['ingresos_mes'];
    
    // Membresías creadas hoy
    $sql_membresias_hoy = "SELECT COUNT(*) as hoy FROM membresias WHERE empresa_id = ? AND DATE(created_at) = CURDATE()";
    $stmt_membresias_hoy = $pdo->prepare($sql_membresias_hoy);
    $stmt_membresias_hoy->execute([$empresa_id]);
    $membresias_hoy = $stmt_membresias_hoy->fetch()['hoy'];
    
    // Membresías de los últimos 7 días
    $sql_ultimos_7_dias = "SELECT DATE(created_at) as fecha, COUNT(*) as cantidad 
                           FROM membresias 
                           WHERE empresa_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                           GROUP BY DATE(created_at) 
                           ORDER BY fecha";
    $stmt_ultimos_7_dias = $pdo->prepare($sql_ultimos_7_dias);
    $stmt_ultimos_7_dias->execute([$empresa_id]);
    $datos_ultimos_7_dias = $stmt_ultimos_7_dias->fetchAll();
    
    // Ingresos de los últimos 7 días
    $sql_ingresos_7_dias = "SELECT DATE(m.created_at) as fecha, COALESCE(SUM(tm.precio), 0) as ingresos 
                            FROM membresias m 
                            JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                            WHERE m.empresa_id = ? AND m.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                            GROUP BY DATE(m.created_at) 
                            ORDER BY fecha";
    $stmt_ingresos_7_dias = $pdo->prepare($sql_ingresos_7_dias);
    $stmt_ingresos_7_dias->execute([$empresa_id]);
    $datos_ingresos_7_dias = $stmt_ingresos_7_dias->fetchAll();
    
    // Top 5 tipos de membresía más populares (solo de la empresa del usuario)
    $sql_top_tipos = "SELECT tm.nombre, COUNT(*) as cantidad 
                      FROM membresias m 
                      JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                      WHERE m.empresa_id = ? AND tm.empresa_id = ?
                      GROUP BY tm.id, tm.nombre 
                      ORDER BY cantidad DESC 
                      LIMIT 5";
    $stmt_top_tipos = $pdo->prepare($sql_top_tipos);
    $stmt_top_tipos->execute([$empresa_id, $empresa_id]);
    $top_tipos = $stmt_top_tipos->fetchAll();
    
    // Últimas membresías creadas (solo de la empresa del usuario)
    $sql_ultimas = "SELECT m.id, c.nombre, c.apellido_paterno, tm.nombre as tipo_membresia, 
                           tm.precio, m.fecha_inicio, m.fecha_fin, m.created_at
                    FROM membresias m 
                    JOIN clientes c ON m.cliente_id = c.id 
                    JOIN tipos_membresia tm ON m.tipo_membresia_id = tm.id 
                    WHERE m.empresa_id = ? AND c.empresa_id = ? AND tm.empresa_id = ?
                    ORDER BY m.created_at DESC 
                    LIMIT 10";
    $stmt_ultimas = $pdo->prepare($sql_ultimas);
    $stmt_ultimas->execute([$empresa_id, $empresa_id, $empresa_id]);
    $ultimas_membresias = $stmt_ultimas->fetchAll();
    
} catch (PDOException $e) {
    die("Error al obtener estadísticas: " . $e->getMessage());
}

// Preparar datos para gráficas
$fechas_7_dias = [];
$cantidades_7_dias = [];
$ingresos_7_dias = [];

// Llenar datos para los últimos 7 días
for ($i = 6; $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime("-$i days"));
    $fechas_7_dias[] = date('d/m', strtotime($fecha));
    
    $cantidad = 0;
    $ingreso = 0;
    
    foreach ($datos_ultimos_7_dias as $dato) {
        if ($dato['fecha'] == $fecha) {
            $cantidad = $dato['cantidad'];
            break;
        }
    }
    
    foreach ($datos_ingresos_7_dias as $dato) {
        if ($dato['fecha'] == $fecha) {
            $ingreso = $dato['ingresos'];
            break;
        }
    }
    
    $cantidades_7_dias[] = $cantidad;
    $ingresos_7_dias[] = $ingreso;
}
?>
<?php include 'includes/header.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.dashboard-stats {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 1rem;
    padding: 2rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 0.75rem;
    padding: 1.5rem;
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-card .stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-card .stat-label {
    color: #6c757d;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.chart-container {
    background: white;
    border-radius: 0.75rem;
    padding: 1.5rem;
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.chart-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.chart-title i {
    margin-right: 0.5rem;
    color: #5e72e4;
}

.recent-memberships {
    background: white;
    border-radius: 0.75rem;
    padding: 1.5rem;
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.1);
}

.membership-item {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.membership-item:last-child {
    border-bottom: none;
}

.membership-info h6 {
    margin-bottom: 0.25rem;
    color: #2c3e50;
}

.membership-info p {
    margin-bottom: 0;
    color: #6c757d;
    font-size: 0.875rem;
}

.membership-status {
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-expired {
    background-color: #f8d7da;
    color: #721c24;
}

.status-pending {
    background-color: #fff3cd;
    color: #856404;
}

.top-types-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.top-types-list li {
    padding: 0.75rem 0;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.top-types-list li:last-child {
    border-bottom: none;
}

.type-name {
    font-weight: 500;
    color: #2c3e50;
}

.type-count {
    background: #5e72e4;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 600;
}

@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 1rem;
    }
    
    .chart-container {
        margin-bottom: 1rem;
    }
}
</style>

<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <!-- Header del Dashboard -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h2 mb-2">Dashboard - <?= htmlspecialchars($nombre_empresa) ?></h1>
                    <!--<p class="text-muted mb-0">Resumen general del sistema de membresías para <?= htmlspecialchars($nombre_empresa) ?></p>
                    <small class="text-info">
                        <i class="fas fa-building me-1"></i>
                        Empresa ID: <?= $empresa_id ?> | 
                        <i class="fas fa-user me-1"></i>
                        Usuario: <?= htmlspecialchars($_SESSION['user']['nombre'] ?? 'N/A') ?>
                    </small>-->
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Imprimir Reporte
                    </button>
                </div>
            </div>

            <!-- Estadísticas Principales -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?= number_format($total_membresias) ?></div>
                        <div class="stat-label">Total de Membresías</div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?= number_format($membresias_activas) ?></div>
                        <div class="stat-label">Membresías Activas</div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-value"><?= number_format($membresias_expiradas) ?></div>
                        <div class="stat-label">Membresías Expiradas</div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?= number_format($membresias_por_expirar) ?></div>
                        <div class="stat-label">Por Expirar (7 días)</div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas de Ingresos -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-value">$<?= number_format($ingresos_hoy, 2) ?></div>
                        <div class="stat-label">Ingresos Hoy</div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%); color: white;">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-value">$<?= number_format($ingresos_mes, 2) ?></div>
                        <div class="stat-label">Ingresos del Mes</div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%); color: white;">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="stat-value"><?= number_format($membresias_hoy) ?></div>
                        <div class="stat-label">Nuevas Hoy</div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%); color: white;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-value"><?= number_format(array_sum($cantidades_7_dias)) ?></div>
                        <div class="stat-label">Últimos 7 Días</div>
                    </div>
                </div>
            </div>

            <!-- Gráficas -->
            <div class="row g-4 mb-4">
                <!-- Gráfica de Membresías Últimos 7 Días -->
                <div class="col-xl-6">
                    <div class="chart-container">
                        <h5 class="chart-title">
                            <i class="fas fa-chart-bar"></i>
                            Membresías Últimos 7 Días
                        </h5>
                        <canvas id="membresiasChart" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Gráfica de Ingresos Últimos 7 Días -->
                <div class="col-xl-6">
                    <div class="chart-container">
                        <h5 class="chart-title">
                            <i class="fas fa-chart-line"></i>
                            Ingresos Últimos 7 Días
                        </h5>
                        <canvas id="ingresosChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Información Adicional -->
            <div class="row g-4">
                <!-- Últimas Membresías -->
                <div class="col-xl-8">
                    <div class="recent-memberships">
                        <h5 class="chart-title">
                            <i class="fas fa-clock"></i>
                            Últimas Membresías Creadas
                        </h5>
                        <?php if (empty($ultimas_membresias)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-2">No hay membresías registradas para <?= htmlspecialchars($nombre_empresa) ?> aún.</p>
                                <a href="modules/membresias/add.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>Crear Primera Membresía
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($ultimas_membresias as $membresia): ?>
                                <div class="membership-item">
                                    <div class="membership-info">
                                        <h6><?= htmlspecialchars($membresia['nombre'] . ' ' . $membresia['apellido_paterno']) ?></h6>
                                        <p>
                                            <strong><?= htmlspecialchars($membresia['tipo_membresia']) ?></strong> - 
                                            $<?= number_format($membresia['precio'], 2) ?> | 
                                            <small class="text-muted">
                                                <?= date('d/m/Y', strtotime($membresia['created_at'])) ?>
                                            </small>
                                        </p>
                                    </div>
                                    <div class="membership-status <?= 
                                        $membresia['fecha_fin'] < date('Y-m-d') ? 'status-expired' : 
                                        ($membresia['fecha_inicio'] > date('Y-m-d') ? 'status-pending' : 'status-active') 
                                    ?>">
                                        <?= 
                                            $membresia['fecha_fin'] < date('Y-m-d') ? 'Expirada' : 
                                            ($membresia['fecha_inicio'] > date('Y-m-d') ? 'Pendiente' : 'Activa') 
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Top Tipos de Membresía -->
                <div class="col-xl-4">
                    <div class="chart-container">
                        <h5 class="chart-title">
                            <i class="fas fa-trophy"></i>
                            Tipos Más Populares
                        </h5>
                        <?php if (empty($top_tipos)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tags fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-2">No hay tipos de membresía populares para <?= htmlspecialchars($nombre_empresa) ?> aún.</p>
                                <a href="modules/tipos_membresia/add.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>Crear Tipo de Membresía
                                </a>
                            </div>
                        <?php else: ?>
                            <ul class="top-types-list">
                                <?php foreach ($top_tipos as $tipo): ?>
                                    <li>
                                        <span class="type-name"><?= htmlspecialchars($tipo['nombre']) ?></span>
                                        <span class="type-count"><?= $tipo['cantidad'] ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</div>

<script>
// Gráfica de Membresías Últimos 7 Días
const ctxMembresias = document.getElementById('membresiasChart').getContext('2d');
new Chart(ctxMembresias, {
    type: 'bar',
    data: {
        labels: <?= json_encode($fechas_7_dias) ?>,
        datasets: [{
            label: 'Membresías Creadas',
            data: <?= json_encode($cantidades_7_dias) ?>,
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

// Gráfica de Ingresos Últimos 7 Días
const ctxIngresos = document.getElementById('ingresosChart').getContext('2d');
new Chart(ctxIngresos, {
    type: 'line',
    data: {
        labels: <?= json_encode($fechas_7_dias) ?>,
        datasets: [{
            label: 'Ingresos ($)',
            data: <?= json_encode($ingresos_7_dias) ?>,
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
</script>

<?php include 'includes/footer.php'; ?> 