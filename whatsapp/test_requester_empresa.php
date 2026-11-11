<?php
require __DIR__ . '/../tickets/includes/whatsapp_ticket_bridge.php';

$conn = ticketsWhatsappDb();
$empresa = ticketsWhatsappDefaultEmpresaId();
echo "WHATSAPP_DEFAULT_EMPRESA_ID = " . var_export($empresa, true) . PHP_EOL;

$testPhone = '5215599887766';
$r = ticketsWhatsappFindOrCreateRequester($conn, $testPhone, 'Prueba Empresa WA', $empresa);
echo "Requester: " . json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;

$chk = $conn->prepare('SELECT id, name, phone, id_empresa, email FROM requesters WHERE id = ?');
$chk->bind_param('i', $r['id']);
$chk->execute();
echo "DB row: " . json_encode($chk->get_result()->fetch_assoc(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
