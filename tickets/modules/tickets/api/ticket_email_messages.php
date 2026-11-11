<?php
/**
 * Fragmento HTML de la conversación por correo (auto-refresh).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/common.php';
require_once dirname(__DIR__, 3) . '/conexion/db.php';
require_once dirname(__DIR__, 4) . '/auth.php';
require_once dirname(__DIR__, 4) . '/includes/empresa_helper.php';
require_once dirname(__DIR__, 3) . '/includes/whatsapp_ticket_bridge.php';
require_once dirname(__DIR__, 3) . '/includes/ticket_email_conversation_helper.php';

checkAuth();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$ticketId = (int) ($_GET['ticket_id'] ?? 0);

try {
    if ($ticketId <= 0) {
        throw new RuntimeException('Ticket inválido');
    }

    $params = [$ticketId];
    $types = 'i';
    $query = 'SELECT t.* FROM tickets t WHERE t.id = ?';
    $query = addEmpresaFilter($query, 't.id_empresa', $params, $types);

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ticket) {
        throw new RuntimeException('Ticket no encontrado o sin acceso');
    }

    $isWhatsappTicket = ticketsWhatsappIsTicket($conn, $ticket);
    $rendered = ticketEmailRenderConversation($conn, $ticketId, $isWhatsappTicket);

    echo json_encode([
        'ok' => true,
        'html' => $rendered['html'],
        'count' => $rendered['count'],
        'last_message_id' => $rendered['last_message_id'],
        'updated_at' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
