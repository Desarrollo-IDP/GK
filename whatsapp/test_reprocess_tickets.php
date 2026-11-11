<?php
require __DIR__ . '/../tickets/includes/whatsapp_ticket_bridge.php';
$r = ticketsWhatsappReprocessChatLog(2000);
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
