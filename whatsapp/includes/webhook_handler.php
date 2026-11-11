<?php
/**
 * Manejo del webhook de Meta WhatsApp Cloud API
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/message_store.php';
require_once dirname(__DIR__, 2) . '/tickets/includes/whatsapp_ticket_bridge.php';

/**
 * Verificación GET del webhook (Meta Developers)
 */
function whatsappHandleWebhookVerification(): bool
{
    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    $expected = whatsappConfig()['verify_token'] ?? '';

    whatsappLogInfo('Intento de verificación webhook', [
        'mode' => $mode,
        'token_match' => hash_equals((string) $expected, (string) $token),
    ]);

    if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, (string) $token)) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(200);
        echo $challenge;
        return true;
    }

    whatsappLogError('Verificación webhook rechazada', [
        'mode' => $mode,
        'has_verify_token' => $expected !== '',
    ]);

    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    return true;
}

/**
 * Valida firma X-Hub-Signature-256 si WHATSAPP_APP_SECRET está configurado
 */
function whatsappValidateSignature(string $rawBody): bool
{
    $secret = whatsappConfig()['app_secret'] ?? '';
    if ($secret === null || $secret === '') {
        return true;
    }

    $header = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    if ($header === '' || !str_starts_with($header, 'sha256=')) {
        whatsappLogError('Firma webhook ausente o inválida');
        return false;
    }

    $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
    if (!hash_equals($expected, $header)) {
        whatsappLogError('Firma webhook no coincide');
        return false;
    }

    return true;
}

/**
 * Extrae mensajes normalizados del payload de Meta
 *
 * @return list<array<string,mixed>>
 */
function whatsappParseIncomingMessages(array $payload): array
{
    $messages = [];

    foreach ($payload['entry'] ?? [] as $entry) {
        foreach ($entry['changes'] ?? [] as $change) {
            if (($change['field'] ?? '') !== 'messages') {
                continue;
            }

            $value = $change['value'] ?? [];
            $metadata = $value['metadata'] ?? [];
            $contacts = $value['contacts'] ?? [];

            foreach ($value['messages'] ?? [] as $msg) {
                $from = $msg['from'] ?? '';
                $contactName = '';
                foreach ($contacts as $contact) {
                    if (($contact['wa_id'] ?? '') === $from) {
                        $contactName = $contact['profile']['name'] ?? '';
                        break;
                    }
                }

                $normalized = [
                    'message_id' => $msg['id'] ?? '',
                    'from' => $from,
                    'from_name' => $contactName,
                    'timestamp' => isset($msg['timestamp']) ? (int) $msg['timestamp'] : null,
                    'type' => $msg['type'] ?? 'unknown',
                    'phone_number_id' => $metadata['phone_number_id'] ?? '',
                    'display_phone_number' => $metadata['display_phone_number'] ?? '',
                    'raw' => $msg,
                ];

                switch ($normalized['type']) {
                    case 'text':
                        $normalized['text'] = $msg['text']['body'] ?? '';
                        break;
                    case 'image':
                        $normalized['media_id'] = $msg['image']['id'] ?? '';
                        $normalized['mime_type'] = $msg['image']['mime_type'] ?? '';
                        $normalized['caption'] = $msg['image']['caption'] ?? '';
                        break;
                    case 'audio':
                        $normalized['media_id'] = $msg['audio']['id'] ?? '';
                        $normalized['mime_type'] = $msg['audio']['mime_type'] ?? '';
                        break;
                    case 'document':
                        $normalized['media_id'] = $msg['document']['id'] ?? '';
                        $normalized['mime_type'] = $msg['document']['mime_type'] ?? '';
                        $normalized['filename'] = $msg['document']['filename'] ?? '';
                        $normalized['caption'] = $msg['document']['caption'] ?? '';
                        break;
                    case 'video':
                        $normalized['media_id'] = $msg['video']['id'] ?? '';
                        $normalized['mime_type'] = $msg['video']['mime_type'] ?? '';
                        $normalized['caption'] = $msg['video']['caption'] ?? '';
                        break;
                    case 'sticker':
                        $normalized['media_id'] = $msg['sticker']['id'] ?? '';
                        break;
                    default:
                        break;
                }

                $messages[] = $normalized;
            }

            foreach ($value['statuses'] ?? [] as $status) {
                whatsappLogInfo('Estado de mensaje WhatsApp', [
                    'message_id' => $status['id'] ?? '',
                    'status' => $status['status'] ?? '',
                    'recipient' => $status['recipient_id'] ?? '',
                ]);
            }
        }
    }

    return $messages;
}

/**
 * Procesa POST del webhook
 */
function whatsappHandleWebhookPost(): void
{
    $rawBody = file_get_contents('php://input') ?: '';

    if ($rawBody === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Empty body']);
        return;
    }

    if (!whatsappValidateSignature($rawBody)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid signature']);
        return;
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        return;
    }

    whatsappSaveIncomingPayload($payload);

    $object = $payload['object'] ?? '';
    if ($object !== 'whatsapp_business_account') {
        whatsappLogInfo('Webhook ignorado (objeto distinto)', ['object' => $object]);
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'ignored' => true]);
        return;
    }

    $messages = whatsappParseIncomingMessages($payload);

    if ($messages === []) {
        $fields = [];
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $fields[] = (string) ($change['field'] ?? '');
            }
        }
        whatsappLogInfo('Webhook recibido sin mensajes de usuario', [
            'fields' => array_values(array_unique($fields)),
            'hint' => 'Suscríbete al campo "messages" en Meta Developers',
        ]);
    }

    foreach ($messages as $message) {
        whatsappLogInfo('Mensaje entrante WhatsApp', [
            'message_id' => $message['message_id'],
            'from' => $message['from'],
            'from_name' => $message['from_name'] ?? '',
            'type' => $message['type'],
            'text' => $message['text'] ?? null,
        ]);

        $saved = whatsappChatSaveFromWebhook($message, true);
        if ($saved !== null) {
            whatsappLogInfo('Mensaje guardado en chat', [
                'message_id' => $message['message_id'],
                'from' => $message['from'],
                'type' => $message['type'],
            ]);
        }

        $ticketResult = ticketsWhatsappProcessInbound($message, $saved);
        if (!($ticketResult['ok'] ?? false) && empty($ticketResult['skipped'])) {
            whatsappLogError('No se pudo crear/vincular ticket', [
                'message_id' => $message['message_id'],
                'from' => $message['from'],
                'error' => $ticketResult['error'] ?? 'desconocido',
            ]);
        } elseif (!empty($ticketResult['created_ticket'])) {
            whatsappLogInfo('Ticket creado desde WhatsApp', [
                'ticket_id' => $ticketResult['ticket_id'],
                'ticket_number' => $ticketResult['ticket_number'] ?? null,
                'from' => $message['from'],
            ]);
        }
    }

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'messages_received' => count($messages),
    ]);
}
