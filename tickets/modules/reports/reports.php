<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

// Obtener empresa del usuario actual
$user_empresa_id = getCurrentUserEmpresaId();

// Consulta de técnicos filtrada por empresa
$technicians_query = "SELECT id, name FROM technicians";
$technicians_params = [];
$technicians_types = '';
$technicians_query = addEmpresaFilter($technicians_query, 'id_empresa', $technicians_params, $technicians_types);
$technicians_query .= " ORDER BY name";
if (!empty($technicians_params)) {
    $technicians_stmt = $conn->prepare($technicians_query);
    $technicians_stmt->bind_param($technicians_types, ...$technicians_params);
    $technicians_stmt->execute();
    $technicians_result = $technicians_stmt->get_result();
} else {
    $technicians_result = $conn->query($technicians_query);
}

// Consulta de empresas - Solo mostrar empresas relacionadas con los tickets de la empresa del usuario
// Si el usuario es admin, mostrar todas las empresas
if ($user_empresa_id === null) {
    $empresas_query = "SELECT DISTINCT e.id_empresa, e.nombre 
                       FROM empresas e
                       INNER JOIN requesters r ON e.id_empresa = r.company_id
                       INNER JOIN tickets t ON r.id = t.requester_id
                       ORDER BY e.nombre";
    $empresas_result = $conn->query($empresas_query);
} else {
    // Usuario normal: solo empresas relacionadas con tickets de su empresa
    $empresas_query = "SELECT DISTINCT e.id_empresa, e.nombre 
                       FROM empresas e
                       INNER JOIN requesters r ON e.id_empresa = r.company_id
                       INNER JOIN tickets t ON r.id = t.requester_id
                       WHERE t.id_empresa = ?
                       ORDER BY e.nombre";
$empresas_stmt = $conn->prepare($empresas_query);
    $empresas_stmt->bind_param("i", $user_empresa_id);
$empresas_stmt->execute();
$empresas_result = $empresas_stmt->get_result();
}

$report_data = [];
$history_data = [];
$cost_data = [];
$tasks_data = [];
$tasks_summary = [
    'total' => 0,
    'cerradas' => 0,
    'abiertas' => 0,
    'en_espera' => 0,
    'actividades_total' => 0,
    'actividades_cerradas' => 0,
];
$total_cost_amount = 0;
$total_cost_entries = 0;
$report_generated = false;
$tasks_table_exists = false;

function reportFormatMinutes(int $minutes): string
{
    $minutes = max(0, $minutes);
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

$tasks_check = $conn->query("SHOW TABLES LIKE 'tasks'");
$tasks_tracking_exists = false;
if ($tasks_check && $tasks_check->num_rows > 0) {
    $tasks_table_exists = true;
    $tracking_check = $conn->query("SHOW TABLES LIKE 'task_status_tracking'");
    $tasks_tracking_exists = $tracking_check && $tracking_check->num_rows > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_generated = true;
    $start_date = $_POST['start_date'] . ' 00:00:00'; // Inicio del día
    $end_date = $_POST['end_date'] . ' 23:59:59'; // Fin del día (incluye todo el día)
    $technician_id = !empty($_POST['technician_id']) ? intval($_POST['technician_id']) : null;
    $empresa_id = !empty($_POST['empresa_id']) ? intval($_POST['empresa_id']) : null;
    
    // Validar que el técnico seleccionado pertenezca a la empresa del usuario
    if ($technician_id && $user_empresa_id !== null) {
        $check_tech = $conn->prepare("SELECT id FROM technicians WHERE id = ? AND id_empresa = ?");
        $check_tech->bind_param("ii", $technician_id, $user_empresa_id);
        $check_tech->execute();
        $check_tech_result = $check_tech->get_result();
        if ($check_tech_result->num_rows === 0) {
            // El técnico no pertenece a la empresa del usuario, ignorar el filtro
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
            // La empresa no está relacionada con tickets de la empresa del usuario, ignorar el filtro
            $empresa_id = null;
        }
        $check_emp->close();
    }
    
    $query = "SELECT 
        t.id as ticket_id,
        t.ticket_number,
        t.created_at,
        t.closed_at,
        t.description,
        t.resolution,
        tech.name as technician_name,
        tech.technician_number,
        e.nombre as empresa_nombre,
        r.name as requester_name,
        r.email as requester_email,
        r.requester_number,
        c.name as category_name,
        s.name as status_name,
        TIMESTAMPDIFF(MINUTE, t.created_at, COALESCE(t.closed_at, NOW())) as tiempo_resolucion_minutos,
        COALESCE((
            SELECT SUM(
                CASE 
                    WHEN tst.ended_at IS NOT NULL THEN 
                        TIMESTAMPDIFF(MINUTE, tst.started_at, tst.ended_at)
                    ELSE 
                        TIMESTAMPDIFF(MINUTE, tst.started_at, NOW())
                END
            )
            FROM ticket_status_tracking tst 
            WHERE tst.ticket_id = t.id 
            AND tst.status_id = 10
        ), 0) as tiempo_espera_minutos,
        (TIMESTAMPDIFF(MINUTE, t.created_at, COALESCE(t.closed_at, NOW())) - 
         COALESCE((
            SELECT SUM(
                CASE 
                    WHEN tst.ended_at IS NOT NULL THEN 
                        TIMESTAMPDIFF(MINUTE, tst.started_at, tst.ended_at)
                    ELSE 
                        TIMESTAMPDIFF(MINUTE, tst.started_at, NOW())
                END
            )
            FROM ticket_status_tracking tst 
            WHERE tst.ticket_id = t.id 
            AND tst.status_id = 10
        ), 0)) as tiempo_sin_espera_minutos
    FROM tickets t
    LEFT JOIN technicians tech ON t.technician_id = tech.id
    LEFT JOIN requesters r ON t.requester_id = r.id
    LEFT JOIN empresas e ON r.company_id = e.id_empresa
    LEFT JOIN empresas_usuarios eu ON t.id_empresa = eu.id_empresa
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN statuses s ON t.status_id = s.id
    WHERE t.created_at BETWEEN ? AND ?
    AND (t.deleted IS NULL OR t.deleted = 0)";

    $params = [$start_date, $end_date];
    $types = "ss";

    // Agregar filtro de empresa (empresas_usuarios)
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
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
    }
    $stmt->close();
    
    // Consulta para obtener resumen de usuarios por tickets creados y cerrados
    $history_query = "SELECT 
        th.user_name,
        COUNT(CASE WHEN th.action = 'created' THEN 1 END) as tickets_creados,
        COUNT(CASE WHEN th.action = 'closed' THEN 1 END) as tickets_cerrados
    FROM ticket_history th
    LEFT JOIN tickets t ON th.ticket_id = t.id
    LEFT JOIN requesters r ON t.requester_id = r.id
    WHERE th.action IN ('created', 'closed')
    AND t.created_at BETWEEN ? AND ?
    AND (t.deleted IS NULL OR t.deleted = 0)";
    
    $history_params = [$start_date, $end_date];
    $history_types = "ss";
    
    // Agregar filtro de empresa
    $history_query = addEmpresaFilter($history_query, 't.id_empresa', $history_params, $history_types);
    
    if ($technician_id) {
        $history_query .= " AND t.technician_id = ?";
        $history_params[] = $technician_id;
        $history_types .= "i";
    }
    
    if ($empresa_id) {
        $history_query .= " AND r.company_id = ?";
        $history_params[] = $empresa_id;
        $history_types .= "i";
    }
    
    $history_query .= " GROUP BY th.user_name ORDER BY tickets_creados DESC, tickets_cerrados DESC";
    
    $history_stmt = $conn->prepare($history_query);
    $history_stmt->bind_param($history_types, ...$history_params);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
    
    while ($history_row = $history_result->fetch_assoc()) {
        $history_data[] = $history_row;
    }
    $history_stmt->close();

    // Consulta para obtener resumen de costos por ticket
    $cost_query = "SELECT
        t.id as ticket_id,
        t.ticket_number,
        e.nombre as empresa_nombre,
        eu.nombre as empresa_usuario_nombre,
        SUM(tc.amount) as total_cost,
        COUNT(tc.id) as total_entries,
        MAX(tc.created_at) as last_cost_at,
        GROUP_CONCAT(tc.description SEPARATOR ' | ') as descriptions
    FROM ticket_costs tc
    INNER JOIN tickets t ON tc.ticket_id = t.id
    LEFT JOIN requesters r ON t.requester_id = r.id
    LEFT JOIN empresas e ON r.company_id = e.id_empresa
    LEFT JOIN empresas_usuarios eu ON t.id_empresa = eu.id_empresa
    WHERE t.created_at BETWEEN ? AND ?
    AND (t.deleted IS NULL OR t.deleted = 0)";
    
    $cost_params = [$start_date, $end_date];
    $cost_types = "ss";
    
    // Agregar filtro de empresa
    $cost_query = addEmpresaFilter($cost_query, 't.id_empresa', $cost_params, $cost_types);

    if ($technician_id) {
        $cost_query .= " AND t.technician_id = ?";
        $cost_params[] = $technician_id;
        $cost_types .= "i";
    }

    if ($empresa_id) {
        $cost_query .= " AND r.company_id = ?";
        $cost_params[] = $empresa_id;
        $cost_types .= "i";
    }

    $cost_query .= " GROUP BY t.id, e.nombre, eu.nombre ORDER BY total_cost DESC";

    $cost_stmt = $conn->prepare($cost_query);
    $cost_stmt->bind_param($cost_types, ...$cost_params);
    $cost_stmt->execute();
    $cost_result = $cost_stmt->get_result();

    while ($cost_row = $cost_result->fetch_assoc()) {
        $cost_row['total_cost'] = (float)$cost_row['total_cost'];
        $cost_row['total_entries'] = (int)$cost_row['total_entries'];
        $cost_data[] = $cost_row;
        $total_cost_amount += $cost_row['total_cost'];
        $total_cost_entries += $cost_row['total_entries'];
    }
    $cost_stmt->close();

    if ($tasks_table_exists) {
        $tasks_wait_sql = $tasks_tracking_exists
            ? "COALESCE((
                SELECT SUM(
                    CASE
                        WHEN tst.ended_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, tst.started_at, tst.ended_at)
                        ELSE TIMESTAMPDIFF(MINUTE, tst.started_at, NOW())
                    END
                )
                FROM task_status_tracking tst
                WHERE tst.entity_type = 'task' AND tst.entity_id = tk.id AND tst.status_id = 10
            ), 0) AS tiempo_espera_minutos"
            : '0 AS tiempo_espera_minutos';

        $tasks_query = "SELECT
            tk.id AS task_id,
            tk.task_number,
            tk.subject,
            tk.created_at,
            tk.closed_at,
            tk.description,
            tk.resolution,
            s.name AS status_name,
            tk.status_id,
            c.name AS category_name,
            tech.name AS technician_name,
            r.name AS requester_name,
            e.nombre AS empresa_nombre,
            (SELECT COUNT(*) FROM task_subtasks st WHERE st.task_id = tk.id) AS subtask_total,
            (SELECT COUNT(*) FROM task_subtasks st WHERE st.task_id = tk.id AND st.status_id = 3) AS subtask_closed,
            TIMESTAMPDIFF(MINUTE, tk.created_at, COALESCE(tk.closed_at, NOW())) AS tiempo_total_minutos,
            {$tasks_wait_sql}
        FROM tasks tk
        LEFT JOIN statuses s ON tk.status_id = s.id
        LEFT JOIN categories c ON tk.category_id = c.id
        LEFT JOIN technicians tech ON tk.technician_id = tech.id
        LEFT JOIN requesters r ON tk.requester_id = r.id
        LEFT JOIN empresas e ON r.company_id = e.id_empresa
        WHERE tk.created_at BETWEEN ? AND ?
        AND tk.deleted = 0";

        $tasks_params = [$start_date, $end_date];
        $tasks_types = 'ss';
        $tasks_query = addEmpresaFilter($tasks_query, 'tk.id_empresa', $tasks_params, $tasks_types);

        if ($technician_id) {
            $tasks_query .= ' AND tk.technician_id = ?';
            $tasks_params[] = $technician_id;
            $tasks_types .= 'i';
        }

        if ($empresa_id) {
            $tasks_query .= ' AND r.company_id = ?';
            $tasks_params[] = $empresa_id;
            $tasks_types .= 'i';
        }

        $tasks_query .= ' ORDER BY tk.created_at DESC';

        $tasks_stmt = $conn->prepare($tasks_query);
        if ($tasks_stmt) {
            $tasks_stmt->bind_param($tasks_types, ...$tasks_params);
            $tasks_stmt->execute();
            $tasks_result = $tasks_stmt->get_result();

            while ($task_row = $tasks_result->fetch_assoc()) {
                $task_row['subtask_total'] = (int) ($task_row['subtask_total'] ?? 0);
                $task_row['subtask_closed'] = (int) ($task_row['subtask_closed'] ?? 0);
                $task_row['tiempo_total_minutos'] = (int) ($task_row['tiempo_total_minutos'] ?? 0);
                $task_row['tiempo_espera_minutos'] = (int) ($task_row['tiempo_espera_minutos'] ?? 0);
                $tasks_data[] = $task_row;

                $tasks_summary['total']++;
                $tasks_summary['actividades_total'] += $task_row['subtask_total'];
                $tasks_summary['actividades_cerradas'] += $task_row['subtask_closed'];

                $status_id = (int) ($task_row['status_id'] ?? 0);
                if ($status_id === 3) {
                    $tasks_summary['cerradas']++;
                } elseif ($status_id === 10) {
                    $tasks_summary['en_espera']++;
                } else {
                    $tasks_summary['abiertas']++;
                }
            }
            $tasks_stmt->close();
        }
    }
}
?>
<?php include '../../includes/modern-header.php'; ?>
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
    .table th, .table td {
        padding: 0.5rem 0.5rem;
        font-size: 0.95rem;
    }
    .table-responsive {
        overflow-x: auto;
    }
    .table thead {
        font-size: 0.95rem;
    }
}
.table-container {
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 2px 8px 0 rgba(32,38,84,0.04);
    margin-bottom: 2rem;
    padding: 0.5rem 0.5rem 0.5rem 0.5rem;
}
.badge-cost-total {
    background: rgba(34, 94, 78, 0.15);
    color: rgba(34, 94, 78, 1);
    border: 1px solid rgba(34, 94, 78, 0.35);
}
.cost-total-row {
    background: rgba(9, 80, 55, 0.15);
}
.cost-total-row td {
    border-top: 2px solid rgba(9, 80, 55, 0.75);
    font-weight: 600;
}
.cost-total-value {
    color: rgba(9, 80, 55, 1);
    font-size: 1.1rem;
}
.cost-amount {
    color: rgba(9, 80, 55, 1);
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
                        Reportes de Tickets
                        <?php echo getEmpresaBadge(); ?>
                    </h1>
                    <p class="text-muted mb-0">
                        Genera reportes personalizados de tickets por fecha, técnico y empresa
                        <?php if ($user_empresa_id !== null): ?>
                            <br><small>Mostrando reportes de tu empresa</small>
                        <?php else: ?>
                            <br><small>Mostrando reportes de todas las empresas</small>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <!-- Formulario de filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="POST" action="" class="row g-3">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required 
                                   value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('-30 days')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required
                                   value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="technician_id" class="form-label">Técnico</label>
                            <select class="form-select" id="technician_id" name="technician_id">
                                <option value="">Todos los técnicos</option>
                                <?php while ($tech = $technicians_result->fetch_assoc()): ?>
                                    <option value="<?php echo $tech['id']; ?>" 
                                            <?php echo (isset($_POST['technician_id']) && $_POST['technician_id'] == $tech['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tech['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="empresa_id" class="form-label">Empresa</label>
                            <select class="form-select" id="empresa_id" name="empresa_id">
                                    <option value="">Todas las empresas</option>
                                <?php while ($emp = $empresas_result->fetch_assoc()): ?>
                                    <option value="<?php echo $emp['id_empresa']; ?>"
                                            <?php echo (isset($_POST['empresa_id']) && $_POST['empresa_id'] == $emp['id_empresa']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Generar Reporte
                            </button>
                            <!--<button
                                type="submit"
                                class="btn btn-outline-primary"
                                formaction="reports_sla.php"
                            >
                                <i class="fas fa-stopwatch"></i> Reporte de SLA
                            </button>-->
                            <?php if (!empty($report_data)): ?>
                            <!--<button type="button" class="btn btn-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> Exportar a Excel
                            </button>-->
                            <button type="button" class="btn btn-danger" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf"></i> Exportar a PDF
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Resultados del reporte -->
            <?php if (!empty($report_data)): ?>
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table align-middle tickets-list-table" id="reportTable">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Fecha Creación</th>
                                <th>Empresa</th>
                                <th>Solicitante</th>
                                <th>Correo</th>
                                <th>Descripción</th>
                                <th>Categoría</th>
                                <th>Estado</th>
                                <th>Resolución</th>
                                <th>Técnico</th>
                                <th>Tiempo Total (HH:MM)</th>
                                <th>Tiempo en Espera (HH:MM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['ticket_number'] ?? $row['ticket_id']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($row['empresa_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['requester_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['requester_email']); ?> </td>
                                <td><?php echo htmlspecialchars($row['description'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['status_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['resolution']); ?></td>
                                <td><?php echo htmlspecialchars($row['technician_name'] ?? 'Sin asignar'); ?></td>
                                <td><?php 
                                    $minutos = $row['tiempo_resolucion_minutos'];
                                    $horas = intval($minutos / 60);
                                    $minutos_restantes = $minutos % 60;
                                    echo sprintf('%02d:%02d', $horas, $minutos_restantes);
                                ?></td>
                                <td><?php 
                                    $minutos_espera = $row['tiempo_espera_minutos'];
                                    $horas_espera = intval($minutos_espera / 60);
                                    $minutos_espera_restantes = $minutos_espera % 60;
                                    echo sprintf('%02d:%02d', $horas_espera, $minutos_espera_restantes);
                                ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sección de costos por ticket -->
            <?php if (!empty($cost_data)): ?>
            <div class="table-container">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h4 class="mb-1">Resumen de Costos por Ticket</h4>
                    </div>
                    <div class="text-end">
                        <span class="badge badge-cost-total fs-6">
                            Inversión total: $<?php echo number_format($total_cost_amount, 2); ?>
                        </span>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div style="min-height:320px;">
                            <canvas id="ticketCostChart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="table-responsive">
                            <table class="table align-middle tickets-list-table" id="costTable">
                                <thead>
                                    <tr>
                                        <th>Ticket</th>
                                        <th>Empresa</th>
                                        <th>Descripción</th>
                                        <th>Total Costos</th>
                                        <th>Registros</th>
                                        <th>Último Gasto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cost_data as $cost_row): ?>
                                    <tr>
                                        <td>Ticket #<?php echo htmlspecialchars($cost_row['ticket_number'] ?? $cost_row['ticket_id']); ?></td>
                                        <td><?php echo htmlspecialchars($cost_row['empresa_nombre'] ?? 'N/A'); ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($cost_row['descriptions'] ?? 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="fw-bold cost-amount">
                                                $<?php echo number_format($cost_row['total_cost'], 2); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $cost_row['total_entries']; ?></td>
                                        <td>
                                            <?php echo !empty($cost_row['last_cost_at']) ? date('d/m/Y H:i', strtotime($cost_row['last_cost_at'])) : 'N/A'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="cost-total-row">
                                        <td colspan="3" class="text-end">TOTAL GENERAL:</td>
                                        <td>
                                            <span class="fw-bold cost-total-value">
                                                $<?php echo number_format($total_cost_amount, 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $total_cost_entries; ?>
                                        </td>
                                        <td>-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Tabla de Resumen de Usuarios -->
            <?php if (!empty($history_data)): ?>
            <div class="table-container">
                <h4 class="mb-3">Resumen de Actividad por Usuario</h4>

                <div class="table-responsive">
                    <table class="table align-middle tickets-list-table" id="historyTable">
                        <thead>
                            <tr>
                                <th>ID Usuario</th>
                                <th>Usuario</th>
                                <th>Total Tickets Creados</th>
                                <th>Total Tickets Cerrados</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_data as $index => $history_row): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($history_row['user_name']); ?></td>
                                <td>
                                    <span class="badge bg-success fs-6">
                                        <?php echo $history_row['tickets_creados']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-danger fs-6">
                                        <?php echo $history_row['tickets_cerrados']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Resumen de Tareas (al final del reporte) -->
            <?php if ($report_generated && $tasks_table_exists): ?>
            <div class="table-container">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h4 class="mb-1">Resumen de Tareas</h4>
                        <p class="text-muted small mb-0">Tareas creadas en el período seleccionado (mismos filtros que tickets)</p>
                    </div>
                    <span class="badge bg-primary fs-6">Total: <?php echo (int) $tasks_summary['total']; ?> tareas</span>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table align-middle table-sm" id="tasksSummaryTable">
                        <thead>
                            <tr>
                                <th>Total tareas</th>
                                <th>Abiertas</th>
                                <th>En espera</th>
                                <th>Cerradas</th>
                                <th>Actividades (cerradas/total)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-secondary fs-6"><?php echo (int) $tasks_summary['total']; ?></span></td>
                                <td><span class="badge bg-info fs-6"><?php echo (int) $tasks_summary['abiertas']; ?></span></td>
                                <td><span class="badge bg-warning text-dark fs-6"><?php echo (int) $tasks_summary['en_espera']; ?></span></td>
                                <td><span class="badge bg-success fs-6"><?php echo (int) $tasks_summary['cerradas']; ?></span></td>
                                <td>
                                    <span class="badge bg-dark fs-6">
                                        <?php echo (int) $tasks_summary['actividades_cerradas']; ?> / <?php echo (int) $tasks_summary['actividades_total']; ?>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($tasks_data)): ?>
                <div class="table-responsive">
                    <table class="table align-middle tickets-list-table" id="tasksTable">
                        <thead>
                            <tr>
                                <th>Tarea</th>
                                <th>Fecha creación</th>
                                <th>Asunto</th>
                                <th>Empresa</th>
                                <th>Solicitante</th>
                                <th>Estado</th>
                                <th>Técnico</th>
                                <th>Actividades</th>
                                <th>Tiempo total (HH:MM)</th>
                                <th>Tiempo en espera (HH:MM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks_data as $task_row): ?>
                            <?php
                                $task_subject = trim((string) ($task_row['subject'] ?? ''));
                                if ($task_subject === '') {
                                    $task_subject = mb_substr(strip_tags((string) ($task_row['description'] ?? '')), 0, 80);
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($task_row['task_number'] ?? $task_row['task_id']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($task_row['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($task_subject ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($task_row['empresa_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($task_row['requester_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($task_row['status_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($task_row['technician_name'] ?? 'Sin asignar'); ?></td>
                                <td><?php echo (int) $task_row['subtask_closed']; ?> / <?php echo (int) $task_row['subtask_total']; ?></td>
                                <td><?php echo reportFormatMinutes((int) $task_row['tiempo_total_minutos']); ?></td>
                                <td><?php echo reportFormatMinutes((int) $task_row['tiempo_espera_minutos']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No hay tareas registradas en el período seleccionado.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.0/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- La gráfica de actividad de usuarios se genera solo en el PDF -->
    <script>
        <?php if (!empty($cost_data)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const costCanvas = document.getElementById('ticketCostChart');
            if (!costCanvas) {
                return;
            }
            const costCtx = costCanvas.getContext('2d');
            const costData = <?php echo json_encode($cost_data); ?>;
            const topCosts = costData.slice(0, 8);
            const totalCostAmountFormatted = <?php echo json_encode('$' . number_format($total_cost_amount, 2)); ?>;
            const totalCostEntriesValue = <?php echo json_encode((string)$total_cost_entries); ?>;

            new Chart(costCtx, {
                type: 'bar',
                data: {
                    labels: topCosts.map(item => `Ticket #${item.ticket_number || item.ticket_id}`),
                    datasets: [{
                        label: 'Total de Costos (MXN)',
                        data: topCosts.map(item => item.total_cost),
                        backgroundColor: 'rgba(28, 78, 64, 0.85)',
                        borderColor: 'rgba(17, 43, 33, 1)',
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Top 8 tickets con mayores costos',
                            font: {
                                size: 18,
                                weight: 'bold'
                            },
                            padding: {
                                top: 10,
                                bottom: 20
                            }
                        },
                        tooltip: {
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 14
                            },
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed.y ?? context.parsed;
                                    return ` $${Number(value).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { 
                                maxRotation: 30, 
                                minRotation: 0,
                                font: {
                                    size: 12
                                }
                            },
                            title: {
                                display: true,
                                text: 'Tickets',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: {
                                    size: 12
                                },
                                callback: function(value) {
                                    return `$${value.toLocaleString('es-MX')}`;
                                }
                            },
                            title: {
                                display: true,
                                text: 'Costo (MXN)',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                }
            });
        });
        <?php endif; ?>
    </script>
    <script>
        <?php if ($report_generated && $tasks_table_exists): ?>
        const TASKS_REPORT_AVAILABLE = true;
        <?php else: ?>
        const TASKS_REPORT_AVAILABLE = false;
        <?php endif; ?>

        function getBase64Image(img, callback) {
            const image = new Image();
            image.crossOrigin = 'Anonymous';
            image.onload = function() {
                const canvas = document.createElement('canvas');
                canvas.width = image.width;
                canvas.height = image.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(image, 0, 0);
                callback(canvas.toDataURL('image/png'));
            };
            image.src = img;
        }

        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
            let pageWidth = doc.internal.pageSize.getWidth();
            let pageHeight = doc.internal.pageSize.getHeight();
            
            // Variables para el contenido
            let y = 100; // Comenzar después del encabezado
            let currentPage = 1;

            // Función para agregar encabezado
            function addHeader(showTitle = true) {
                // Obtener dimensiones actuales de la página
                const currentPageWidth = doc.internal.pageSize.getWidth();
                const currentPageHeight = doc.internal.pageSize.getHeight();
                
                getBase64Image('../../modules/reports/Encabezado.png', function(logoData) {
                    // Tamaño del logo para orientación horizontal
                    const logoWidth = 75;
                    const logoHeight = 50;

                    doc.setGState(new doc.GState({ opacity: 0.4 }));
                    doc.addImage(logoData, 'PNG', 20, 20, logoWidth, logoHeight);
                    doc.setGState(new doc.GState({ opacity: 1 }));
                    
                    if (showTitle) {
                    doc.setFontSize(15);
                    doc.setFont(undefined, 'bold');
                    doc.text('Reporte de Tickets', currentPageWidth / 2, 95, { align: 'center' });
                    }
                    doc.setFontSize(11);
                    doc.setFont(undefined, 'bold');
                    const today = new Date().toLocaleDateString();
                    doc.text(`Fecha: ${today}`, currentPageWidth - 40, 65, { align: 'right' });
                });
            }

            // Función para agregar pie de página
            function addFooter() {
                // Obtener dimensiones actuales de la página
                const currentPageWidth = doc.internal.pageSize.getWidth();
                const currentPageHeight = doc.internal.pageSize.getHeight();
                
                getBase64Image('../../modules/reports/Pie de página.png', function(logoData) {
                    doc.addImage(logoData, 'PNG', 30, currentPageHeight - 40, 60, 30);
                    doc.setFontSize(8);
                    doc.setFont(undefined, 'normal');
                    const pageNumber = `Página ${currentPage}`;
                    doc.text(pageNumber, currentPageWidth / 2, currentPageHeight - 20, { align: 'center' });
                    doc.text('GK - Sistema de Gestión de Tickets', currentPageWidth - 40, currentPageHeight - 20, { align: 'right' });
                });
            }

            // Función para verificar espacio y agregar nueva página si es necesario
            function checkSpace(neededSpace) {
                const currentPageHeight = doc.internal.pageSize.getHeight();
                if (y + neededSpace > currentPageHeight - 80) {
                    doc.addPage('landscape', 'a4');
                    currentPage++;
                    y = 100;
                    addHeader(false); // Sin título en páginas adicionales
                    addFooter();
                    return true;
                }
                return false;
            }

            function renderTasksReportSection(done) {
                if (!TASKS_REPORT_AVAILABLE) {
                    done();
                    return;
                }

                doc.addPage('landscape', 'a4');
                currentPage++;
                y = 100;
                addHeader(false);
                addFooter();

                const pageW = doc.internal.pageSize.getWidth();
                const tasksChartWidth = 280;
                const tasksChartHeight = 140;

                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.text('Resumen de Tareas', pageW / 2, y, { align: 'center' });
                y += 25;

                function renderTasksDetailTable() {
                    const tasksTable = document.getElementById('tasksTable');
                    if (tasksTable) {
                        const taskRows = Array.from(tasksTable.querySelectorAll('tbody tr'));
                        const taskBody = taskRows.map(row => {
                            const cells = row.querySelectorAll('td');
                            return [
                                cells[0]?.innerText?.trim() || '',
                                cells[1]?.innerText?.trim() || '',
                                cells[2]?.innerText?.trim() || '',
                                cells[4]?.innerText?.trim() || '',
                                cells[5]?.innerText?.trim() || '',
                                cells[6]?.innerText?.trim() || '',
                                cells[7]?.innerText?.trim() || '',
                                cells[8]?.innerText?.trim() || '',
                                cells[9]?.innerText?.trim() || ''
                            ];
                        });

                        if (taskBody.length) {
                            checkSpace(100);
                            doc.autoTable({
                                head: [['Tarea', 'Fecha creación', 'Asunto', 'Solicitante', 'Estado', 'Técnico', 'Actividades', 'Tiempo total', 'Tiempo espera']],
                                body: taskBody,
                                startY: y,
                                styles: { fontSize: 7, overflow: 'linebreak' },
                                headStyles: { fillColor: [49, 122, 48], textColor: 255 },
                                margin: { left: 20, right: 20 },
                                theme: 'grid',
                                columnStyles: {
                                    2: { cellWidth: 100 },
                                    3: { cellWidth: 75 }
                                }
                            });
                        }
                    } else {
                        checkSpace(30);
                        doc.setFontSize(10);
                        doc.setFont(undefined, 'normal');
                        doc.text('No hay tareas registradas en el período seleccionado.', pageW / 2, y, { align: 'center' });
                    }

                    done();
                }

                const summaryTable = document.getElementById('tasksSummaryTable');
                const summaryRow = summaryTable ? summaryTable.querySelector('tbody tr') : null;
                if (!summaryRow) {
                    renderTasksDetailTable();
                    return;
                }

                const cells = summaryRow.querySelectorAll('td');
                const parseSummaryNum = (text) => parseInt(String(text || '0').replace(/[^\d]/g, ''), 10) || 0;
                const tasksSummaryCanvas = document.createElement('canvas');
                tasksSummaryCanvas.width = tasksChartWidth;
                tasksSummaryCanvas.height = tasksChartHeight;
                document.body.appendChild(tasksSummaryCanvas);

                new Chart(tasksSummaryCanvas, {
                    type: 'bar',
                    data: {
                        labels: ['Total tareas', 'Abiertas', 'En espera', 'Cerradas'],
                        datasets: [{
                            label: 'Cantidad de tareas',
                            data: [
                                parseSummaryNum(cells[0]?.innerText),
                                parseSummaryNum(cells[1]?.innerText),
                                parseSummaryNum(cells[2]?.innerText),
                                parseSummaryNum(cells[3]?.innerText)
                            ],
                            backgroundColor: [
                                'rgba(14, 98, 154, 0.75)',
                                'rgba(54, 162, 235, 0.75)',
                                'rgba(255, 206, 86, 0.85)',
                                'rgba(40, 167, 69, 0.75)'
                            ],
                            borderColor: [
                                'rgba(14, 98, 154, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 193, 7, 1)',
                                'rgba(40, 167, 69, 1)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false },
                            title: {
                                display: true,
                                text: 'Distribución de tareas en el período',
                                font: { size: 50, weight: 'bold' },
                                padding: 20
                            }
                        },
                        scales: {
                            x: {
                                ticks: { font: { size: 28 }, padding: 8 }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    font: { size: 28 },
                                    padding: 8
                                }
                            }
                        }
                    }
                });

                setTimeout(function() {
                    const summaryImg = tasksSummaryCanvas.toDataURL('image/png');
                    const imgWidth = 380;
                    const imgHeight = (tasksChartHeight * imgWidth) / tasksChartWidth;
                    const xPos = (pageW - imgWidth) / 2;
                    checkSpace(imgHeight + 30);
                    doc.addImage(summaryImg, 'PNG', xPos, y, imgWidth, imgHeight);
                    y += imgHeight + 25;
                    document.body.removeChild(tasksSummaryCanvas);
                    renderTasksDetailTable();
                }, 800);
            }

            // Agregar encabezado a la primera página
            addHeader();
            addFooter();

            // Información del período
            const currentPageWidthInfo = doc.internal.pageSize.getWidth();
            doc.setFontSize(12);
            doc.setFont(undefined, 'normal');
            doc.text(`Período: ${document.getElementById('start_date').value} a ${document.getElementById('end_date').value}`, currentPageWidthInfo / 2, 115, { align: 'center' });
            
            // Añadir información de filtros si se aplicaron
            const technician = document.getElementById('technician_id');
            const empresa = document.getElementById('empresa_id');
            let filterText = '';
            
            if (technician.value) {
                filterText += `Técnico: ${technician.options[technician.selectedIndex].text}`;
            }
            
            if (empresa.value) {
                if (filterText) filterText += ' | ';
                filterText += `Empresa: ${empresa.options[empresa.selectedIndex].text}`;
            }
            
            if (filterText) {
                y += 30;
                const currentPageWidthFilter = doc.internal.pageSize.getWidth();
                doc.text(filterText, currentPageWidthFilter / 2, y, { align: 'center' });
            }
            
            y += 40;

            // Obtener datos de la tabla
            const table = document.getElementById('reportTable');
            const rows = Array.from(table.querySelectorAll('tbody tr'));
            const statusCount = {};
            const categoryCount = {};
            const tecnicoCount = {};
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td, th');
                const status = cells[7]?.innerText || 'N/A'; // Estado ahora está en índice 7
                const category = cells[6]?.innerText || 'N/A'; // Categoría ahora está en índice 6
                const tecnico = cells[9]?.innerText || 'N/A'; // Técnico ahora está en índice 9

                statusCount[status] = (statusCount[status] || 0) + 1;
                categoryCount[category] = (categoryCount[category] || 0) + 1;
                tecnicoCount[tecnico] = (tecnicoCount[tecnico] || 0) + 1;
            });

            setTimeout(function() {
                // Crear canvas para las gráficas con tamaños proporcionales (reducidos)
                const chartWidth = 280;
                const chartHeight = 140;

                // Crear canvas para las gráficas
                const areaCanvas = document.createElement('canvas');
                areaCanvas.width = chartWidth;
                areaCanvas.height = chartHeight;
                document.body.appendChild(areaCanvas);

                // 1. Gráfica de línea de estado - PÁGINA 1
                    new Chart(areaCanvas, {
                        type: 'line',
                        data: {
                            labels: Object.keys(statusCount),
                            datasets: [{
                                label: 'Tickets por Estado',
                                data: Object.values(statusCount),
                                fill: true,
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 2,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: { 
                                    display: true,
                                    position: 'top',
                                    labels: { 
                                        font: { size: 40 },
                                        padding: 20
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Evolución de Tickets por Estado',
                                    font: { size: 50 },
                                    padding: 20
                                }
                            },
                            scales: {
                                x: {
                                    ticks: { 
                                        font: { size: 30 },
                                        padding: 10
                                    }
                                },
                                y: {
                                beginAtZero: true,
                                    ticks: { 
                                        font: { size: 30 },
                                        padding: 10
                                }
                                }
                            }
                        }
                    });

                    setTimeout(function() {
                        const areaImg = areaCanvas.toDataURL('image/png');
                    // Verificar espacio antes de agregar la imagen (ajustado para gráfica más pequeña)
                    checkSpace(180);
                    
                        // Calcular posición para centrar la imagen (reducida)
                        const imgWidth = 350;
                        const imgHeight = (chartHeight * imgWidth) / chartWidth;
                        const currentPageWidthImg = doc.internal.pageSize.getWidth();
                        const xPos = (currentPageWidthImg - imgWidth) / 2;
                        doc.addImage(areaImg, 'PNG', xPos, y, imgWidth, imgHeight);
                    y += imgHeight + 30;

                    // 2. Tabla de resumen de estadísticas - PÁGINA 1
                        const totalTickets = rows.length;
                        const ticketsResueltos = Object.entries(statusCount).filter(([status]) => 
                            status.toLowerCase().includes('cerrado') || status.toLowerCase().includes('resuelto')
                        ).reduce((sum, [_, count]) => sum + count, 0);
                        
                        const tiempoPromedio = rows.reduce((sum, row) => {
                            const cells = row.querySelectorAll('td, th');
                            const tiempoTexto = cells[10]?.innerText || '00:00'; // Tiempo Total ahora está en índice 10
                            const [horas, minutos] = tiempoTexto.split(':').map(Number);
                            const tiempoEnMinutos = (horas * 60) + minutos;
                            return sum + tiempoEnMinutos;
                        }, 0) / totalTickets;
                    
                    const tiempoPromedioEspera = rows.reduce((sum, row) => {
                        const cells = row.querySelectorAll('td, th');
                        const tiempoTexto = cells[11]?.innerText || '00:00'; // Tiempo Espera ahora está en índice 11
                            const [horas, minutos] = tiempoTexto.split(':').map(Number);
                            const tiempoEnMinutos = (horas * 60) + minutos;
                            return sum + tiempoEnMinutos;
                        }, 0) / totalTickets;
                        
                        const horasPromedio = Math.floor(tiempoPromedio / 60);
                        const minutosPromedio = Math.round(tiempoPromedio % 60);
                        const tiempoPromedioFormateado = `${horasPromedio.toString().padStart(2, '0')}:${minutosPromedio.toString().padStart(2, '0')}`;
                    
                    const horasPromedioEspera = Math.floor(tiempoPromedioEspera / 60);
                    const minutosPromedioEspera = Math.round(tiempoPromedioEspera % 60);
                    const tiempoPromedioEsperaFormateado = `${horasPromedioEspera.toString().padStart(2, '0')}:${minutosPromedioEspera.toString().padStart(2, '0')}`;
                        
                        const statsData = [
                            ['Estadística', 'Valor'],
                            ['Total de Tickets', totalTickets.toString()],
                            ['Tickets Resueltos', ticketsResueltos.toString()],
                            ['Tasa de Resolución', `${((ticketsResueltos / totalTickets) * 100).toFixed(1)}%`],
                        ['Tiempo Promedio Total', tiempoPromedioFormateado],
                        ['Tiempo Promedio en Espera', tiempoPromedioEsperaFormateado]
                        ];
                    
                    // Verificar espacio antes de agregar la tabla
                    checkSpace(150);
                        
                        doc.autoTable({
                            head: [statsData[0]],
                            body: statsData.slice(1),
                            startY: y,
                            styles: { fontSize: 10 },
                            headStyles: { fillColor: [14, 98, 154] },
                            margin: { left: 40, right: 40 },
                        theme: 'grid'
                    });

                    y = doc.lastAutoTable.finalY + 30;
                    
                    const renderRemainingSections = () => {
                    // Crear nueva página para la siguiente sección
                    doc.addPage('landscape', 'a4');
                    currentPage++;
                    y = 100;
                    addHeader(false); // Sin título en páginas adicionales
                    addFooter();
                    
                    // 3. Gráfica de resumen de técnicos (pastel) - PÁGINA 2
                    const tecnicoPieCanvas = document.createElement('canvas');
                    tecnicoPieCanvas.width = chartWidth;
                    tecnicoPieCanvas.height = chartHeight;
                    document.body.appendChild(tecnicoPieCanvas);

                        new Chart(tecnicoPieCanvas, {
                            type: 'pie',
                            data: {
                                labels: Object.keys(tecnicoCount),
                                datasets: [{
                                    label: 'Tickets por Técnico',
                                    data: Object.values(tecnicoCount),
                                    backgroundColor: [
                                        'rgba(54, 162, 235, 0.7)',
                                        'rgba(255, 99, 132, 0.7)',
                                        'rgba(255, 206, 86, 0.7)',
                                        'rgba(75, 192, 192, 0.7)',
                                        'rgba(14, 98, 154, 0.7)',
                                        'rgba(153, 102, 255, 0.7)',
                                        'rgba(255, 159, 64, 0.7)'
                                    ]
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: true,
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: { 
                                            font: { size: 50 },
                                            padding: 20
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: 'Distribución por Técnico',
                                        font: { size: 80 },
                                        padding: 20
                                    }
                                }
                            }
                        });

                         setTimeout(function() {
                            const tecnicoPieImg = tecnicoPieCanvas.toDataURL('image/png');
                            // Mantener proporción cuadrada para el gráfico de pastel (reducido)
                            const pieSize = 210;
                            const currentPageWidthPie = doc.internal.pageSize.getWidth();
                            const xPos = (currentPageWidthPie - pieSize) / 2;
                            doc.addImage(tecnicoPieImg, 'PNG', xPos, y, pieSize, pieSize);
                        y += pieSize + 30;

                        // 4. Tabla de resumen de técnicos - PÁGINA 2
                            const resumenTecnicos = Object.entries(tecnicoCount).map(([tecnico, total], idx) => [idx + 1, tecnico, total]);
                        
                        // Verificar espacio antes de agregar la tabla
                        checkSpace(150);
                        
                            doc.autoTable({
                                head: [['ID Técnico', 'Técnico', 'Total de Tickets']],
                                body: resumenTecnicos,
                                startY: y,
                                styles: { fontSize: 9 },
                                headStyles: { fillColor: [189, 42, 74] },
                                margin: { left: 40, right: 40 },
                            theme: 'grid'
                        });

                        y = doc.lastAutoTable.finalY + 30;
                        
                        // Crear nueva página para la siguiente sección
                        doc.addPage('landscape', 'a4');
                        currentPage++;
                        y = 100;
                        addHeader(false); // Sin título en páginas adicionales
                        addFooter();
                        
                        // 5. Gráfica de categoría - PÁGINA 3
                        const categoryBarCanvas = document.createElement('canvas');
                        categoryBarCanvas.width = chartWidth;
                        categoryBarCanvas.height = chartHeight;
                        document.body.appendChild(categoryBarCanvas);

                            new Chart(categoryBarCanvas, {
                                type: 'bar',
                                data: {
                                    labels: Object.keys(categoryCount),
                                    datasets: [{
                                        label: 'Tickets por Categoría',
                                        data: Object.values(categoryCount),
                                        backgroundColor: [
                                            'rgba(54, 162, 235, 0.7)',
                                            'rgba(255, 99, 132, 0.7)',
                                            'rgba(14, 98, 154, 0.7)',
                                            'rgba(255, 206, 86, 0.7)',
                                            'rgba(75, 192, 192, 0.7)',
                                            'rgba(153, 102, 255, 0.7)',
                                            'rgba(255, 159, 64, 0.7)'
                                        ]
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: true,
                                    plugins: {
                                        legend: { 
                                            display: false 
                                        },
                                        title: {
                                            display: true,
                                            text: 'Tickets por Categoría',
                                            font: { size: 50 },
                                            padding: 20
                                        }
                                    },
                                    scales: {
                                        x: {
                                            ticks: { 
                                                font: { size: 30 },
                                                padding: 10
                                            }
                                        },
                                        y: {
                                        beginAtZero: true,
                                            ticks: { 
                                                font: { size: 30 },
                                                padding: 10
                                            }
                                        }
                                    }
                                }
                            });

                            setTimeout(function() {
                                const categoryBarImg = categoryBarCanvas.toDataURL('image/png');
                                const imgWidth = 350;
                                const imgHeight = (chartHeight * imgWidth) / chartWidth;
                                const currentPageWidthCat = doc.internal.pageSize.getWidth();
                                const xPos = (currentPageWidthCat - imgWidth) / 2;
                                doc.addImage(categoryBarImg, 'PNG', xPos, y, imgWidth, imgHeight);
                            y += imgHeight + 30;

                            // 6. Tabla de resumen de categorías - PÁGINA 3
                            const resumenCategorias = Object.entries(categoryCount).map(([categoria, total], idx) => [idx + 1, categoria, total]);
                            
                            // Verificar espacio antes de agregar la tabla
                            checkSpace(150);
                            
                            doc.autoTable({
                                head: [['ID Categoría', 'Categoría', 'Total de Tickets']],
                                body: resumenCategorias,
                                startY: y,
                                styles: { fontSize: 9 },
                                headStyles: { fillColor: [75, 192, 192] },
                                margin: { left: 40, right: 40 },
                                theme: 'grid'
                            });

                            y = doc.lastAutoTable.finalY + 30;

                                // Eliminar canvases
                                document.body.removeChild(areaCanvas);
                                document.body.removeChild(tecnicoPieCanvas);
                                document.body.removeChild(categoryBarCanvas);

                            // Crear nueva página para la tabla principal (orientación horizontal)
                            doc.addPage('landscape', 'a4');
                            currentPage++;
                            y = 100;
                            addHeader(false); // Sin título en páginas adicionales
                            addFooter();
                            
                            // 7. Tabla principal con tickets - PÁGINA 4 (HORIZONTAL)
                                const columns = [
                                    { header: 'Ticket', dataKey: 0 },
                                    { header: 'Fecha Creación', dataKey: 1 },
                                    { header: 'Empresa', dataKey: 2 },
                                    { header: 'Solicitante', dataKey: 3 },
                                    { header: 'Correo', dataKey: 4 },
                                    { header: 'Descripción', dataKey: 5 },
                                    { header: 'Categoría', dataKey: 6 },
                                    { header: 'Estado', dataKey: 7 },
                                    { header: 'Técnico', dataKey: 9 },
                                    { header: 'Tiempo Total', dataKey: 10 },
                                    { header: 'Tiempo Espera', dataKey: 11 }
                                ];
                                
                                // Mapear datos de las filas
                                const allData = rows.map(row => {
                                    const cells = row.querySelectorAll('td');
                                    // Limpiar y truncar texto largo para evitar problemas de renderizado
                                    const cleanText = (text, maxLength = 100) => {
                                        if (!text) return '';
                                        let cleaned = text.toString().trim();
                                        // Reemplazar múltiples espacios y saltos de línea
                                        cleaned = cleaned.replace(/\s+/g, ' ');
                                        cleaned = cleaned.replace(/\n/g, ' ');
                                        // Truncar si es muy largo
                                        if (cleaned.length > maxLength) {
                                            cleaned = cleaned.substring(0, maxLength) + '...';
                                        }
                                        return cleaned;
                                    };
                                    
                                    return {
                                        ticket: cleanText(cells[0]?.innerText || '', 20),
                                        fecha: cleanText(cells[1]?.innerText || '', 20),
                                        empresa: cleanText(cells[2]?.innerText || '', 30),
                                        solicitante: cleanText(cells[3]?.innerText || '', 30),
                                        correo: cleanText(cells[4]?.innerText || '', 40),
                                        descripcion: cleanText(cells[5]?.innerText || 'N/A', 150), // Descripción con más caracteres permitidos
                                        categoria: cleanText(cells[6]?.innerText || '', 20),
                                        estado: cleanText(cells[7]?.innerText || '', 20),
                                        tecnico: cleanText(cells[9]?.innerText || '', 30),
                                        tiempoTotal: cleanText(cells[10]?.innerText || '', 10),
                                        tiempoEspera: cleanText(cells[11]?.innerText || '', 10)
                                    };
                                });
                                
                                // Agrupar por solicitante
                                const groupedByRequester = {};
                                allData.forEach(item => {
                                    const requester = item.solicitante || 'Sin Solicitante';
                                    if (!groupedByRequester[requester]) {
                                        groupedByRequester[requester] = [];
                                    }
                                    groupedByRequester[requester].push(item);
                                });
                                
                                // Crear estructura de datos agrupada con encabezados y contadores
                                const groupedData = [];
                                Object.keys(groupedByRequester).forEach(requester => {
                                    const tickets = groupedByRequester[requester];
                                    const count = tickets.length;
                                    
                                    // Agregar encabezado del solicitante
                                    groupedData.push([
                                        { content: `SOLICITANTE: ${requester}`, colSpan: 11, styles: { fillColor: [74, 194, 74], textColor: 255, fontStyle: 'bold', fontSize: 8 } }
                                    ]);
                                    
                                    // Agregar tickets del solicitante
                                    tickets.forEach(ticket => {
                                        groupedData.push([
                                            ticket.ticket,
                                            ticket.fecha,
                                            ticket.empresa,
                                            ticket.solicitante,
                                            ticket.correo,
                                            ticket.descripcion,
                                            ticket.categoria,
                                            ticket.estado,
                                            ticket.tecnico,
                                            ticket.tiempoTotal,
                                            ticket.tiempoEspera
                                        ]);
                                    });
                                    
                                    // Agregar fila de contador
                                    groupedData.push([
                                        { content: `Total de tickets para ${requester}: ${count}`, colSpan: 11, styles: { fillColor: [220, 220, 220], fontStyle: 'bold', fontSize: 7 } }
                                    ]);
                                    
                                    // Agregar fila en blanco para separación
                                    groupedData.push([
                                        { content: '', colSpan: 11, styles: { fillColor: [255, 255, 255] } }
                                    ]);
                                });
                                
                                // Obtener ancho de página en landscape (A4 landscape = ~792pt)
                                const landscapePageWidth = doc.internal.pageSize.getWidth();
                                const availableWidth = landscapePageWidth - 40; // Restar márgenes (20 cada lado)
                                
                                doc.autoTable({
                                    head: [columns.map(col => col.header)],
                                    body: groupedData,
                                    startY: y,
                                    styles: { 
                                        fontSize: 6,
                                        cellPadding: 2,
                                        overflow: 'linebreak',
                                        cellWidth: 'wrap',
                                        halign: 'left',
                                        valign: 'middle'
                                    },
                                    headStyles: { 
                                        fillColor: [49, 122, 48],
                                        textColor: 255,
                                        fontSize: 7,
                                        fontStyle: 'bold',
                                        overflow: 'linebreak',
                                        cellWidth: 'wrap',
                                        halign: 'center',
                                        valign: 'middle'
                                    },
                                    margin: { left: 20, right: 20 },
                                    theme: 'grid',
                                    tableWidth: 'auto',
                                    columnStyles: {
                                        0: { cellWidth: 40, fontSize: 6, halign: 'center', overflow: 'linebreak' },   // Ticket
                                        1: { cellWidth: 55, fontSize: 6, halign: 'center', overflow: 'linebreak' },   // Fecha Creación
                                        2: { cellWidth: 60, fontSize: 6, overflow: 'linebreak' },                     // Empresa
                                        3: { cellWidth: 65, fontSize: 6, overflow: 'linebreak' },                     // Solicitante
                                        4: { cellWidth: 75, fontSize: 5, overflow: 'linebreak' },                    // Correo
                                        5: { cellWidth: 200, fontSize: 5, overflow: 'linebreak' }, // Descripción (mucho más ancha)
                                        6: { cellWidth: 50, fontSize: 6, overflow: 'linebreak' },                     // Categoría
                                        7: { cellWidth: 50, fontSize: 6, halign: 'center', overflow: 'linebreak' },   // Estado
                                        9: { cellWidth: 70, fontSize: 6, overflow: 'linebreak' },                     // Técnico
                                        10: { cellWidth: 45, fontSize: 6, halign: 'center', overflow: 'linebreak' },  // Tiempo Total
                                        11: { cellWidth: 45, fontSize: 6, halign: 'center', overflow: 'linebreak' }   // Tiempo Espera
                                    },
                                    didParseCell: function(data) {
                                        // Evitar rotación de texto - forzar linebreak en todas las celdas
                                        data.cell.styles.overflow = 'linebreak';
                                        data.cell.styles.cellWidth = 'wrap';
                                        data.cell.styles.halign = data.cell.styles.halign || 'left';
                                        data.cell.styles.valign = 'middle';
                                        
                                        // Para filas especiales (encabezados y contadores), mantener el estilo personalizado
                                        if (data.row.raw && data.row.raw[0] && typeof data.row.raw[0] === 'object' && data.row.raw[0].content) {
                                            // Es una fila especial con objeto de contenido (encabezado o contador)
                                            if (data.row.raw[0].styles) {
                                                Object.assign(data.cell.styles, data.row.raw[0].styles);
                                            }
                                            data.cell.styles.overflow = 'linebreak';
                                            data.cell.styles.halign = 'center';
                                            return;
                                        }
                                        
                                        // Para celdas normales, asegurar que no se rote el texto
                                        if (data.cell.text && typeof data.cell.text === 'string') {
                                            // Limpiar texto muy largo
                                            if (data.cell.text.length > 100) {
                                                data.cell.text = data.cell.text.substring(0, 100) + '...';
                                            }
                                        }
                                    },
                                    didDrawPage: function (data) {
                                    // Agregar encabezado y pie de página en cada página de la tabla
                                    if (data.pageNumber > currentPage) {
                                        currentPage = data.pageNumber;
                                        addHeader(false); // Sin título en páginas adicionales
                                        addFooter();
                                    }
                                }
                            });

                            y = doc.lastAutoTable.finalY + 30;
                            
                            // Crear nueva página para la actividad de usuarios (orientación horizontal)
                            doc.addPage('landscape', 'a4');
                            currentPage++;
                            y = 100;
                            addHeader(false); // Sin título en páginas adicionales
                            addFooter();
                            
                            // 8. Gráfica de actividad de usuarios - PÁGINA 5
                            const userActivityCanvas = document.createElement('canvas');
                            userActivityCanvas.width = 420;
                            userActivityCanvas.height = 210;
                            document.body.appendChild(userActivityCanvas);

                            const userActivityCtx = userActivityCanvas.getContext('2d');
                            const userData = <?php echo json_encode($history_data); ?>;
                            
                            new Chart(userActivityCtx, {
                                type: 'line',
                                data: {
                                    labels: userData.map(user => user.user_name),
                                    datasets: [{
                                        label: 'Tickets Creados',
                                        data: userData.map(user => user.tickets_creados),
                                        backgroundColor: 'rgba(40, 167, 69, 0.3)',
                                        borderColor: 'rgba(40, 167, 69, 1)',
                                        borderWidth: 3,
                                        fill: true,
                                        tension: 0.4
                                    }, {
                                        label: 'Tickets Cerrados',
                                        data: userData.map(user => user.tickets_cerrados),
                                        backgroundColor: 'rgba(220, 53, 69, 0.3)',
                                        borderColor: 'rgba(220, 53, 69, 1)',
                                        borderWidth: 3,
                                        fill: true,
                                        tension: 0.4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: true,
                                    plugins: {
                                        legend: {
                                            position: 'top',
                                            labels: { 
                                                font: { size: 40 },
                                                padding: 20
                                            }
                                        },
                                        title: {
                                            display: true,
                                            text: 'Actividad de Usuarios - Tickets Creados vs Cerrados',
                                            font: { size: 50 },
                                            padding: 20
                                        }
                                    },
                                    scales: {
                                        x: {
                                            ticks: { 
                                                font: { size: 30 },
                                                padding: 10,
                                                maxRotation: 45
                                            }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                stepSize: 1,
                                                font: { size: 30 },
                                                padding: 10
                                            }
                                        }
                                    }
                                }
                            });

                            setTimeout(function() {
                                const userActivityImg = userActivityCanvas.toDataURL('image/png');
                                const imgWidth = 350;
                                const imgHeight = (210 * imgWidth) / 420;
                                const currentPageWidthUser = doc.internal.pageSize.getWidth();
                                const xPos = (currentPageWidthUser - imgWidth) / 2;
                                doc.addImage(userActivityImg, 'PNG', xPos, y, imgWidth, imgHeight);
                                y += imgHeight + 30;

                                // Eliminar canvas
                                document.body.removeChild(userActivityCanvas);
                                
                                // 9. Tabla de resumen de usuarios - PÁGINA 5
                                const historyTable = document.getElementById('historyTable');
                                if (historyTable) {
                                    const historyRows = Array.from(historyTable.querySelectorAll('tbody tr'));
                                    const historyData = historyRows.map(row => {
                                        const cells = row.querySelectorAll('td, th');
                                        return [
                                            cells[0]?.innerText,
                                            cells[1]?.innerText,
                                            cells[2]?.innerText,
                                            cells[3]?.innerText
                                        ];
                                    });
                                    
                                    // Verificar espacio antes de agregar la tabla
                                    checkSpace(150);
                                    
                                    doc.autoTable({
                                        head: [['ID Usuario', 'Usuario', 'Total Tickets Creados', 'Total Tickets Cerrados']],
                                        body: historyData,
                                        startY: y,
                                        styles: { fontSize: 8 },
                                        headStyles: { fillColor: [108, 117, 125] },
                                        margin: { left: 20, right: 20 },
                                        theme: 'grid'
                                    });
                                }
                                
                                // 10. Resumen de tareas — al final del reporte
                                renderTasksReportSection(function() {
                                    doc.save('reporte_tickets.pdf');
                                });
                            }, 800);
                        }, 800);
                    }, 800);
                    };

                    const costTableElement = document.getElementById('costTable');
                    const costRows = costTableElement ? Array.from(costTableElement.querySelectorAll('tbody tr')) : [];

                    if (costRows.length) {
                        doc.addPage('landscape', 'a4');
                        currentPage++;
                        y = 100;
                        addHeader(false); // Sin título en páginas adicionales
                        addFooter();

                        const costCanvas = document.createElement('canvas');
                        costCanvas.width = chartWidth;
                        costCanvas.height = chartHeight;
                        document.body.appendChild(costCanvas);

                        // Filtrar filas excluyendo la última (total)
                        const dataRows = costRows.filter(row => !row.classList.contains('cost-total-row'));
                        
                        const costLabels = dataRows.map(row => row.querySelector('td')?.innerText || '');
                        const costTotals = dataRows.map(row => {
                            const cells = row.querySelectorAll('td, th');
                            const rawValue = cells[3]?.innerText || '0';
                            return parseFloat(rawValue.replace(/[^0-9.-]+/g, '')) || 0;
                        });

                        new Chart(costCanvas, {
                            type: 'bar',
                            data: {
                                labels: costLabels,
                                datasets: [{
                                    label: 'Total de Costos (MXN)',
                                    data: costTotals,
                        backgroundColor: 'rgba(34, 94, 78, 0.85)',
                        borderColor: 'rgba(17, 43, 33, 1)',
                                    borderWidth: 2
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: true,
                                plugins: {
                                    legend: { display: false },
                                    title: {
                                        display: true,
                                        text: 'Costos registrados por ticket',
                                        font: {
                                            size: 50,
                                            weight: 'bold'
                                        },
                                        padding: 20
                                    },
                                    tooltip: {
                                        titleFont: {
                                            size: 30
                                        },
                                        bodyFont: {
                                            size: 30
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        ticks: {
                                            font: {
                                                size: 30
                                            }
                                        },
                                        title: {
                                            display: true,
                                            text: 'Tickets',
                                            font: {
                                                size: 40,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            font: {
                                                size: 30
                                            }
                                        },
                                        title: {
                                            display: true,
                                            text: 'Costo (MXN)',
                                            font: {
                                                size: 40,
                                                weight: 'bold'
                                            }
                                        }
                                    }
                                }
                            }
                        });

                        setTimeout(function() {
                            const costImg = costCanvas.toDataURL('image/png');
                            const imgWidth = 350;
                            const imgHeight = (chartHeight * imgWidth) / chartWidth;
                            const currentPageWidthCost = doc.internal.pageSize.getWidth();
                            const xPos = (currentPageWidthCost - imgWidth) / 2;
                            doc.addImage(costImg, 'PNG', xPos, y, imgWidth, imgHeight);
                            y += imgHeight + 30;

                            // Separar filas de datos y fila de total
                        const dataRowsForTable = costRows.filter(row => !row.classList.contains('cost-total-row'));
                            
                            const costTableData = dataRowsForTable.map(row => {
                                const cells = row.querySelectorAll('td, th');
                                return [
                                    cells[0]?.innerText || '',
                                    cells[1]?.innerText || '',
                                    cells[2]?.innerText || '',
                                    cells[3]?.innerText || '',
                                    cells[4]?.innerText || '',
                                    cells[5]?.innerText || ''
                                ];
                            });
                            
                            // Agregar fila de totales
                        costTableData.push([
                            'TOTAL GENERAL',
                            '',
                            '',
                            <?php echo json_encode('$' . number_format($total_cost_amount, 2)); ?>,
                            <?php echo json_encode((string)$total_cost_entries); ?>,
                            '-'
                        ]);

                            checkSpace(150);

                            doc.autoTable({
                                head: [['Ticket', 'Empresa', 'Descripción', 'Total Costos', 'Registros', 'Último Gasto']],
                                body: costTableData,
                                startY: y,
                                styles: { fontSize: 8 },
                                headStyles: { fillColor: [34, 94, 78], textColor: 255 },
                                margin: { left: 40, right: 40 },
                                theme: 'grid',
                                columnStyles: {
                                    2: { cellWidth: 'auto', fontSize: 7 }
                                },
                                didParseCell: function(data) {
                                    // Resaltar la última fila (total)
                                    if (data.row.index === costTableData.length - 1) {
                                        data.cell.styles.fontStyle = 'bold';
                                        data.cell.styles.fillColor = [9, 80, 55];
                                        data.cell.styles.textColor = 255;
                                        data.cell.styles.fontSize = 10;
                                    }
                                }
                            });

                            document.body.removeChild(costCanvas);

                            y = doc.lastAutoTable.finalY + 30;

                            renderRemainingSections();
                        }, 800);
                    } else {
                        renderRemainingSections();
                    }
                }, 800);
            }, 100);
        }
    </script>
</body>
</html>               