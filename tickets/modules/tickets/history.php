<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/ticket_display_helper.php';

// Verificar autenticación
checkAuth();

// Enforce per-user access (admin bypass inside helper)
if (!(function_exists('canViewTicketHistory') && canViewTicketHistory())) {
    // Supervisores/visualizadores sin flag no pueden entrar directamente
    header('Location: tickets.php');
    exit;
}

// Logging de diagnóstico: inicio del script
error_log('[Tickets] [history.php] Inicio. GET=' . json_encode($_GET) . ', usuario=' . (function_exists('getUserName') ? getUserName() : 'desconocido') . ', ip=' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'));

// Verificar que se proporcione un ID de ticket
if (!isset($_GET['id']) || empty($_GET['id'])) {
    error_log('[Tickets] [history.php] Falta parámetro id en la URL. Redirigiendo a tickets.php');
    header('Location: tickets.php');
    exit;
}

$ticket_id = $_GET['id'];
error_log('[Tickets] [history.php] Procesando ticket_id=' . $ticket_id);

// Verificar que el ticket existe y pertenece a la empresa del usuario
$query = "SELECT id FROM tickets WHERE id = ?";
$params = [$ticket_id];
$types = 'i';
$query = addEmpresaFilter($query, 'id_empresa', $params, $types);
$ticket_check = $conn->prepare($query);
$ticket_check->bind_param($types, ...$params);
$ticket_check->execute();
$ticket_result = $ticket_check->get_result();

if ($ticket_result->num_rows === 0) {
    error_log('[Tickets] [history.php] Ticket no encontrado o sin acceso. id=' . $ticket_id . '. Redirigiendo a tickets.php');
    header('Location: tickets.php');
    exit;
}

// Obtener información del ticket con filtro de empresa
$query = "SELECT t.*, r.name as requester_name, c.name as category_name, s.name as status_name, eu.nombre as empresa_nombre
          FROM tickets t 
          LEFT JOIN requesters r ON t.requester_id = r.id 
          LEFT JOIN categories c ON t.category_id = c.id 
          LEFT JOIN statuses s ON t.status_id = s.id 
          LEFT JOIN empresas_usuarios eu ON t.id_empresa = eu.id_empresa
          WHERE t.id = ?";
$params = [$ticket_id];
$types = 'i';
$query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
$ticket_info = $conn->prepare($query);
$ticket_info->bind_param($types, ...$params);
$ticket_info->execute();
$ticket_data = $ticket_info->get_result()->fetch_assoc();
error_log('[Tickets] [history.php] Información del ticket cargada correctamente. id=' . $ticket_id);

// Obtener el historial del ticket
$history_query = "SELECT * FROM ticket_history WHERE ticket_id = ? ORDER BY created_at DESC";
$history_stmt = $conn->prepare($history_query);
$history_stmt->bind_param("i", $ticket_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
error_log('[Tickets] [history.php] Historial recuperado. id=' . $ticket_id . ', registros=' . ($history_result ? $history_result->num_rows : 0));
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
}
.history-item {
    border-left: 4px solid #e9ecef;
    padding-left: 1rem;
    margin-bottom: 1.5rem;
    position: relative;
}
.history-item.created { border-left-color: #28a745; }
.history-item.updated { border-left-color: #007bff; }
.history-item.closed { border-left-color: #dc3545; }
.history-item.closed-inactivity { border-left-color: #ffc107; }
.history-item.deleted { border-left-color: #6c757d; }
.history-item.reopened { border-left-color: #ffc107; }
.history-icon {
    position: absolute;
    left: -12px;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: white;
}
.history-item.created .history-icon { background-color: #28a745; }
.history-item.updated .history-icon { background-color: #007bff; }
.history-item.closed .history-icon { background-color: #dc3545; }
.history-item.closed-inactivity .history-icon { background-color: #ffc107; color: #212529; }
.history-item.deleted .history-icon { background-color: #6c757d; }
.history-item.reopened .history-icon { background-color: #ffc107; }
</style>

<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-2">Historial del Ticket #<?php echo $ticket_id; ?></h1>
                    <p class="text-muted mb-0">Registro completo de cambios realizados en este ticket</p>
                </div>
                <div class="tickets-page-actions">
                    <a href="all_tickets.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver a Tickets
                    </a>
                    <a href="view.php?id=<?php echo $ticket_id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-eye me-2"></i>Ver Ticket
                    </a>
                </div>
            </div>

            <!-- Información del ticket -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Información del Ticket</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Solicitante:</strong> <?php echo htmlspecialchars($ticket_data['requester_name'] ?? 'No asignado'); ?></p>
                            <p><strong>Categoría:</strong> <?php echo htmlspecialchars($ticket_data['category_name'] ?? 'No asignada'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Estado:</strong>
                                <?php echo htmlspecialchars(ticketGetDisplayStatusNameFromRow($ticket_data)); ?>
                                <?php if (ticketIsClosedByInactivity($ticket_data)): ?>
                                <span class="badge bg-warning text-dark ms-1"><i class="fas fa-clock me-1"></i>Inactividad</span>
                                <?php endif; ?>
                                <?php if (!empty($ticket_data['deleted'])): ?>
                                <span class="badge bg-danger ms-1"><i class="fas fa-trash me-1"></i>Eliminado</span>
                                <?php endif; ?>
                            </p>
                            <p><strong>Creado:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket_data['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historial -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Historial de Cambios</h5>
                </div>
                <div class="card-body">
                    <?php if ($history_result->num_rows > 0): ?>
                        <div class="timeline">
                            <?php while ($history = $history_result->fetch_assoc()): ?>
                                <?php
                                $history_action = $history['action'];
                                if ($history_action === 'closed' && stripos((string) ($history['description'] ?? ''), 'inactividad') !== false) {
                                    $history_action = 'closed_inactivity';
                                }
                                $history_css = ticketHistoryActionCssClass($history_action);
                                ?>
                                <div class="history-item <?php echo htmlspecialchars($history_css); ?>">
                                    <div class="history-icon">
                                        <i class="fas <?php echo ticketHistoryActionIcon($history_action); ?>"></i>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-1"><?php echo htmlspecialchars(ticketHistoryActionLabel($history_action)); ?></h6>
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($history['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1"><strong>Usuario:</strong> <?php echo htmlspecialchars($history['user_name']); ?></p>
                                    
                                    <?php if (!empty($history['description'])): ?>
                                        <p class="mb-1"><strong>Descripción:</strong> <?php echo htmlspecialchars($history['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($history['field_name'])): ?>
                                        <div class="row mt-2">
                                            <div class="col-md-6">
                                                <small class="text-muted"><strong>Campo:</strong> <?php echo htmlspecialchars($history['field_name']); ?></small>
                                            </div>
                                        </div>
                                        <?php if (!empty($history['old_value']) || !empty($history['new_value'])): ?>
                                            <div class="row mt-2">
                                                <?php if (!empty($history['old_value'])): ?>
                                                    <div class="col-md-6">
                                                        <small class="text-danger"><strong>Valor anterior:</strong></small>
                                                        <div class="bg-light p-2 rounded">
                                                            <small><?php echo htmlspecialchars($history['old_value']); ?></small>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($history['new_value'])): ?>
                                                    <div class="col-md-6">
                                                        <small class="text-success"><strong>Valor nuevo:</strong></small>
                                                        <div class="bg-light p-2 rounded">
                                                            <small><?php echo htmlspecialchars($history['new_value']); ?></small>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No hay historial disponible para este ticket.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
</div>

<?php include '../../includes/footer.php'; ?>
</body>
</html>
