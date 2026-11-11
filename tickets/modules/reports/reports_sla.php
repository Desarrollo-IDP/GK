<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

checkAuth();

// Solo permitir acceso vía POST desde el formulario de filtros
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reports.php');
    exit;
}

if (empty($_POST['start_date']) || empty($_POST['end_date'])) {
    header('Location: reports.php');
    exit;
}

// Obtener empresa del usuario actual
$user_empresa_id = getCurrentUserEmpresaId();

// Parámetros del formulario
$start_date_input = $_POST['start_date'];
$end_date_input   = $_POST['end_date'];
$start_date = $start_date_input . ' 00:00:00';
$end_date   = $end_date_input . ' 23:59:59';
$technician_id = !empty($_POST['technician_id']) ? intval($_POST['technician_id']) : null;
$empresa_id = !empty($_POST['empresa_id']) ? intval($_POST['empresa_id']) : null;

// Validar que el técnico seleccionado pertenezca a la empresa del usuario
if ($technician_id && $user_empresa_id !== null) {
    $check_tech = $conn->prepare("SELECT id FROM technicians WHERE id = ? AND id_empresa = ?");
    $check_tech->bind_param("ii", $technician_id, $user_empresa_id);
    $check_tech->execute();
    $check_tech_result = $check_tech->get_result();
    if ($check_tech_result->num_rows === 0) {
        $technician_id = null;
    }
    $check_tech->close();
}

// Validar que la empresa seleccionada esté relacionada con tickets de la empresa del usuario
if ($empresa_id && $user_empresa_id !== null) {
    $check_emp = $conn->prepare("SELECT DISTINCT e.id_empresa
                                 FROM empresas e
                                 INNER JOIN requesters r ON e.id_empresa = r.company_id
                                 INNER JOIN tickets t ON r.id = t.requester_id
                                 WHERE e.id_empresa = ? AND t.id_empresa = ?
                                 LIMIT 1");
    $check_emp->bind_param("ii", $empresa_id, $user_empresa_id);
    $check_emp->execute();
    $check_emp_result = $check_emp->get_result();
    if ($check_emp_result->num_rows === 0) {
        $empresa_id = null;
    }
    $check_emp->close();
}

// Consulta base para SLA
$query = "SELECT
    t.id as ticket_id,
    t.ticket_number,
    t.created_at,
    t.closed_at,
    e.nombre as empresa_nombre,
    r.name as requester_name,
    s.name as status_name,
    TIMESTAMPDIFF(MINUTE, t.created_at, COALESCE(t.closed_at, NOW())) as tiempo_resolucion_minutos
FROM tickets t
LEFT JOIN requesters r ON t.requester_id = r.id
LEFT JOIN empresas e ON r.company_id = e.id_empresa
LEFT JOIN statuses s ON t.status_id = s.id
WHERE t.created_at BETWEEN ? AND ?
AND (t.deleted IS NULL OR t.deleted = 0)";

$params = [$start_date, $end_date];
$types = "ss";

// Filtro por empresa usando el helper existente
$query = addEmpresaFilter($query, 't.id_empresa', $params, $types);

if ($technician_id) {
    $query .= " AND t.technician_id = ?";
    $params[] = $technician_id;
    $types .= "i";
}

if ($empresa_id) {
    $query .= " AND r.company_id = ?";
    $params[] = $empresa_id;
    $types .= "i";
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Error al preparar la consulta de SLA.');
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Configuración simple de SLA (horas objetivo para todos los tickets)
$SLA_OBJETIVO_HORAS = 8;
$SLA_OBJETIVO_MINUTOS = $SLA_OBJETIVO_HORAS * 60;

$tickets = [];
$total_tickets = 0;
$sla_cumplidos = 0;
$sla_incumplidos = 0;
$tickets_abiertos = 0;
$suma_tiempo_cumplido = 0;
$suma_tiempo_incumplido = 0;

while ($row = $result->fetch_assoc()) {
    $total_tickets++;

    $tiempo_min = (int)$row['tiempo_resolucion_minutos'];
    $tiempo_horas = $tiempo_min / 60;
    $esta_cerrado = !empty($row['closed_at']);

    if (!$esta_cerrado) {
        $tickets_abiertos++;
        $estado_sla = 'abierto';
        $sla_cumplido = false;
    } elseif ($tiempo_min <= $SLA_OBJETIVO_MINUTOS) {
        $estado_sla = 'cumplido';
        $sla_cumplido = true;
        $sla_cumplidos++;
        $suma_tiempo_cumplido += $tiempo_min;
    } else {
        $estado_sla = 'incumplido';
        $sla_cumplido = false;
        $sla_incumplidos++;
        $suma_tiempo_incumplido += $tiempo_min;
    }

    $tickets[] = [
        'ticket_number' => $row['ticket_number'] ?? $row['ticket_id'],
        'empresa_nombre' => $row['empresa_nombre'] ?? 'N/A',
        'requester_name' => $row['requester_name'] ?? 'N/A',
        'created_at' => $row['created_at'],
        'closed_at' => $row['closed_at'],
        'status_name' => $row['status_name'] ?? 'N/A',
        'tiempo_min' => $tiempo_min,
        'tiempo_horas' => $tiempo_horas,
        'sla_cumplido' => $sla_cumplido,
        'estado_sla' => $estado_sla,
        'esta_cerrado' => $esta_cerrado,
    ];
}
$stmt->close();

function formatoHoras($minutos) {
    if ($minutos <= 0) return '00:00';
    $h = floor($minutos / 60);
    $m = $minutos % 60;
    return sprintf('%02d:%02d', $h, $m);
}

$tickets_cerrados = $sla_cumplidos + $sla_incumplidos;
$porcentaje_cumplimiento = $tickets_cerrados > 0 ? ($sla_cumplidos / $tickets_cerrados) * 100 : 0;
$promedio_cumplidos = $sla_cumplidos > 0 ? $suma_tiempo_cumplido / $sla_cumplidos : 0;
$promedio_incumplidos = $sla_incumplidos > 0 ? $suma_tiempo_incumplido / $sla_incumplidos : 0;

include '../../includes/modern-header.php';
?>
<style>
.body-bg {
    background: #f7f8fa;
    min-height: 100vh;
    width: 100vw;
    overflow-x: hidden;
}
.main-content-boxed {
    max-width: 1400px;
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
</style>
<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <div class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-2">
                        Cumplimiento de SLA
                        <?php echo getEmpresaBadge(); ?>
                    </h1>
                    <p class="text-muted mb-0">
                        Período del reporte:
                        <strong>
                            <?php echo htmlspecialchars($start_date_input); ?> al
                            <?php echo htmlspecialchars($end_date_input); ?>
                        </strong>
                        <br>
                        <small>Objetivo de resolución: <?php echo $SLA_OBJETIVO_HORAS; ?> horas · El % de cumplimiento se calcula solo sobre tickets cerrados.</small>
                    </p>
                </div>
                <div>
                    <a href="reports.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver a reportes
                    </a>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md col-lg">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted text-uppercase small mb-1">Tickets analizados</div>
                            <div class="h4 mb-0"><?php echo $total_tickets; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md col-lg">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted text-uppercase small mb-1">SLA cumplido</div>
                            <div class="h4 mb-0 text-success"><?php echo $sla_cumplidos; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md col-lg">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted text-uppercase small mb-1">SLA incumplido</div>
                            <div class="h4 mb-0 text-danger"><?php echo $sla_incumplidos; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md col-lg">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted text-uppercase small mb-1">En curso (abiertos)</div>
                            <div class="h4 mb-0 text-warning"><?php echo $tickets_abiertos; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md col-lg">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted text-uppercase small mb-1">% Cumplimiento</div>
                            <div class="h4 mb-0">
                                <?php echo number_format($porcentaje_cumplimiento, 1); ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted text-uppercase small mb-1">
                                Tiempo promedio de resolución (SLA cumplido)
                            </div>
                            <div class="h5 mb-0">
                                <?php echo formatoHoras((int)$promedio_cumplidos); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted text-uppercase small mb-1">
                                Tiempo promedio de resolución (SLA incumplido)
                            </div>
                            <div class="h5 mb-0">
                                <?php echo formatoHoras((int)$promedio_incumplidos); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">Detalle por ticket</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Ticket</th>
                                    <th>Empresa</th>
                                    <th>Solicitante</th>
                                    <th>Fecha creación</th>
                                    <th>Fecha cierre</th>
                                    <th>Tiempo real</th>
                                    <th>SLA objetivo</th>
                                    <th>Estado SLA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">
                                        No se encontraron tickets en el período seleccionado.
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $t): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($t['ticket_number']); ?></td>
                                        <td><?php echo htmlspecialchars($t['empresa_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($t['requester_name']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                                        <td>
                                            <?php echo $t['closed_at'] ? date('d/m/Y H:i', strtotime($t['closed_at'])) : 'Abierto'; ?>
                                        </td>
                                        <td><?php echo formatoHoras($t['tiempo_min']); ?></td>
                                        <td><?php echo $SLA_OBJETIVO_HORAS; ?> h</td>
                                        <td>
                                            <?php if ($t['estado_sla'] === 'cumplido'): ?>
                                                <span class="badge bg-success">Cumplido</span>
                                            <?php elseif ($t['estado_sla'] === 'incumplido'): ?>
                                                <span class="badge bg-danger">Incumplido</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">En curso</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>

