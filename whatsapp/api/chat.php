<?php
/**
 * API JSON del chat / pruebas WhatsApp
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__) . '/includes/message_store.php';
require_once dirname(__DIR__) . '/includes/meta_api.php';
require_once dirname(__DIR__) . '/includes/test_suite.php';
require_once dirname(__DIR__) . '/includes/webhook_handler.php';
require_once dirname(__DIR__, 2) . '/tickets/includes/whatsapp_ticket_bridge.php';

checkAuth();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'serve') {
    $file = basename((string) ($_GET['file'] ?? ''));
    if ($file === '' || preg_match('/[\\\\\\/]/', $file)) {
        http_response_code(400);
        exit('Archivo no válido');
    }
    $path = WHATSAPP_CHAT_MEDIA_DIR . '/' . $file;
    if (!is_file($path)) {
        http_response_code(404);
        exit('No encontrado');
    }
    $mime = mime_content_type($path) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($action) {
        case 'status':
            $sync = whatsappChatSyncIncoming(150);
            $cfg = whatsappConfig();
            echo json_encode([
                'ok' => true,
                'configured' => whatsappIsConfigured(),
                'phone_number_id' => $cfg['phone_number_id'] ?? null,
                'conversations' => count(whatsappChatListConversations()),
                'sync' => $sync,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'conversations':
            $sync = whatsappChatSyncIncoming(150);
            echo json_encode([
                'ok' => true,
                'conversations' => whatsappChatListConversations(),
                'sync' => $sync,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'messages':
            whatsappChatSyncIncoming(150);
            $phone = preg_replace('/\D+/', '', (string) ($_GET['phone'] ?? ''));
            if ($phone === '') {
                throw new RuntimeException('Teléfono requerido');
            }
            $messages = whatsappChatLoadMessages($phone, 300);
            echo json_encode(['ok' => true, 'phone' => $phone, 'messages' => $messages], JSON_UNESCAPED_UNICODE);
            break;

        case 'import':
            $result = whatsappChatSyncIncoming(200);
            echo json_encode(['ok' => true, 'imported' => $result['imported'], 'sync' => $result], JSON_UNESCAPED_UNICODE);
            break;

        case 'sync':
            $result = whatsappChatSyncIncoming((int) ($_GET['limit'] ?? 200));
            echo json_encode(['ok' => true, 'sync' => $result], JSON_UNESCAPED_UNICODE);
            break;

        case 'reprocess_tickets':
            $result = ticketsWhatsappReprocessChatLog((int) ($_GET['limit'] ?? 1000));
            echo json_encode(['ok' => true, 'reprocess' => $result], JSON_UNESCAPED_UNICODE);
            break;

        case 'recent_inbound':
            whatsappChatSyncIncoming(150);
            $limit = max(1, min(100, (int) ($_GET['limit'] ?? 25)));
            echo json_encode([
                'ok' => true,
                'messages' => array_reverse(whatsappChatRecentInbound($limit)),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'tests':
            $baseUrl = rtrim((string) ($_GET['base_url'] ?? 'http://localhost:90/GK'), '/');
            $liveSend = isset($_GET['live_send']) && $_GET['live_send'] === '1';
            $livePhone = $_GET['phone'] ?? null;
            $results = whatsappRunTestSuite($baseUrl, $liveSend, $livePhone);
            echo json_encode([
                'ok' => true,
                'summary' => whatsappTestSuiteSummary($results),
                'results' => $results,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'simulate':
            $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
            $type = (string) ($_POST['type'] ?? 'text');
            $body = trim((string) ($_POST['body'] ?? ''));
            $name = trim((string) ($_POST['contact_name'] ?? 'Contacto prueba'));

            if ($phone === '') {
                throw new RuntimeException('Teléfono requerido');
            }

            $msg = [
                'message_id' => 'wamid.SIM_' . bin2hex(random_bytes(6)),
                'from' => $phone,
                'from_name' => $name,
                'timestamp' => time(),
                'type' => $type,
                'text' => $body,
            ];

            if ($type === 'image') {
                $msg['media_id'] = 'sim_image_' . bin2hex(random_bytes(4));
                $msg['mime_type'] = 'image/jpeg';
                $msg['caption'] = $body;
            } elseif ($type === 'audio') {
                $msg['media_id'] = 'sim_audio_' . bin2hex(random_bytes(4));
                $msg['mime_type'] = 'audio/ogg';
            }

            $saved = whatsappChatSaveFromWebhook($msg);
            echo json_encode(['ok' => true, 'message' => $saved], JSON_UNESCAPED_UNICODE);
            break;

        case 'send':
            if (!whatsappIsConfigured()) {
                throw new RuntimeException('WhatsApp no está configurado. Revise whatsapp/.env (ACCESS_TOKEN y PHONE_NUMBER_ID).');
            }

            $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
            $type = (string) ($_POST['type'] ?? 'text');
            $body = trim((string) ($_POST['body'] ?? ''));

            if ($phone === '') {
                throw new RuntimeException('Teléfono destino requerido');
            }
            if (strlen($phone) < 10) {
                throw new RuntimeException('Teléfono inválido. Use formato internacional, ej: 5215512345678');
            }

            $result = null;
            $mediaPath = null;
            $mimeType = null;
            $uploadFilename = null;

            if ($type === 'text') {
                if ($body === '') {
                    throw new RuntimeException('Escriba un mensaje');
                }
                $result = whatsappSendTextMessage($phone, $body);
            } elseif ($type === 'image' || $type === 'audio' || $type === 'document') {
                if (empty($_FILES['file']['tmp_name'])) {
                    throw new RuntimeException('Archivo requerido');
                }
                $uploadFilename = basename((string) ($_FILES['file']['name'] ?? 'archivo'));
                $mimeType = mime_content_type($_FILES['file']['tmp_name']) ?: ($_FILES['file']['type'] ?? 'application/octet-stream');
                $upload = whatsappUploadMedia($_FILES['file']['tmp_name'], $mimeType);
                if (!$upload['ok'] || empty($upload['media_id'])) {
                    throw new RuntimeException($upload['error'] ?? 'Error al subir archivo a Meta');
                }
                if ($type === 'image') {
                    $result = whatsappSendImageMessage($phone, $upload['media_id'], $body);
                } elseif ($type === 'audio') {
                    $result = whatsappSendAudioMessage($phone, $upload['media_id']);
                } else {
                    $docBody = $body !== '' ? $body : $uploadFilename;
                    $result = whatsappSendDocumentMessage($phone, $upload['media_id'], $uploadFilename, $docBody);
                    $body = $docBody;
                }

                $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $uploadFilename) ?: 'archivo';
                $localCopy = WHATSAPP_CHAT_MEDIA_DIR . '/' . uniqid('out_', true) . '_' . $safeName;
                if (@copy($_FILES['file']['tmp_name'], $localCopy)) {
                    $mediaPath = 'storage/chat/media/' . basename($localCopy);
                }
            } else {
                throw new RuntimeException('Tipo no soportado');
            }

            if (!$result['ok']) {
                $metaErr = $result['error'] ?? '';
                if (isset($result['response']['error']['message'])) {
                    $metaErr = (string) $result['response']['error']['message'];
                }
                throw new RuntimeException($metaErr !== '' ? $metaErr : 'Meta API rechazó el envío');
            }

            $waMessageId = (string) ($result['response']['messages'][0]['id'] ?? '');
            $displayBody = $type === 'text' ? $body : ($body !== '' ? $body : '[' . ucfirst($type) . ']');

            $saved = whatsappChatSaveMessage([
                'direction' => 'outbound',
                'phone' => $phone,
                'type' => $type,
                'body' => $displayBody,
                'media_path' => $mediaPath,
                'mime_type' => $mimeType,
                'message_id' => $waMessageId ?: null,
                'status' => 'sent',
            ]);

            $ticketLinked = null;
            $conn = ticketsWhatsappDb();
            if ($conn) {
                ticketsWhatsappEnsureSchema($conn);
                $empresaId = ticketsWhatsappDefaultEmpresaId();
                $requester = ticketsWhatsappFindRequesterByPhone($conn, $phone, $empresaId);
                if ($requester !== null) {
                    $open = ticketsWhatsappFindOpenTicket($conn, $requester['id'], $empresaId);
                    if ($open !== null) {
                        $ticketMsg = ticketsWhatsappInsertMessage(
                            $conn,
                            $open['id'],
                            $requester['id'],
                            'WhatsApp Lab',
                            'lab@whatsapp.local',
                            $displayBody,
                            $waMessageId,
                            $type,
                            'technician',
                            0
                        );
                        if ($mediaPath !== null) {
                            ticketsWhatsappSaveAttachmentForMessage($conn, $open['id'], $ticketMsg['ticket_message_id'], [
                                'type' => $type,
                                'media_path' => $mediaPath,
                                'body' => $displayBody,
                            ]);
                        }
                        $ticketLinked = [
                            'ticket_id' => $open['id'],
                            'ticket_number' => $open['ticket_number'] ?? $open['id'],
                            'ticket_message_id' => $ticketMsg['ticket_message_id'],
                        ];
                    }
                }
            }

            echo json_encode([
                'ok' => true,
                'message' => $saved,
                'meta' => $result['response'],
                'ticket' => $ticketLinked,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'ticket_for_phone':
            $phone = preg_replace('/\D+/', '', (string) ($_GET['phone'] ?? ''));
            if ($phone === '') {
                throw new RuntimeException('Teléfono requerido');
            }
            $conn = ticketsWhatsappDb();
            $ticket = null;
            if ($conn) {
                $empresaId = ticketsWhatsappDefaultEmpresaId();
                $requester = ticketsWhatsappFindRequesterByPhone($conn, $phone, $empresaId);
                if ($requester !== null) {
                    $open = ticketsWhatsappFindOpenTicket($conn, $requester['id'], $empresaId);
                    if ($open !== null) {
                        $ticket = $open;
                    }
                }
            }
            echo json_encode(['ok' => true, 'ticket' => $ticket], JSON_UNESCAPED_UNICODE);
            break;

        case 'media':
            $mediaId = trim((string) ($_GET['media_id'] ?? ''));
            if ($mediaId === '') {
                throw new RuntimeException('media_id requerido');
            }
            if (str_starts_with($mediaId, 'sim_') || str_starts_with($mediaId, 'media_suite_')) {
                throw new RuntimeException('Media simulado — sin archivo en Meta');
            }
            $dl = whatsappDownloadMediaToChat($mediaId);
            if (!$dl['ok']) {
                throw new RuntimeException($dl['error'] ?? 'No se pudo descargar');
            }
            echo json_encode(['ok' => true, 'path' => $dl['path'], 'mime_type' => $dl['mime_type']], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
