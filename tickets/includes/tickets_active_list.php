<?php
/**
 * Consulta y renderizado de la tabla de tickets activos (lista principal).
 */

declare(strict_types=1);

function ticketsActiveListStatusBadgeClass(?string $status): string
{
    switch (strtolower((string) $status)) {
        case 'abierto':
            return 'primary';
        case 'cerrado':
            return 'secondary';
        case 'sin asignar':
            return 'warning';
        default:
            return 'secondary';
    }
}

function ticketsActiveListStatusIcon(?string $status): string
{
    switch (strtolower((string) $status)) {
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

/**
 * @return array{query:string,params:array<int,mixed>,types:string}
 */
function ticketsActiveListBuildQuery(array $filters): array
{
    $filter = (string) ($filters['filter'] ?? '');
    $statusFilter = (string) ($filters['status'] ?? '');
    $statusIdFilter = (int) ($filters['status_id'] ?? 0);
    $q = trim((string) ($filters['q'] ?? ''));
    $idFilter = trim((string) ($filters['id'] ?? ''));

    $params = [];
    $types = '';

    $select = "SELECT t.*, 
          c.name as category_name, 
          s.name as status_name,
          cm.name as contact_mode_name,
          r.name as requester_name,
          tech.name as technician_name,
          e.nombre as empresa_nombre
          FROM tickets t
          LEFT JOIN categories c ON t.category_id = c.id
          LEFT JOIN statuses s ON t.status_id = s.id
          LEFT JOIN contact_modes cm ON t.contact_mode_id = cm.id
          LEFT JOIN requesters r ON t.requester_id = r.id
          LEFT JOIN technicians tech ON t.technician_id = tech.id
          LEFT JOIN empresas_usuarios e ON t.id_empresa = e.id_empresa";

    if ($idFilter !== '' && is_numeric($idFilter)) {
        $query = $select . " WHERE t.id = ? AND t.deleted != 1";
        $params[] = (int) $idFilter;
        $types .= 'i';
        $query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
    } elseif ($q !== '' && is_numeric($q)) {
        $query = $select . " WHERE t.id = ? AND t.deleted != 1";
        $params[] = (int) $q;
        $types .= 'i';
        $query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
    } else {
        $query = $select . " WHERE t.closed_at IS NULL AND t.deleted != 1";
        $query = addEmpresaFilter($query, 't.id_empresa', $params, $types);
    }

    if ($filter === 'unassigned') {
        $query .= ' AND t.status_id = 1';
    } elseif ($statusIdFilter > 0) {
        $query .= ' AND t.status_id = ?';
        $params[] = $statusIdFilter;
        $types .= 'i';
    } elseif ($statusFilter !== '') {
        $query .= ' AND s.name = ?';
        $params[] = $statusFilter;
        $types .= 's';
    }

    $query .= ' ORDER BY t.created_at DESC';

    return ['query' => $query, 'params' => $params, 'types' => $types];
}

/**
 * @return mysqli_result|false
 */
function ticketsActiveListFetch(mysqli $conn, array $filters)
{
    $built = ticketsActiveListBuildQuery($filters);
    $stmt = $conn->prepare($built['query']);
    if (!$stmt) {
        return false;
    }
    if (!empty($built['params'])) {
        $stmt->bind_param($built['types'], ...$built['params']);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

function ticketsActiveListRenderTbody(mysqli_result $result): string
{
    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $ticketLabel = htmlspecialchars((string) ($row['ticket_number'] ?? $row['id']));
            $category = htmlspecialchars((string) ($row['category_name'] ?? 'No asignada'));
            $statusName = htmlspecialchars((string) ($row['status_name'] ?? 'Sin estado'));
            $badge = ticketsActiveListStatusBadgeClass($row['status_name'] ?? '');
            $icon = ticketsActiveListStatusIcon($row['status_name'] ?? '');
            $requester = htmlspecialchars((string) ($row['requester_name'] ?? 'No asignado'));
            $technician = htmlspecialchars((string) ($row['technician_name'] ?? 'No asignado'));
            $date = date('d/m/Y', strtotime((string) $row['created_at']));
            $time = date('H:i', strtotime((string) $row['created_at']));
            $id = (int) $row['id'];
            ?>
            <tr class="fade-in" data-ticket-id="<?php echo $id; ?>">
                <td data-label="Ticket">
                    <span class="font-semibold" style="color: #202654;">#<?php echo $ticketLabel; ?></span>
                </td>
                <td data-label="Categoría">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-tag text-muted me-2 d-none d-md-inline"></i>
                        <?php echo $category; ?>
                    </div>
                </td>
                <td data-label="Estado">
                    <span class="badge badge-<?php echo $badge; ?>">
                        <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                        <?php echo $statusName; ?>
                    </span>
                </td>
                <td class="tickets-col-optional" data-label="Solicitante">
                    <div class="d-flex align-items-center">
                        <div class="avatar me-2">
                            <i class="fas fa-user text-muted"></i>
                        </div>
                        <span><?php echo $requester; ?></span>
                    </div>
                </td>
                <td class="tickets-col-desktop-only" data-label="Técnico">
                    <div class="d-flex align-items-center">
                        <div class="avatar me-2">
                            <i class="fas fa-tools text-muted"></i>
                        </div>
                        <span><?php echo $technician; ?></span>
                    </div>
                </td>
                <td data-label="Fecha">
                    <div class="text-sm">
                        <div class="font-medium"><?php echo $date; ?></div>
                        <div class="text-muted tickets-col-lg-only"><?php echo $time; ?></div>
                    </div>
                </td>
                <td data-label="Acciones">
                    <div class="tickets-list-actions">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline" title="Ver detalles">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $id; ?>)" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php
        }
    } else {
        ?>
        <tr id="ticketsEmptyRow">
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
        <?php
    }
    return (string) ob_get_clean();
}
