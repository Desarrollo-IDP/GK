<?php
/**
 * API WhatsApp para vista de ticket (envío y actualización en vivo)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/auth.php';
require_once dirname(__DIR__, 2) . '/conexion/db.php';
require_once dirname(__DIR__, 2) . '/includes/whatsapp_ticket_bridge.php';
require_once dirname(__DIR__, 3) . '/includes/empresa_helper.php';

checkAuth();

if (ob_get_level() === 0) {
    ob_start();
}

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * @return array<string,mixed>
 */
function ticketsWhatsappApiLoadTicket(mysqli $conn, int $ticketId): array
{
    if ($ticketId <= 0) {
        throw new RuntimeException('Ticket inválido');
    }

    $params = [$ticketId];
    $types = 'i';
    $query = 'SELECT t.*, r.phone AS requester_phone, r.name AS requester_name,
              cm.name AS contact_mode_name
              FROM tickets t
              LEFT JOIN requesters r ON t.requester_id = r.id
              LEFT JOIN contact_modes cm ON t.contact_mode_id = cm.id
              WHERE t.id = ?';
    $query = addEmpresaFilter($query, 't.id_empresa', $params, $types);

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ticket) {
        throw new RuntimeException('Ticket no encontrado o sin acceso');
    }

    return $ticket;
}

try {
    $response = null;
    switch ($action) {
        case 'send':
            $ticketId = (int) ($_POST['ticket_id'] ?? 0);
            $technicianId = (int) ($_POST['technician_id'] ?? 0);
            $type = (string) ($_POST['type'] ?? 'text');
            $body = trim((string) ($_POST['body'] ?? ''));

            if ($technicianId <= 0) {
                throw new RuntimeException('Debe asignar un técnico al ticket');
            }

            $ticket = ticketsWhatsappApiLoadTicket($conn, $ticketId);
            if (!empty($ticket['closed_at'])) {
                throw new RuntimeException('El ticket está cerrado');
            }
            if (!ticketsWhatsappIsTicket($conn, $ticket)) {
                throw new RuntimeException('Este ticket no tiene habilitada la conversación por WhatsApp');
            }

            $phone = (string) ($ticket['requester_phone'] ?? '');
            $uploadedFile = null;
            if (!empty($_FILES['file']['tmp_name'])) {
                $uploadedFile = $_FILES['file'];
            }

            $result = ticketsWhatsappSendFromTechnician(
                $conn,
                $ticketId,
                $technicianId,
                $phone,
                $type,
                $body,
                $uploadedFile
            );

            if (!$result['ok']) {
                throw new RuntimeException($result['error'] ?? 'Error al enviar');
            }

            $messageId = (int) ($result['ticket_message_id'] ?? 0);
            $message = null;
            if ($messageId > 0) {
                $rows = ticketsWhatsappFetchTicketMessages($conn, $ticketId, $messageId - 1);
                $message = $rows[0] ?? null;
            }

            $response = [
                'ok' => true,
                'result' => $result,
                'message' => $message,
            ];
            break;

        case 'sync':
            $ticketId = (int) ($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0);
            ticketsWhatsappApiLoadTicket($conn, $ticketId);
            $sync = ticketsWhatsappSyncInboundForTicket($conn, $ticketId, true);
            $response = ['ok' => true, 'sync' => $sync];
            break;

        case 'messages':
            $ticketId = (int) ($_GET['ticket_id'] ?? 0);
            $sinceId = (int) ($_GET['since_id'] ?? 0);
            $doSync = ($_GET['sync'] ?? '1') !== '0';
            $importWebhooks = ($_GET['import_webhooks'] ?? '0') === '1';

            ticketsWhatsappApiLoadTicket($conn, $ticketId);

            $sync = ['synced' => 0, 'skipped' => 0, 'errors' => 0];
            if ($doSync) {
                $sync = ticketsWhatsappSyncInboundForTicket($conn, $ticketId, $importWebhooks);
            }

            $messages = ticketsWhatsappFetchTicketMessages($conn, $ticketId, $sinceId);

            $response = [
                'ok' => true,
                'messages' => $messages,
                'sync' => $sync,
            ];
            break;

        default:
            http_response_code(400);
            $response = ['ok' => false, 'error' => 'Acción no válida'];
    }

    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
