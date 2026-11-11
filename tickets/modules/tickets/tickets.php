<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/tickets_active_list.php';
require_once '../../includes/ticket_history.php';

if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Verificar acceso por empresa antes de eliminar
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
$id_filter = isset($_GET['id']) ? trim($_GET['id']) : '';

$list_filters = [
    'filter' => $filter,
    'status' => $status_filter,
    'status_id' => $status_id_filter,
    'q' => $q,
    'id' => $id_filter,
];

$result = ticketsActiveListFetch($conn, $list_filters);
if ($result === false) {
    die('Error al consultar tickets: ' . $conn->error);
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
.table-container {
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 2px 8px 0 rgba(32,38,84,0.04);
    margin-bottom: 2rem;
    padding: 0.5rem;
}
.tickets-table-status {
    font-size: 0.75rem;
    color: #6b7280;
    padding: 0.35rem 0.5rem 0;
    text-align: right;
}
.tickets-table-status .fa-circle {
    font-size: 0.45rem;
    vertical-align: middle;
}
.tickets-table-status.is-refreshing .fa-circle {
    color: #f59e0b;
}
.tickets-table-status.is-live .fa-circle {
    color: #22c55e;
    animation: ticketsPulse 2s infinite;
}
@keyframes ticketsPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.35; }
}
#ticketsTableBody.is-updating {
    opacity: 0.72;
    transition: opacity 0.2s ease;
}
</style>

<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-2">
                        Gestión de Tickets
                        <?php if ($id_filter !== ''): ?>
                            <?php
                            // Obtener ticket_number si existe para el filtro
                            $filter_ticket_query = "SELECT ticket_number FROM tickets WHERE id = ?";
                            $filter_ticket_stmt = $conn->prepare($filter_ticket_query);
                            $filter_ticket_stmt->bind_param("i", $id_filter);
                            $filter_ticket_stmt->execute();
                            $filter_ticket_result = $filter_ticket_stmt->get_result();
                            $filter_ticket_row = $filter_ticket_result->fetch_assoc();
                            $display_ticket_number = $filter_ticket_row['ticket_number'] ?? $id_filter;
                            $filter_ticket_stmt->close();
                            ?>
                            <span class="badge bg-primary ms-2">Filtrado: Ticket #<?php echo htmlspecialchars($display_ticket_number); ?></span>
                        <?php elseif ($filter === 'unassigned'): ?>
                            <span class="badge bg-danger ms-2">Sin Asignar</span>
                        <?php elseif ($status_id_filter > 0): ?>
                            <span class="badge bg-warning ms-2">
                                <?php 
                                $status_names = [1 => 'Sin Asignar', 2 => 'Abierto', 10 => 'En Proceso', 3 => 'Cerrado'];
                                echo isset($status_names[$status_id_filter]) ? $status_names[$status_id_filter] : "Status ID: $status_id_filter";
                                ?>
                            </span>
                        <?php elseif (!empty($status_filter)): ?>
                            <span class="badge bg-warning ms-2"><?php echo htmlspecialchars($status_filter); ?></span>
                        <?php endif; ?>
                    </h1>
                    <p class="text-muted mb-0">
                        <?php if ($id_filter !== ''): ?>
                            Mostrando ticket específico
                        <?php elseif ($filter === 'unassigned'): ?>
                            
                        <?php elseif ($status_id_filter > 0): ?>
                            
                        <?php elseif (!empty($status_filter)): ?>
                            
                        <?php else: ?>
                            Administra y da seguimiento a todos los tickets del sistema
                        <?php endif; ?>
                    </p>
                </div>
                <div class="tickets-page-actions">
                    <?php if ($id_filter !== '' || $filter === 'unassigned' || !empty($status_filter) || $status_id_filter > 0): ?>
                        <a href="tickets.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Limpiar Filtro
                        </a>
                    <?php endif; ?>
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

            <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="table-container" id="ticketsTableContainer">
                <!--<div class="tickets-table-status is-live" id="ticketsTableStatus">
                    <i class="fas fa-circle me-1"></i><span id="ticketsTableStatusText">Actualización automática cada 10 s</span>
                </div>-->
                <div class="table-responsive">
                    <table class="table align-middle tickets-list-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Categoría</th>
                                <th>Estado</th>
                                <th class="tickets-col-optional">Solicitante</th>
                                <th class="tickets-col-desktop-only">Técnico</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="ticketsTableBody">
                            <?php echo ticketsActiveListRenderTbody($result); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
</div>

<script>
function confirmDelete(id) {
    if (confirm('¿Está seguro de que desea eliminar este ticket? Esta acción no se puede deshacer.')) {
        window.location.href = 'tickets.php?delete=' + id;
    }
}

(function () {
    const POLL_MS = 10000;
    const tbody = document.getElementById('ticketsTableBody');
    const statusEl = document.getElementById('ticketsTableStatus');
    const statusText = document.getElementById('ticketsTableStatusText');
    if (!tbody) return;

    let polling = false;
    let timer = null;
    let lastSignature = '';

    function currentQueryString() {
        return window.location.search.replace(/^\?/, '');
    }

    function setStatus(mode, text) {
        if (!statusEl || !statusText) return;
        statusEl.classList.remove('is-live', 'is-refreshing');
        if (mode) statusEl.classList.add(mode);
        statusText.textContent = text;
    }

    function tableSignature(html) {
        const tmp = document.createElement('tbody');
        tmp.innerHTML = html;
        return Array.from(tmp.querySelectorAll('tr[data-ticket-id]'))
            .map((tr) => tr.dataset.ticketId + ':' + tr.innerHTML.length)
            .join('|');
    }

    async function refreshTicketsTable() {
        if (polling || document.hidden) return;
        polling = true;
        setStatus('is-refreshing', 'Actualizando lista…');
        tbody.classList.add('is-updating');

        try {
            const qs = currentQueryString();
            const url = 'api/tickets_table.php' + (qs ? '?' + qs + '&' : '?') + '_t=' + Date.now();
            const res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error al actualizar');

            const sig = tableSignature(data.html);
            if (sig !== lastSignature) {
                tbody.innerHTML = data.html;
                lastSignature = sig;
            }

            const time = new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            setStatus('is-live', 'Actualizado ' + time + ' · ' + (data.count ?? 0) + ' ticket(s)');
        } catch (e) {
            console.warn('Tickets poll:', e.message);
            setStatus('is-refreshing', 'Reintentando actualización…');
        } finally {
            tbody.classList.remove('is-updating');
            polling = false;
        }
    }

    lastSignature = tableSignature(tbody.innerHTML);
    refreshTicketsTable();
    timer = setInterval(refreshTicketsTable, POLL_MS);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refreshTicketsTable();
    });
})();
</script>

<?php include '../../includes/footer.php'; ?>
