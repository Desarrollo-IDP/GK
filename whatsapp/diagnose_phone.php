<?php
declare(strict_types=1);

require __DIR__ . '/../tickets/includes/whatsapp_ticket_bridge.php';

$phone = '525650299150';
$norm = whatsappChatNormalizePhone($phone);
$last10 = substr($norm, -10);

echo "=== Diagnóstico WhatsApp → Tickets ===\n";
echo "Teléfono buscado: {$norm} (últimos 10: {$last10})\n";
echo "WHATSAPP_DEFAULT_EMPRESA_ID: " . var_export(ticketsWhatsappDefaultEmpresaId(), true) . "\n\n";

// Chat local
$chatAll = whatsappChatLoadMessages(null, 5000);
$chatInbound = array_filter($chatAll, fn($m) => ($m['direction'] ?? '') === 'inbound' && ticketsWhatsappPhonesMatch($norm, (string)($m['phone'] ?? '')));
$chatOutbound = array_filter($chatAll, fn($m) => ($m['direction'] ?? '') === 'outbound' && ticketsWhatsappPhonesMatch($norm, (string)($m['phone'] ?? '')));
echo "--- Chat local (messages.jsonl) ---\n";
echo "Inbound desde este número: " . count($chatInbound) . "\n";
echo "Outbound hacia este número: " . count($chatOutbound) . "\n";
if ($chatInbound) {
    foreach ($chatInbound as $m) {
        echo "  IN  " . ($m['message_id'] ?? '?') . " | " . ($m['body'] ?? '') . " | " . ($m['created_at'] ?? '') . "\n";
    }
} else {
    echo "  (ningún mensaje ENTRANTE guardado — el webhook no recibió respuestas de este número en este servidor)\n";
}
foreach ($chatOutbound as $m) {
    echo "  OUT " . ($m['message_id'] ?? '?') . " | " . ($m['body'] ?? '') . " | " . ($m['created_at'] ?? '') . "\n";
}

$incomingFiles = glob(WHATSAPP_INCOMING_DIR . '/*.json') ?: [];
echo "\n--- Webhooks guardados (storage/incoming/) ---\n";
echo "Archivos JSON: " . count($incomingFiles) . "\n";

$conn = ticketsWhatsappDb();
if (!$conn) {
    echo "\nERROR: sin conexión a BD\n";
    exit(1);
}
ticketsWhatsappEnsureSchema($conn);

echo "\n--- Base de datos: requesters ---\n";
$stmt = $conn->prepare("SELECT id, name, email, phone, id_empresa, requester_number FROM requesters WHERE phone LIKE ? OR phone LIKE ? OR REPLACE(REPLACE(phone,' ',''),'+','') LIKE ?");
$likeFull = '%' . $norm . '%';
$like10 = '%' . $last10;
$stmt->bind_param('sss', $likeFull, $like10, $like10);
$stmt->execute();
$requesters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
if (!$requesters) {
    echo "  No hay solicitante con este teléfono\n";
} else {
    foreach ($requesters as $r) {
        echo "  id={$r['id']} name={$r['name']} phone={$r['phone']} id_empresa=" . ($r['id_empresa'] ?? 'NULL') . " email={$r['email']}\n";
    }
}

echo "\n--- Base de datos: tickets ---\n";
foreach ($requesters as $r) {
    $rid = (int) $r['id'];
    $tq = $conn->prepare("SELECT id, ticket_number, status_id, requester_id, id_empresa, closed_at, last_wa_message_id, contact_mode_id, created_at FROM tickets WHERE requester_id = ? ORDER BY id DESC LIMIT 10");
    $tq->bind_param('i', $rid);
    $tq->execute();
    $tickets = $tq->get_result()->fetch_all(MYSQLI_ASSOC);
    $tq->close();
    if (!$tickets) {
        echo "  Requester {$rid}: sin tickets\n";
    }
    foreach ($tickets as $t) {
        $open = empty($t['closed_at']) && (int)($t['status_id'] ?? 0) !== 3 ? 'ABIERTO' : 'cerrado';
        echo "  Ticket #{$t['ticket_number']} id={$t['id']} {$open} status_id={$t['status_id']} id_empresa=" . ($t['id_empresa'] ?? 'NULL') . " created={$t['created_at']}\n";
    }
}

echo "\n--- ticket_messages (wamid) para teléfonos relacionados ---\n";
if ($requesters) {
    $ids = array_column($requesters, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $mq = $conn->prepare("SELECT tm.id, tm.ticket_id, tm.message_id, tm.is_whatsapp, tm.message, tm.created_at, t.ticket_number
        FROM ticket_messages tm JOIN tickets t ON t.id = tm.ticket_id
        WHERE t.requester_id IN ($placeholders) ORDER BY tm.id DESC LIMIT 20");
    $mq->bind_param($types, ...$ids);
    $mq->execute();
    $msgs = $mq->get_result()->fetch_all(MYSQLI_ASSOC);
    $mq->close();
    if (!$msgs) {
        echo "  Sin mensajes en tickets para este solicitante\n";
    }
    foreach ($msgs as $m) {
        echo "  tm.id={$m['id']} ticket=#{$m['ticket_number']} wamid=" . substr((string)$m['message_id'], 0, 40) . "... wa={$m['is_whatsapp']} | " . substr((string)$m['message'], 0, 50) . "\n";
    }
}

echo "\n--- Duplicados message_id huérfanos (en ticket_messages sin ticket válido) ---\n";
$orphan = $conn->query("SELECT tm.id, tm.message_id, tm.ticket_id FROM ticket_messages tm LEFT JOIN tickets t ON t.id = tm.ticket_id WHERE tm.message_id LIKE 'wamid.%' AND t.id IS NULL LIMIT 5");
if ($orphan && $orphan->num_rows > 0) {
    while ($row = $orphan->fetch_assoc()) {
        echo "  HUÉRFANO tm.id={$row['id']} ticket_id={$row['ticket_id']} wamid={$row['message_id']}\n";
    }
} else {
    echo "  Ninguno detectado\n";
}

echo "\n--- Simulación: ¿se crearía ticket si llegara mensaje ahora? ---\n";
$empresaId = ticketsWhatsappDefaultEmpresaId();
$req = ticketsWhatsappFindRequesterByPhone($conn, $norm, $empresaId);
if ($req) {
    echo "Requester encontrado: id={$req['id']} id_empresa=" . ($req['id_empresa'] ?? 'NULL') . "\n";
    $open = ticketsWhatsappFindOpenTicket($conn, $req['id'], $empresaId);
    echo "Ticket abierto: " . ($open ? "#{$open['ticket_number']} id={$open['id']}" : 'ninguno → se crearía uno nuevo') . "\n";
} else {
    echo "Requester NO existe → se crearía requester + ticket nuevo al recibir mensaje\n";
}

echo "\n--- Prueba processInbound simulado ---\n";
$fake = [
    'message_id' => 'wamid.DIAG_' . bin2hex(random_bytes(4)),
    'from' => $norm,
    'from_name' => 'Diagnóstico',
    'type' => 'text',
    'text' => 'Mensaje diagnóstico ' . date('H:i:s'),
];
$result = ticketsWhatsappProcessInbound($fake, null);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
