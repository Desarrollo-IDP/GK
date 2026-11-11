<?php
require __DIR__ . '/../tickets/includes/whatsapp_ticket_bridge.php';
$conn = ticketsWhatsappDb();
if (!$conn) {
    fwrite(STDERR, "No DB\n");
    exit(1);
}
ticketsWhatsappEnsureSchema($conn);
echo ticketsWhatsappColumnExists($conn, 'ticket_messages', 'is_whatsapp') ? "schema_ok\n" : "schema_fail\n";

require_once __DIR__ . '/includes/webhook_handler.php';
$files = glob(__DIR__ . '/storage/incoming/*.json') ?: [];
if ($files) {
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $payload = json_decode((string) file_get_contents($files[0]), true);
    if (is_array($payload)) {
        foreach (whatsappParseIncomingMessages($payload) as $msg) {
            $r = ticketsWhatsappProcessInbound($msg);
            echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            break;
        }
    }
}
