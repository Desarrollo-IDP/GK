<?php
/**
 * API: notificaciones de mensajes entrantes (correo / WhatsApp) por ticket.
 *
 * GET  → listado con estado leído/no leído por usuario
 * POST → marcar como leídas (ids, ticket o todas)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../conexion/db.php';
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../includes/empresa_helper.php';

checkAuth();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$userId = (int) ($_SESSION['user']['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado', 'items' => [], 'count' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Asegura la tabla de lecturas.
 */
function notificationsEnsureReadsTable(mysqli $conn): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $check = $conn->query("SHOW TABLES LIKE 'ticket_notification_reads'");
    if ($check && $check->num_rows > 0) {
        $ready = true;
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS ticket_notification_reads (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            message_id INT NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_message (user_id, message_id),
            KEY idx_user_read_at (user_id, read_at),
            KEY idx_message_id (message_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ready = true;
}

/**
 * @return array{0: string, 1: bool} [sqlFragment, hasWhatsappCol]
 */
function notificationsInboundSqlParts(mysqli $conn): array
{
    $hasWaCol = false;
    $waCheck = $conn->query("SHOW COLUMNS FROM ticket_messages LIKE 'is_whatsapp'");
    if ($waCheck && $waCheck->num_rows > 0) {
        $hasWaCol = true;
    }

    $channelExpr = $hasWaCol
        ? "CASE WHEN tm.is_whatsapp = 1 THEN 'whatsapp' WHEN tm.is_email = 1 THEN 'email' ELSE 'message' END"
        : "CASE WHEN tm.is_email = 1 THEN 'email' ELSE 'message' END";

    $inboundFilter = $hasWaCol
        ? 'AND (tm.is_email = 1 OR tm.is_whatsapp = 1)'
        : 'AND tm.is_email = 1';

    return [$channelExpr, $inboundFilter, $hasWaCol];
}

/**
 * Inserta lecturas ignorando duplicados.
 *
 * @param list<int> $messageIds
 */
function notificationsMarkMessageIds(mysqli $conn, int $userId, array $messageIds): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $messageIds), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return 0;
    }

    $stmt = $conn->prepare('
        INSERT IGNORE INTO ticket_notification_reads (user_id, message_id, read_at)
        VALUES (?, ?, NOW())
    ');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar el marcado de lectura');
    }

    $marked = 0;
    foreach ($ids as $messageId) {
        $stmt->bind_param('ii', $userId, $messageId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $marked++;
        }
    }
    $stmt->close();

    return $marked;
}

/**
 * Marca como leídos los mensajes entrantes de un ticket abierto (filtro empresa).
 */
function notificationsMarkByTicket(mysqli $conn, int $userId, int $ticketId): int
{
    if ($ticketId <= 0) {
        return 0;
    }

    [, $inboundFilter] = notificationsInboundSqlParts($conn);
    $params = [$ticketId];
    $types = 'i';

    $sql = "
        SELECT tm.id
        FROM ticket_messages tm
        INNER JOIN tickets t ON t.id = tm.ticket_id
        WHERE tm.ticket_id = ?
          AND tm.sender_type = 'requester'
          AND (t.deleted = 0 OR t.deleted IS NULL)
          AND t.closed_at IS NULL
          {$inboundFilter}
    ";
    $sql = addEmpresaFilter($sql, 't.id_empresa', $params, $types);

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int) $row['id'];
    }
    $stmt->close();

    return notificationsMarkMessageIds($conn, $userId, $ids);
}

/**
 * Marca todas las notificaciones visibles (tickets abiertos, filtro empresa).
 */
function notificationsMarkAllVisible(mysqli $conn, int $userId, int $limit = 100): int
{
    [, $inboundFilter] = notificationsInboundSqlParts($conn);
    $params = [];
    $types = '';

    $sql = "
        SELECT tm.id
        FROM ticket_messages tm
        INNER JOIN tickets t ON t.id = tm.ticket_id
        LEFT JOIN ticket_notification_reads nr
            ON nr.message_id = tm.id AND nr.user_id = {$userId}
        WHERE tm.sender_type = 'requester'
          AND tm.ticket_id IS NOT NULL
          AND (t.deleted = 0 OR t.deleted IS NULL)
          AND t.closed_at IS NULL
          AND nr.id IS NULL
          {$inboundFilter}
    ";
    $sql = addEmpresaFilter($sql, 't.id_empresa', $params, $types);
    $sql .= ' ORDER BY tm.created_at DESC, tm.id DESC LIMIT ' . max(1, min(200, $limit));

    if ($types !== '') {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
        $stmt = null;
    }

    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int) $row['id'];
    }
    if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    return notificationsMarkMessageIds($conn, $userId, $ids);
}

try {
    notificationsEnsureReadsTable($conn);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $payload = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        if ($payload === []) {
            $payload = $_POST;
        }

        $action = (string) ($payload['action'] ?? 'mark_read');
        $marked = 0;

        if ($action === 'mark_all') {
            $marked = notificationsMarkAllVisible($conn, $userId, (int) ($payload['limit'] ?? 100));
        } elseif ($action === 'mark_ticket') {
            $marked = notificationsMarkByTicket($conn, $userId, (int) ($payload['ticket_id'] ?? 0));
        } else {
            $ids = $payload['message_ids'] ?? $payload['ids'] ?? [];
            if (!is_array($ids)) {
                $ids = [];
            }
            if (isset($payload['message_id'])) {
                $ids[] = (int) $payload['message_id'];
            }
            $marked = notificationsMarkMessageIds($conn, $userId, $ids);
        }

        echo json_encode([
            'ok' => true,
            'action' => $action,
            'marked' => $marked,
            'user_id' => $userId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // GET — listado
    $limit = (int) ($_GET['limit'] ?? 25);
    if ($limit < 5) {
        $limit = 5;
    }
    if ($limit > 50) {
        $limit = 50;
    }

    [$channelExpr, $inboundFilter] = notificationsInboundSqlParts($conn);
    $params = [];
    $types = '';

    $sql = "
        SELECT
            tm.id,
            tm.ticket_id,
            tm.sender_name,
            tm.sender_email,
            tm.message,
            tm.created_at,
            tm.is_email,
            {$channelExpr} AS channel,
            t.ticket_number,
            t.subject AS ticket_subject,
            t.status_id,
            CASE WHEN nr.id IS NULL THEN 0 ELSE 1 END AS is_read
        FROM ticket_messages tm
        INNER JOIN tickets t ON t.id = tm.ticket_id
        LEFT JOIN ticket_notification_reads nr
            ON nr.message_id = tm.id AND nr.user_id = ?
        WHERE tm.sender_type = 'requester'
          AND tm.ticket_id IS NOT NULL
          AND (t.deleted = 0 OR t.deleted IS NULL)
          AND t.closed_at IS NULL
          {$inboundFilter}
    ";

    $params[] = $userId;
    $types .= 'i';
    $sql = addEmpresaFilter($sql, 't.id_empresa', $params, $types);
    $sql .= ' ORDER BY is_read ASC, tm.created_at DESC, tm.id DESC LIMIT ' . $limit;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    $unreadCount = 0;
    while ($row = $result->fetch_assoc()) {
        $plain = trim(html_entity_decode(strip_tags((string) ($row['message'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plain = preg_replace('/\s+/u', ' ', $plain ?? '') ?? '';
        if (mb_strlen($plain) > 140) {
            $plain = mb_substr($plain, 0, 140) . '…';
        }

        $ticketNumber = $row['ticket_number'] ?? $row['ticket_id'];
        $sender = trim((string) ($row['sender_name'] ?? ''));
        if ($sender === '') {
            $sender = trim((string) ($row['sender_email'] ?? '')) ?: 'Solicitante';
        }

        $channel = (string) ($row['channel'] ?? 'message');
        if ($channel === 'whatsapp') {
            $channelLabel = 'WhatsApp';
        } elseif ($channel === 'email') {
            $channelLabel = 'Correo';
        } else {
            $channelLabel = 'Mensaje';
        }

        $isRead = (int) ($row['is_read'] ?? 0) === 1;
        if (!$isRead) {
            $unreadCount++;
        }

        $items[] = [
            'id' => (int) $row['id'],
            'ticket_id' => (int) $row['ticket_id'],
            'ticket_number' => (string) $ticketNumber,
            'ticket_subject' => (string) ($row['ticket_subject'] ?? ''),
            'sender_name' => $sender,
            'preview' => $plain !== '' ? $plain : '(Sin texto)',
            'channel' => $channel,
            'channel_label' => $channelLabel,
            'is_read' => $isRead,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'created_at_iso' => !empty($row['created_at']) ? date('c', strtotime($row['created_at'])) : null,
        ];
    }
    $stmt->close();

    // Conteo total de no leídas (no limitado al listado)
    $countParams = [];
    $countTypes = '';
    $countSql = "
        SELECT COUNT(*) AS total
        FROM ticket_messages tm
        INNER JOIN tickets t ON t.id = tm.ticket_id
        LEFT JOIN ticket_notification_reads nr
            ON nr.message_id = tm.id AND nr.user_id = ?
        WHERE tm.sender_type = 'requester'
          AND tm.ticket_id IS NOT NULL
          AND (t.deleted = 0 OR t.deleted IS NULL)
          AND t.closed_at IS NULL
          AND nr.id IS NULL
          {$inboundFilter}
    ";
    $countParams[] = $userId;
    $countTypes .= 'i';
    $countSql = addEmpresaFilter($countSql, 't.id_empresa', $countParams, $countTypes);

    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param($countTypes, ...$countParams);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();
    $totalUnread = (int) ($countRow['total'] ?? $unreadCount);

    echo json_encode([
        'ok' => true,
        'user_id' => $userId,
        'count' => count($items),
        'unread_count' => $totalUnread,
        'items' => $items,
        'updated_at' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'items' => [],
        'count' => 0,
        'unread_count' => 0,
    ], JSON_UNESCAPED_UNICODE);
}
