<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__, 2) . '/conexion/db.php';
require_once dirname(__DIR__, 2) . '/includes/whatsapp_ticket_bridge.php';

$ticketId = (int) ($argv[1] ?? 1977);

echo "Testing sync for ticket {$ticketId}\n";

try {
    $sync = ticketsWhatsappSyncInboundForTicket($conn, $ticketId);
    echo "Sync: " . json_encode($sync, JSON_PRETTY_PRINT) . "\n";
    $msgs = ticketsWhatsappFetchTicketMessages($conn, $ticketId, 0);
    echo "Messages count: " . count($msgs) . "\n";
    foreach ($msgs as $m) {
        echo "  id={$m['id']} {$m['sender_type']}: " . substr((string)$m['message'], 0, 40) . "\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
