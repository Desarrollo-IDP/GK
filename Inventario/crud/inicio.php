<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
checkAuth();

$pageTitle = 'Dashboard';
require_once 'includes/header.php';

$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa_usuario en equipos
$check_column = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

$query = "SELECT nombre_cliente, COUNT(id_equipo) AS cantidad_equipos FROM equipos";
$params = [];
$types = '';

if ($user_empresa_id !== null && $has_empresa_usuario_column) {
    $query .= " WHERE id_empresa_usuario = ?";
    $params[] = $user_empresa_id;
    $types = 'i';
}

$query .= " GROUP BY nombre_cliente";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
$result = $conn->query($query);
}

$empresas = [];
while ($row = $result->fetch_assoc()) {
    $empresas[] = $row;
}

$empresas_json = json_encode($empresas);
?>

<div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
    <div class="inicio-header mb-8">
        <div class="flex items-center gap-3 mb-1">
            <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg">
                <i class="fas fa-chart-line text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Flujo de empresas</h1>
                <p class="inicio-header-subtitle">Resumen general de equipos por cliente</p>
            </div>
        </div>
        <div class="inicio-header-date">
            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i'); ?>
        </div>
    </div>

    <div class="inv-card mb-8">
        <div class="inv-card-header">
            <i class="fas fa-chart-area inv-card-icon"></i>
            <h2 class="inv-card-title">Gráfica por cliente</h2>
        </div>
        <div class="h-96 px-4 pb-4">
            <canvas id="equiposChart"></canvas>
        </div>
    </div>

    <div class="flex items-center gap-2 mb-5">
        <i class="fas fa-th-large inv-brand-text text-lg"></i>
        <h2 class="text-lg font-semibold text-gray-800">Dashboard</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php $result->data_seek(0); while ($row = $result->fetch_assoc()): ?>
        <a href="visualizar-equipos.php?cliente=<?php echo urlencode($row['nombre_cliente']); ?>" 
           class="inv-card inv-card-link block p-6 rounded-2xl border border-gray-100 bg-white shadow-md hover:shadow-xl hover:border-gray-200 transition-all">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl inv-brand-bg text-white">
                    <i class="fas fa-building text-lg"></i>
                </div>
                <div class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($row['nombre_cliente']); ?></div>
            </div>
            <div class="text-sm text-gray-500 flex items-center gap-2">
                <i class="fas fa-desktop text-gray-400"></i>
                Equipos: <?php echo $row['cantidad_equipos']; ?>
            </div>
        </a>
        <?php endwhile; ?>
    </div>
</div>

<style>
.inv-page-wrap { max-width: 1600px; }
.inv-brand-bg { background-color: #1E2A3B; }
.inv-brand-text { color: #1E2A3B; }
.inicio-header {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
    padding: 1.25rem 1.75rem;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.inicio-header h1 {
    color: #1E2A3B;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
}
.inicio-header-subtitle {
    margin: 0.25rem 0 0;
    font-size: 0.9rem;
    color: #6b7280;
}
.inicio-header-date {
    color: #6b7280;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.inicio-header-date i {
    color: #1E2A3B;
}
@media (max-width: 768px) {
    .inicio-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
}
.inv-card {
    background: #fff;
    border: 1px solid #f1f5f9;
    border-radius: 1rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    overflow: hidden;
}
.inv-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    background: #fafbfc;
}
.inv-card-icon { color: #1E2A3B; font-size: 1.125rem; }
.inv-card-title { margin: 0; font-size: 1rem; font-weight: 600; color: #334155; }
.inv-card-link:hover { transform: translateY(-2px); }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let equiposChart;
const empresas = <?php echo $empresas_json; ?>;

document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('equiposChart').getContext('2d');

    const datosIniciales = empresas.map(empresa => empresa.cantidad_equipos);

    equiposChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: empresas.map(empresa => empresa.nombre_cliente),
            datasets: [{
                label: 'Cantidad de Equipos',
                data: datosIniciales,
                backgroundColor: 'rgba(30, 42, 59, 0.12)',
                borderColor: '#1E2A3B',
                borderWidth: 2,
                fill: true,
                tension: 0.25,
                pointBackgroundColor: '#1E2A3B',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.06)' },
                    ticks: { color: '#64748b', font: { size: 11 } }
                },
                x: {
                    grid: { color: 'rgba(0, 0, 0, 0.06)' },
                    ticks: { color: '#64748b', font: { size: 11 } }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: '#334155',
                        boxWidth: 12,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: '#1E2A3B',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    padding: 12
                }
            }
        }
    });
});

function actualizarGrafica() {
    const checkboxes = document.querySelectorAll('.empresa-checkbox:checked');
    const empresasSeleccionadas = Array.from(checkboxes).map(checkbox => checkbox.value);

    const datosFiltrados = empresas.map(empresa => {
        return empresasSeleccionadas.includes(empresa.nombre_cliente) ? empresa.cantidad_equipos : 0;
    });

    equiposChart.data.datasets[0].data = datosFiltrados;
    equiposChart.update();
}
</script>

<?php require_once 'includes/footer.php'; ?>