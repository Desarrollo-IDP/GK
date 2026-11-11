<?php
/**
 * Almacén local de mensajes del chat de prueba WhatsApp
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

define('WHATSAPP_CHAT_DIR', WHATSAPP_STORAGE . '/chat');
define('WHATSAPP_CHAT_MEDIA_DIR', WHATSAPP_CHAT_DIR . '/media');
define('WHATSAPP_MESSAGES_FILE', WHATSAPP_CHAT_DIR . '/messages.jsonl');
define('WHATSAPP_IMPORTED_INDEX', WHATSAPP_CHAT_DIR . '/imported_message_ids.json');

function whatsappChatNormalizePhone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone);
}

function whatsappChatEnsureStorage(): void
{
    whatsappEnsureStorage();
    foreach ([WHATSAPP_CHAT_DIR, WHATSAPP_CHAT_MEDIA_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    if (!is_file(WHATSAPP_MESSAGES_FILE)) {
        touch(WHATSAPP_MESSAGES_FILE);
    }
}

/**
 * Índice de message_id ya guardados (evita duplicados)
 *
 * @return array<string, true>
 */
function whatsappChatGetImportedIds(): array
{
    whatsappChatEnsureStorage();
    global $whatsappChatIdsRuntime;
    static $cache = null;
    if ($cache !== null) {
        return array_merge($cache, is_array($whatsappChatIdsRuntime) ? $whatsappChatIdsRuntime : []);
    }

    $cache = [];
    if (is_readable(WHATSAPP_IMPORTED_INDEX)) {
        $data = json_decode((string) file_get_contents(WHATSAPP_IMPORTED_INDEX), true);
        if (is_array($data)) {
            foreach ($data as $id) {
                if (is_string($id) && $id !== '') {
                    $cache[$id] = true;
                }
            }
        }
    }

    if (is_readable(WHATSAPP_MESSAGES_FILE)) {
        $lines = file(WHATSAPP_MESSAGES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (!empty($row['message_id'])) {
                $cache[(string) $row['message_id']] = true;
            }
        }
    }

    return array_merge($cache, is_array($whatsappChatIdsRuntime) ? $whatsappChatIdsRuntime : []);
}

function whatsappChatRememberMessageId(?string $messageId): void
{
    if ($messageId === null || $messageId === '') {
        return;
    }

    global $whatsappChatIdsRuntime;
    if (!is_array($whatsappChatIdsRuntime)) {
        $whatsappChatIdsRuntime = [];
    }
    $whatsappChatIdsRuntime[$messageId] = true;

    $ids = whatsappChatGetImportedIds();
    if (isset($ids[$messageId])) {
        return;
    }
    $ids[$messageId] = true;
    $list = array_keys($ids);
    if (count($list) > 5000) {
        $list = array_slice($list, -5000);
    }
    file_put_contents(WHATSAPP_IMPORTED_INDEX, json_encode(array_values($list), JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function whatsappChatMessageExists(?string $messageId): bool
{
    if ($messageId === null || $messageId === '') {
        return false;
    }
    $ids = whatsappChatGetImportedIds();
    return isset($ids[$messageId]);
}

/**
 * @param array<string,mixed> $message
 * @return array<string,mixed>|null
 */
function whatsappChatSaveMessage(array $message): ?array
{
    whatsappChatEnsureStorage();

    if (!empty($message['message_id']) && whatsappChatMessageExists((string) $message['message_id'])) {
        return null;
    }

    $record = array_merge([
        'id' => bin2hex(random_bytes(16)),
        'direction' => 'inbound',
        'phone' => '',
        'contact_name' => '',
        'type' => 'text',
        'body' => '',
        'media_id' => null,
        'media_path' => null,
        'mime_type' => null,
        'message_id' => null,
        'status' => 'received',
        'created_at' => date('Y-m-d H:i:s'),
    ], $message);

    file_put_contents(
        WHATSAPP_MESSAGES_FILE,
        json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    whatsappChatRememberMessageId($record['message_id'] ?? null);

    return $record;
}

/**
 * @return list<array<string,mixed>>
 */
function whatsappChatLoadMessages(?string $phone = null, int $limit = 200): array
{
    whatsappChatEnsureStorage();
    if (!is_readable(WHATSAPP_MESSAGES_FILE)) {
        return [];
    }

    $lines = file(WHATSAPP_MESSAGES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $messages = [];

    foreach ($lines as $line) {
        $row = json_decode($line, true);
        if (!is_array($row)) {
            continue;
        }
        if ($phone !== null) {
            if (whatsappChatNormalizePhone((string) ($row['phone'] ?? '')) !== whatsappChatNormalizePhone($phone)) {
                continue;
            }
        }
        $messages[] = whatsappChatEnrichMessage($row);
    }

    if ($phone === null) {
        return array_slice($messages, -$limit);
    }

    return array_slice($messages, -$limit);
}

/**
 * Intenta descargar media de Meta si falta archivo local
 *
 * @param array<string,mixed> $msg
 * @return array<string,mixed>
 */
function whatsappChatEnrichMessage(array $msg): array
{
    if (!empty($msg['media_path']) && is_file(dirname(__DIR__) . '/' . $msg['media_path'])) {
        $msg['media_url'] = whatsappChatMediaPublicUrl((string) $msg['media_path']);
        return $msg;
    }

    $mediaId = $msg['media_id'] ?? null;
    if (!$mediaId || !is_string($mediaId)) {
        return $msg;
    }
    if (str_starts_with($mediaId, 'sim_') || str_starts_with($mediaId, 'media_suite_')) {
        return $msg;
    }

    require_once __DIR__ . '/meta_api.php';
    $dl = whatsappDownloadMediaToChat($mediaId);
    if ($dl['ok'] && !empty($dl['path'])) {
        $msg['media_path'] = $dl['path'];
        $msg['media_url'] = whatsappChatMediaPublicUrl($dl['path']);
    }

    return $msg;
}

function whatsappChatMediaPublicUrl(string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    return 'api/chat.php?action=serve&file=' . rawurlencode(basename($relativePath));
}

/**
 * @return list<array{phone:string,contact_name:string,last_message:string,last_at:string,unread:int}>
 */
function whatsappChatListConversations(): array
{
    $messages = whatsappChatLoadMessages(null, 5000);
    $conversations = [];

    foreach ($messages as $msg) {
        $phone = whatsappChatNormalizePhone((string) ($msg['phone'] ?? ''));
        if ($phone === '' || $phone === '0000000000') {
            continue;
        }
        if (($msg['type'] ?? '') === 'system') {
            continue;
        }
        if (!isset($conversations[$phone])) {
            $conversations[$phone] = [
                'phone' => $phone,
                'contact_name' => (string) ($msg['contact_name'] ?? ''),
                'last_message' => '',
                'last_at' => '',
                'last_type' => 'text',
                'count' => 0,
            ];
        }
        if (!empty($msg['contact_name'])) {
            $conversations[$phone]['contact_name'] = $msg['contact_name'];
        }
        $conversations[$phone]['count']++;
        $conversations[$phone]['last_at'] = $msg['created_at'] ?? '';
        $conversations[$phone]['last_type'] = $msg['type'] ?? 'text';
        $conversations[$phone]['last_message'] = whatsappChatPreviewText($msg);
    }

    $list = array_values($conversations);
    usort($list, fn($a, $b) => strcmp($b['last_at'], $a['last_at']));
    return $list;
}

/**
 * @param array<string,mixed> $msg
 */
function whatsappChatPreviewText(array $msg): string
{
    $type = $msg['type'] ?? 'text';
    if ($type === 'text') {
        return (string) ($msg['body'] ?? '');
    }
    if ($type === 'image') {
        return '[Imagen] ' . (string) ($msg['body'] ?? '');
    }
    if ($type === 'audio') {
        return '[Audio]';
    }
    if ($type === 'document') {
        return '[Documento] ' . (string) ($msg['body'] ?? '');
    }
    return '[' . ucfirst($type) . ']';
}

/**
 * Guarda mensaje normalizado del webhook en el chat
 *
 * @param array<string,mixed> $message
 */
function whatsappChatSaveFromWebhook(array $message, bool $downloadMedia = true): ?array
{
    $messageId = (string) ($message['message_id'] ?? '');
    if ($messageId !== '' && whatsappChatMessageExists($messageId)) {
        return null;
    }

    $body = '';
    if (($message['type'] ?? '') === 'text') {
        $body = (string) ($message['text'] ?? '');
    } elseif (!empty($message['caption'])) {
        $body = (string) $message['caption'];
    }

    $mediaPath = null;
    $mediaId = $message['media_id'] ?? null;
    if ($downloadMedia && is_string($mediaId) && $mediaId !== ''
        && !str_starts_with($mediaId, 'sim_')
        && !str_starts_with($mediaId, 'media_suite_')
    ) {
        require_once __DIR__ . '/meta_api.php';
        $dl = whatsappDownloadMediaToChat($mediaId);
        if ($dl['ok'] && !empty($dl['path'])) {
            $mediaPath = $dl['path'];
        }
    }

    return whatsappChatSaveMessage([
        'direction' => 'inbound',
        'phone' => whatsappChatNormalizePhone((string) ($message['from'] ?? '')),
        'contact_name' => (string) ($message['from_name'] ?? ''),
        'type' => (string) ($message['type'] ?? 'unknown'),
        'body' => $body,
        'media_id' => $mediaId,
        'media_path' => $mediaPath,
        'mime_type' => $message['mime_type'] ?? null,
        'message_id' => $messageId ?: null,
        'status' => 'received',
    ]);
}

/**
 * Sincroniza mensajes desde payloads JSON del webhook (sin duplicar)
 *
 * @return array{imported:int, skipped:int, scanned_files:int, tickets_created:int, tickets_linked:int, ticket_errors:int}
 */
function whatsappChatSyncIncoming(int $maxFiles = 200): array
{
    require_once __DIR__ . '/webhook_handler.php';
    require_once dirname(__DIR__, 2) . '/tickets/includes/whatsapp_ticket_bridge.php';

    $files = glob(WHATSAPP_INCOMING_DIR . '/*.json') ?: [];
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $files = array_slice($files, 0, $maxFiles);

    $imported = 0;
    $skipped = 0;
    $ticketsCreated = 0;
    $ticketsLinked = 0;
    $ticketErrors = 0;

    foreach ($files as $file) {
        $payload = json_decode((string) file_get_contents($file), true);
        if (!is_array($payload)) {
            continue;
        }
        foreach (whatsappParseIncomingMessages($payload) as $msg) {
            $saved = whatsappChatSaveFromWebhook($msg, true);
            if ($saved !== null) {
                $imported++;
            } else {
                $skipped++;
            }

            $ticketResult = ticketsWhatsappProcessInbound($msg, $saved);
            if (($ticketResult['ok'] ?? false) && !empty($ticketResult['created_ticket'])) {
                $ticketsCreated++;
            } elseif (($ticketResult['ok'] ?? false) && empty($ticketResult['skipped'])) {
                $ticketsLinked++;
            } elseif (!($ticketResult['ok'] ?? false) && empty($ticketResult['skipped'])) {
                $ticketErrors++;
            }
        }
    }

    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'scanned_files' => count($files),
        'tickets_created' => $ticketsCreated,
        'tickets_linked' => $ticketsLinked,
        'ticket_errors' => $ticketErrors,
    ];
}

/**
 * Últimos mensajes recibidos (todos los contactos)
 *
 * @return list<array<string,mixed>>
 */
function whatsappChatRecentInbound(int $limit = 30): array
{
    $all = whatsappChatLoadMessages(null, 5000);
    $inbound = array_values(array_filter($all, fn($m) => ($m['direction'] ?? '') === 'inbound'));
    return array_slice($inbound, -$limit);
}

/**
 * Importa mensajes desde payloads JSON guardados por el webhook
 * @deprecated Use whatsappChatSyncIncoming()
 */
function whatsappChatImportFromIncoming(int $maxFiles = 50): int
{
    $result = whatsappChatSyncIncoming($maxFiles);
    return $result['imported'];
}
