<?php
/**
 * Puente WhatsApp ↔ módulo de tickets
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/whatsapp/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/whatsapp/includes/config.php';
require_once dirname(__DIR__, 2) . '/whatsapp/includes/message_store.php';
require_once dirname(__DIR__, 2) . '/whatsapp/includes/meta_api.php';
require_once __DIR__ . '/ticket_counter_helper.php';
require_once __DIR__ . '/ticket_history.php';

/**
 * @return mysqli|null
 */
function ticketsWhatsappDb(): ?mysqli
{
    static $cached = null;
    if ($cached instanceof mysqli) {
        return $cached;
    }

    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->set_charset('utf8mb4');
        $cached = $conn;
        return $cached;
    }

    $dbFile = dirname(__DIR__) . '/conexion/db.php';
    if (!is_file($dbFile)) {
        return null;
    }
    require_once $dbFile;

    if (!isset($conn) || !($conn instanceof mysqli)) {
        return null;
    }
    $conn->set_charset('utf8mb4');
    $cached = $conn;
    return $cached;
}

function ticketsWhatsappDefaultEmpresaId(): ?int
{
    whatsappLoadEnv();
    $raw = whatsappEnv('WHATSAPP_DEFAULT_EMPRESA_ID');
    if ($raw === null || $raw === '') {
        return null;
    }
    $id = (int) $raw;
    return $id > 0 ? $id : null;
}

/**
 * Asigna id_empresa al solicitante si aún no tiene (p. ej. creado antes de configurar .env).
 */
function ticketsWhatsappEnsureRequesterEmpresa(mysqli $conn, int $requesterId, ?int $empresaId): void
{
    if ($empresaId === null || $empresaId <= 0) {
        return;
    }
    if (!ticketsWhatsappColumnExists($conn, 'requesters', 'id_empresa')) {
        return;
    }
    $stmt = $conn->prepare(
        'UPDATE requesters SET id_empresa = ? WHERE id = ? AND (id_empresa IS NULL OR id_empresa = 0)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ii', $empresaId, $requesterId);
    $stmt->execute();
    $stmt->close();
}

function ticketsWhatsappColumnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ((int) ($row['c'] ?? 0)) > 0;
}

function ticketsWhatsappEnsureSchema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (!ticketsWhatsappColumnExists($conn, 'ticket_messages', 'is_whatsapp')) {
        @$conn->query(
            'ALTER TABLE ticket_messages
             ADD COLUMN is_whatsapp TINYINT(1) NOT NULL DEFAULT 0 AFTER is_email'
        );
    }
    if (!ticketsWhatsappColumnExists($conn, 'ticket_messages', 'whatsapp_type')) {
        @$conn->query(
            'ALTER TABLE ticket_messages
             ADD COLUMN whatsapp_type VARCHAR(32) DEFAULT NULL AFTER is_whatsapp'
        );
    }
    if (!ticketsWhatsappColumnExists($conn, 'tickets', 'whatsapp_enabled')) {
        @$conn->query(
            'ALTER TABLE tickets
             ADD COLUMN whatsapp_enabled TINYINT(1) NOT NULL DEFAULT 0
             COMMENT \'1 = permite conversación/envío WhatsApp en la vista\''
        );
    }
    if (!ticketsWhatsappColumnExists($conn, 'tickets', 'last_wa_message_id')) {
        @$conn->query(
            'ALTER TABLE tickets
             ADD COLUMN last_wa_message_id VARCHAR(255) DEFAULT NULL'
        );
    }

    $res = $conn->query("SELECT id FROM contact_modes WHERE LOWER(name) LIKE '%whatsapp%' LIMIT 1");
    if ($res && $res->num_rows === 0) {
        @$conn->query("INSERT INTO contact_modes (name) VALUES ('WhatsApp')");
    }
}

/**
 * Activa el canal WhatsApp en un ticket (si la columna existe).
 */
function ticketsWhatsappEnableOnTicket(mysqli $conn, int $ticketId): void
{
    if ($ticketId <= 0 || !ticketsWhatsappColumnExists($conn, 'tickets', 'whatsapp_enabled')) {
        return;
    }
    $stmt = $conn->prepare('UPDATE tickets SET whatsapp_enabled = 1, updated_at = NOW() WHERE id = ? AND whatsapp_enabled = 0');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $stmt->close();
}

function ticketsWhatsappPhonesMatch(string $a, string $b): bool
{
    $na = whatsappChatNormalizePhone($a);
    $nb = whatsappChatNormalizePhone($b);
    if ($na === '' || $nb === '') {
        return false;
    }
    if ($na === $nb) {
        return true;
    }
    $la = strlen($na) > 10 ? substr($na, -10) : $na;
    $lb = strlen($nb) > 10 ? substr($nb, -10) : $nb;
    return $la === $lb;
}

/**
 * @return array{id:int,name:string,email:string,phone:string,id_empresa:?int}|null
 */
function ticketsWhatsappFindRequesterByPhone(mysqli $conn, string $phone, ?int $empresaId = null): ?array
{
    $stmt = $conn->prepare(
        'SELECT id, name, email, phone, id_empresa FROM requesters WHERE phone IS NOT NULL AND phone != ""'
    );
    if (!$stmt || !$stmt->execute()) {
        return null;
    }

    $exactEmpresa = null;
    $withoutEmpresa = null;
    $fallback = null;

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!ticketsWhatsappPhonesMatch($phone, (string) $row['phone'])) {
            continue;
        }

        $rowEmpresa = $row['id_empresa'] !== null && (int) $row['id_empresa'] > 0
            ? (int) $row['id_empresa']
            : null;

        if ($empresaId !== null) {
            if ($rowEmpresa === $empresaId) {
                $exactEmpresa = $row;
                break;
            }
            if ($rowEmpresa === null) {
                $withoutEmpresa = $row;
                continue;
            }
            // Mismo teléfono pero otra empresa: no usar
            continue;
        }

        $fallback = $row;
        break;
    }
    $stmt->close();

    $chosen = $exactEmpresa ?? $withoutEmpresa ?? $fallback;
    if ($chosen === null) {
        return null;
    }

    $requesterId = (int) $chosen['id'];
    if ($empresaId !== null) {
        ticketsWhatsappEnsureRequesterEmpresa($conn, $requesterId, $empresaId);
    }

    $chosenEmpresa = $chosen['id_empresa'] !== null && (int) $chosen['id_empresa'] > 0
        ? (int) $chosen['id_empresa']
        : $empresaId;

    return [
        'id' => $requesterId,
        'name' => (string) $chosen['name'],
        'email' => (string) $chosen['email'],
        'phone' => (string) $chosen['phone'],
        'id_empresa' => $chosenEmpresa,
    ];
}

function ticketsWhatsappPlaceholderEmail(string $phone): string
{
    return 'wa_' . whatsappChatNormalizePhone($phone) . '@whatsapp.local';
}

/**
 * @return array{id:int,name:string,email:string,phone:string,id_empresa:?int}
 */
function ticketsWhatsappFindOrCreateRequester(mysqli $conn, string $phone, string $name, ?int $empresaId = null): array
{
    $existing = ticketsWhatsappFindRequesterByPhone($conn, $phone, $empresaId);
    if ($existing !== null) {
        if ($empresaId !== null) {
            ticketsWhatsappEnsureRequesterEmpresa($conn, $existing['id'], $empresaId);
            $existing['id_empresa'] = $empresaId;
        }
        return $existing;
    }

    $normPhone = whatsappChatNormalizePhone($phone);
    $displayName = $name !== '' ? $name : ('WhatsApp +' . $normPhone);
    $email = ticketsWhatsappPlaceholderEmail($normPhone);

    if ($empresaId !== null) {
        $dup = $conn->prepare('SELECT id FROM requesters WHERE email = ? AND id_empresa = ? LIMIT 1');
        if ($dup) {
            $dup->bind_param('si', $email, $empresaId);
            $dup->execute();
            $dupRow = $dup->get_result()->fetch_assoc();
            $dup->close();
            if ($dupRow) {
                $foundId = (int) $dupRow['id'];
                $phoneUpd = $conn->prepare('UPDATE requesters SET phone = ? WHERE id = ?');
                if ($phoneUpd) {
                    $phoneUpd->bind_param('si', $normPhone, $foundId);
                    $phoneUpd->execute();
                    $phoneUpd->close();
                }
                return ticketsWhatsappFindRequesterByPhone($conn, $phone, $empresaId) ?? [
                    'id' => $foundId,
                    'name' => $displayName,
                    'email' => $email,
                    'phone' => $normPhone,
                    'id_empresa' => $empresaId,
                ];
            }
        }
    }

    $empresaForCounter = $empresaId ?? 0;
    $requesterNumber = getNextRequesterNumber($conn, $empresaForCounter);

    if ($empresaId !== null) {
        $stmt = $conn->prepare(
            'INSERT INTO requesters (name, email, phone, id_empresa, requester_number) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssis', $displayName, $email, $normPhone, $empresaId, $requesterNumber);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO requesters (name, email, phone, requester_number) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('ssss', $displayName, $email, $normPhone, $requesterNumber);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('No se pudo crear el solicitante: ' . $stmt->error);
    }
    $id = (int) $stmt->insert_id;
    $stmt->close();

    return [
        'id' => $id,
        'name' => $displayName,
        'email' => $email,
        'phone' => $normPhone,
        'id_empresa' => $empresaId,
    ];
}

function ticketsWhatsappResolveContactModeId(mysqli $conn): ?int
{
    $envId = whatsappEnv('WHATSAPP_CONTACT_MODE_ID');
    if ($envId !== null && $envId !== '') {
        return (int) $envId;
    }
    $res = $conn->query("SELECT id FROM contact_modes WHERE LOWER(name) LIKE '%whatsapp%' ORDER BY id ASC LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        return (int) $row['id'];
    }
    return null;
}

function ticketsWhatsappEnsureTicketEmpresa(mysqli $conn, int $ticketId, ?int $empresaId): void
{
    if ($empresaId === null || $empresaId <= 0) {
        return;
    }
    if (!ticketsWhatsappColumnExists($conn, 'tickets', 'id_empresa')) {
        return;
    }
    $stmt = $conn->prepare(
        'UPDATE tickets SET id_empresa = ? WHERE id = ? AND (id_empresa IS NULL OR id_empresa = 0)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ii', $empresaId, $ticketId);
    $stmt->execute();
    $stmt->close();
}

/**
 * @return array{id:int,ticket_number:?string}|null
 */
function ticketsWhatsappFindOpenTicket(mysqli $conn, int $requesterId, ?int $empresaId = null): ?array
{
    $sql = 'SELECT id, ticket_number, id_empresa FROM tickets
            WHERE requester_id = ?
              AND (closed_at IS NULL OR closed_at = "0000-00-00 00:00:00")
              AND (status_id IS NULL OR status_id != 3)
              AND (deleted = 0 OR deleted IS NULL)';
    $types = 'i';
    $params = [$requesterId];
    if ($empresaId !== null) {
        $sql .= ' AND (id_empresa = ? OR id_empresa IS NULL OR id_empresa = 0)';
        $types .= 'i';
        $params[] = $empresaId;
    }
    $sql .= ' ORDER BY id DESC LIMIT 1';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $ticketId = (int) $row['id'];
    if ($empresaId !== null) {
        ticketsWhatsappEnsureTicketEmpresa($conn, $ticketId, $empresaId);
    }
    return ['id' => $ticketId, 'ticket_number' => $row['ticket_number'] ?? null];
}

function ticketsWhatsappBuildMessageBody(array $message): string
{
    $type = (string) ($message['type'] ?? 'text');
    if ($type === 'text') {
        return trim((string) ($message['text'] ?? ''));
    }
    $caption = trim((string) ($message['caption'] ?? $message['text'] ?? ''));
    $labels = [
        'image' => '[Imagen WhatsApp]',
        'audio' => '[Audio WhatsApp]',
        'video' => '[Video WhatsApp]',
        'document' => '[Documento WhatsApp]',
        'sticker' => '[Sticker WhatsApp]',
    ];
    $label = $labels[$type] ?? '[Mensaje WhatsApp]';
    return $caption !== '' ? $label . ' ' . $caption : $label;
}

/**
 * @return array{id:int,ticket_number:string,created:bool}
 */
function ticketsWhatsappCreateTicket(
    mysqli $conn,
    int $requesterId,
    string $description,
    string $waMessageId,
    ?int $empresaId = null
): array {
    $empresaForCounter = $empresaId ?? 0;
    $ticketNumber = getNextTicketNumber($conn, $empresaForCounter);
    $subject = 'WhatsApp: ' . mb_substr($description, 0, 120);
    $contactModeId = ticketsWhatsappResolveContactModeId($conn);
    $statusId = 1;

    if ($empresaId !== null && $contactModeId !== null) {
        $stmt = $conn->prepare(
            'INSERT INTO tickets (subject, description, status_id, requester_id, contact_mode_id, id_empresa, ticket_number, last_wa_message_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->bind_param('ssiiiiss', $subject, $description, $statusId, $requesterId, $contactModeId, $empresaId, $ticketNumber, $waMessageId);
    } elseif ($empresaId !== null) {
        $stmt = $conn->prepare(
            'INSERT INTO tickets (subject, description, status_id, requester_id, id_empresa, ticket_number, last_wa_message_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->bind_param('ssiiiss', $subject, $description, $statusId, $requesterId, $empresaId, $ticketNumber, $waMessageId);
    } elseif ($contactModeId !== null) {
        $stmt = $conn->prepare(
            'INSERT INTO tickets (subject, description, status_id, requester_id, contact_mode_id, ticket_number, last_wa_message_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->bind_param('ssiiiss', $subject, $description, $statusId, $requesterId, $contactModeId, $ticketNumber, $waMessageId);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO tickets (subject, description, status_id, requester_id, ticket_number, last_wa_message_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->bind_param('ssiiss', $subject, $description, $statusId, $requesterId, $ticketNumber, $waMessageId);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('No se pudo crear el ticket: ' . $stmt->error);
    }
    $ticketId = (int) $stmt->insert_id;
    $stmt->close();

    ticketsWhatsappEnableOnTicket($conn, $ticketId);

    logTicketCreated($conn, $ticketId, 'WhatsApp');

    return ['id' => $ticketId, 'ticket_number' => $ticketNumber, 'created' => true];
}

function ticketsWhatsappCopyMediaToTicketUpload(string $sourceRelativePath, string $originalName): ?array
{
    $waRoot = dirname(__DIR__, 2) . '/whatsapp/';
    $source = $waRoot . ltrim(str_replace('\\', '/', $sourceRelativePath), '/');
    if (!is_file($source)) {
        return null;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/attachments/' . date('Y') . '/' . date('m') . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $originalName) ?: basename($source);
    $unique = uniqid('wa_', true) . '_' . $safeName;
    $dest = $uploadDir . $unique;
    if (!copy($source, $dest)) {
        return null;
    }

    $mime = mime_content_type($dest) ?: 'application/octet-stream';
    return [
        'relative_path' => 'uploads/attachments/' . date('Y') . '/' . date('m') . '/' . $unique,
        'filename' => $safeName,
        'mime_type' => $mime,
        'size' => (int) filesize($dest),
    ];
}

/**
 * @param array<string,mixed> $chatRecord
 */
function ticketsWhatsappSaveAttachmentForMessage(
    mysqli $conn,
    int $ticketId,
    int $ticketMessageId,
    array $chatRecord
): void {
    $mediaPath = $chatRecord['media_path'] ?? null;
    if (!is_string($mediaPath) || $mediaPath === '') {
        return;
    }

    $type = (string) ($chatRecord['type'] ?? 'file');
    $filename = match ($type) {
        'image' => 'imagen_whatsapp.jpg',
        'audio' => 'audio_whatsapp.ogg',
        'video' => 'video_whatsapp.mp4',
        'document' => (string) ($chatRecord['body'] ?? 'documento'),
        default => basename($mediaPath),
    };

    $copied = ticketsWhatsappCopyMediaToTicketUpload($mediaPath, $filename);
    if ($copied === null) {
        return;
    }

    $stmt = $conn->prepare(
        'INSERT INTO ticket_attachments (ticket_id, message_id, ticket_message_id, filename, file_name, file_path, mime_type, size, is_inline, created_at)
         VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 0, NOW())'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param(
        'iissssi',
        $ticketId,
        $ticketMessageId,
        $copied['filename'],
        $copied['filename'],
        $copied['relative_path'],
        $copied['mime_type'],
        $copied['size']
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * @return array{ticket_message_id:int}
 */
function ticketsWhatsappInsertMessage(
    mysqli $conn,
    int $ticketId,
    int $requesterId,
    string $senderName,
    string $senderEmail,
    string $body,
    string $waMessageId,
    string $waType,
    string $senderType = 'requester',
    ?int $senderId = null
): array {
    $hasWaCols = ticketsWhatsappColumnExists($conn, 'ticket_messages', 'is_whatsapp');
    $senderId = $senderId ?? $requesterId;

    if ($hasWaCols) {
        $stmt = $conn->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, sender_name, sender_email, message, message_id, is_email, is_whatsapp, whatsapp_type, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, ?, NOW())'
        );
        $stmt->bind_param('isisssss', $ticketId, $senderType, $senderId, $senderName, $senderEmail, $body, $waMessageId, $waType);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, sender_name, sender_email, message, message_id, is_email, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())'
        );
        $stmt->bind_param('isissss', $ticketId, $senderType, $senderId, $senderName, $senderEmail, $body, $waMessageId);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('No se pudo guardar mensaje en ticket: ' . $stmt->error);
    }
    $messageId = (int) $stmt->insert_id;
    $stmt->close();

    $upd = $conn->prepare('UPDATE tickets SET last_wa_message_id = ?, updated_at = NOW() WHERE id = ?');
    if ($upd) {
        $upd->bind_param('si', $waMessageId, $ticketId);
        $upd->execute();
        $upd->close();
    }

    return ['ticket_message_id' => $messageId];
}

/**
 * @return array{id:int,ticket_number:string,requester_id:int,created:bool}|null
 */
function ticketsWhatsappResolveInboundTicket(
    mysqli $conn,
    int $requesterId,
    ?int $preferredTicketId,
    ?int $empresaId,
    string $body,
    string $waMessageId
): ?array {
    if ($preferredTicketId !== null && $preferredTicketId > 0) {
        $stmt = $conn->prepare(
            'SELECT id, ticket_number, requester_id, closed_at, status_id
             FROM tickets WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $preferredTicketId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || (int) $row['requester_id'] !== $requesterId) {
            return null;
        }
        $closedAt = $row['closed_at'] ?? null;
        $isClosed = ($closedAt !== null && $closedAt !== '' && $closedAt !== '0000-00-00 00:00:00')
            || (int) ($row['status_id'] ?? 0) === 3;
        if ($isClosed) {
            return null;
        }
        ticketsWhatsappEnableOnTicket($conn, (int) $row['id']);
        return [
            'id' => (int) $row['id'],
            'ticket_number' => (string) ($row['ticket_number'] ?? $row['id']),
            'requester_id' => $requesterId,
            'created' => false,
        ];
    }

    $open = ticketsWhatsappFindOpenTicket($conn, $requesterId, $empresaId);
    if ($open !== null) {
        ticketsWhatsappEnableOnTicket($conn, (int) $open['id']);
        return [
            'id' => (int) $open['id'],
            'ticket_number' => (string) ($open['ticket_number'] ?? $open['id']),
            'requester_id' => $requesterId,
            'created' => false,
        ];
    }

    $ticket = ticketsWhatsappCreateTicket($conn, $requesterId, $body, $waMessageId, $empresaId);
    return [
        'id' => (int) $ticket['id'],
        'ticket_number' => (string) $ticket['ticket_number'],
        'requester_id' => $requesterId,
        'created' => true,
    ];
}

/**
 * Texto visible en el chat cuando el mensaje incluye adjuntos.
 * Oculta placeholders ([Imagen WhatsApp], etc.) y nombres de archivo duplicados.
 */
function ticketsWhatsappChatVisibleMessageText(?string $message, array $attachments = [], ?string $whatsappType = null): string
{
    $text = trim((string) $message);
    if ($text === '') {
        return '';
    }

    if ($attachments === []) {
        return $text;
    }

    $stripped = preg_replace(
        '/^\[(?:Imagen|Image|Documento|Document|Audio|Video|Sticker|Mensaje)\s+WhatsApp\]\s*/iu',
        '',
        $text
    );
    $stripped = trim((string) $stripped);

    if ($stripped === '' && preg_match(
        '/^\[(?:Imagen|Image|Documento|Document|Audio|Video|Sticker|Mensaje)\s+WhatsApp\]$/iu',
        $text
    )) {
        return '';
    }

    foreach ($attachments as $att) {
        $filename = trim((string) ($att['filename'] ?? ''));
        if ($filename === '') {
            continue;
        }
        foreach ([$filename, basename($filename)] as $candidate) {
            if (strcasecmp($text, $candidate) === 0) {
                return '';
            }
            if ($stripped !== '' && strcasecmp($stripped, $candidate) === 0) {
                return '';
            }
        }
    }

    $type = strtolower((string) ($whatsappType ?? ''));
    if (in_array($type, ['image', 'audio', 'document', 'video', 'sticker'], true) && $stripped === '') {
        return '';
    }

    return $stripped !== '' ? $stripped : $text;
}

/**
 * @return list<array<string,mixed>>
 */
function ticketsWhatsappFetchTicketMessages(mysqli $conn, int $ticketId, int $sinceId = 0): array
{
    $hasWa = ticketsWhatsappColumnExists($conn, 'ticket_messages', 'is_whatsapp');
    $sql = 'SELECT tm.*,
            CASE WHEN tm.sender_type = "requester" THEN r.name WHEN tm.sender_type = "technician" THEN t.name ELSE "Sistema" END AS sender_name
            FROM ticket_messages tm
            LEFT JOIN requesters r ON tm.sender_type = "requester" AND tm.sender_id = r.id
            LEFT JOIN technicians t ON tm.sender_type = "technician" AND tm.sender_id = t.id
            WHERE tm.ticket_id = ?';
    if ($hasWa) {
        $sql .= ' AND (tm.is_whatsapp = 1 OR tm.message_id LIKE "wamid.%")';
    } else {
        $sql .= ' AND tm.message_id LIKE "wamid.%"';
    }
    if ($sinceId > 0) {
        $sql .= ' AND tm.id > ?';
    }
    $sql .= ' ORDER BY tm.id ASC';

    if ($sinceId > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $ticketId, $sinceId);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $ticketId);
    }
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($messages as &$msg) {
        $attStmt = $conn->prepare(
            'SELECT id, filename, file_path, mime_type FROM ticket_attachments WHERE ticket_message_id = ?'
        );
        $attStmt->bind_param('i', $msg['id']);
        $attStmt->execute();
        $atts = $attStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $attStmt->close();
        foreach ($atts as &$att) {
            $att['url'] = '../../' . ltrim((string) $att['file_path'], '/');
        }
        unset($att);
        $msg['attachments'] = $atts;
    }
    unset($msg);

    return $messages;
}

/**
 * Importa mensajes entrantes del chat/webhook al ticket abierto (vista en vivo).
 *
 * @return array{synced:int,skipped:int,errors:int}
 */
function ticketsWhatsappSyncInboundForTicket(mysqli $conn, int $ticketId, bool $importWebhooks = false): array
{
    ticketsWhatsappEnsureSchema($conn);

    $stmt = $conn->prepare(
        'SELECT t.id, t.closed_at, t.status_id, r.phone, r.name
         FROM tickets t
         JOIN requesters r ON t.requester_id = r.id
         WHERE t.id = ? LIMIT 1'
    );
    if (!$stmt) {
        return ['synced' => 0, 'skipped' => 0, 'errors' => 1];
    }
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $ticketRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ticketRow || empty($ticketRow['phone'])) {
        return ['synced' => 0, 'skipped' => 0, 'errors' => 1];
    }

    $closedAt = $ticketRow['closed_at'] ?? null;
    $isClosed = ($closedAt !== null && $closedAt !== '' && $closedAt !== '0000-00-00 00:00:00')
        || (int) ($ticketRow['status_id'] ?? 0) === 3;
    if ($isClosed) {
        return ['synced' => 0, 'skipped' => 0, 'errors' => 0];
    }

    $requesterPhone = (string) $ticketRow['phone'];

    if ($importWebhooks && function_exists('whatsappChatSyncIncoming')) {
        whatsappChatSyncIncoming(20);
    }

    $synced = 0;
    $skipped = 0;
    $errors = 0;
    $all = whatsappChatLoadMessages(null, 5000);

    foreach ($all as $row) {
        if (($row['direction'] ?? '') !== 'inbound') {
            continue;
        }
        if (!ticketsWhatsappPhonesMatch($requesterPhone, (string) ($row['phone'] ?? ''))) {
            continue;
        }

        $waMessageId = (string) ($row['message_id'] ?? '');
        if ($waMessageId !== '') {
            $exists = $conn->prepare(
                'SELECT id FROM ticket_messages WHERE ticket_id = ? AND message_id = ? LIMIT 1'
            );
            if ($exists) {
                $exists->bind_param('is', $ticketId, $waMessageId);
                $exists->execute();
                if ($exists->get_result()->fetch_assoc()) {
                    $exists->close();
                    $skipped++;
                    continue;
                }
                $exists->close();
            }
        }

        $normalized = [
            'message_id' => $waMessageId,
            'from' => (string) ($row['phone'] ?? ''),
            'from_name' => (string) ($row['contact_name'] ?? $ticketRow['name'] ?? ''),
            'type' => (string) ($row['type'] ?? 'text'),
            'text' => (string) ($row['body'] ?? ''),
            'media_id' => $row['media_id'] ?? null,
            'mime_type' => $row['mime_type'] ?? null,
        ];

        $result = ticketsWhatsappProcessInbound($normalized, $row, $ticketId);
        if (!empty($result['skipped'])) {
            $skipped++;
        } elseif ($result['ok'] ?? false) {
            $synced++;
        } else {
            $errors++;
        }
    }

    return ['synced' => $synced, 'skipped' => $skipped, 'errors' => $errors];
}

function ticketsWhatsappProcessInbound(array $message, ?array $chatRecord = null, ?int $preferredTicketId = null): array
{
    $conn = ticketsWhatsappDb();
    if (!$conn) {
        return ['ok' => false, 'error' => 'Sin conexión a base de datos'];
    }
    ticketsWhatsappEnsureSchema($conn);

    $waMessageId = (string) ($message['message_id'] ?? '');
    if ($waMessageId !== '') {
        $chk = $conn->prepare('SELECT id, ticket_id FROM ticket_messages WHERE message_id = ? LIMIT 1');
        if ($chk) {
            $chk->bind_param('s', $waMessageId);
            $chk->execute();
            $dup = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($dup) {
                $dupTicketId = (int) ($dup['ticket_id'] ?? 0);
                if ($preferredTicketId !== null && $preferredTicketId > 0 && $dupTicketId === $preferredTicketId) {
                    return ['ok' => true, 'skipped' => true, 'ticket_id' => $dupTicketId];
                }
                return ['ok' => true, 'skipped' => true, 'ticket_id' => $dupTicketId ?: null];
            }
        }
    }

    $phone = whatsappChatNormalizePhone((string) ($message['from'] ?? ''));
    if ($phone === '') {
        return ['ok' => false, 'error' => 'Teléfono vacío'];
    }

    $contactName = (string) ($message['from_name'] ?? '');
    $empresaId = ticketsWhatsappDefaultEmpresaId();
    $body = ticketsWhatsappBuildMessageBody($message);

    try {
        $requester = ticketsWhatsappFindOrCreateRequester($conn, $phone, $contactName, $empresaId);
        $resolved = ticketsWhatsappResolveInboundTicket(
            $conn,
            $requester['id'],
            $preferredTicketId,
            $empresaId,
            $body,
            $waMessageId
        );
        if ($resolved === null) {
            return ['ok' => false, 'error' => 'No se pudo resolver el ticket para este mensaje'];
        }

        $ticketId = $resolved['id'];
        $ticketNumber = $resolved['ticket_number'];
        $createdTicket = !empty($resolved['created']);

        $saved = ticketsWhatsappInsertMessage(
            $conn,
            $ticketId,
            $requester['id'],
            $requester['name'],
            $requester['email'],
            $body,
            $waMessageId,
            (string) ($message['type'] ?? 'text')
        );

        $attachmentRecord = $chatRecord;
        if ($attachmentRecord === null && !empty($message['media_id']) && is_string($message['media_id'])) {
            $mediaId = $message['media_id'];
            if (!str_starts_with($mediaId, 'sim_') && !str_starts_with($mediaId, 'media_suite_')) {
                $dl = whatsappDownloadMediaToChat($mediaId);
                if ($dl['ok'] && !empty($dl['path'])) {
                    $attachmentRecord = [
                        'type' => $message['type'] ?? 'file',
                        'media_path' => $dl['path'],
                        'body' => $body,
                    ];
                }
            }
        }

        if ($attachmentRecord !== null) {
            ticketsWhatsappSaveAttachmentForMessage($conn, $ticketId, $saved['ticket_message_id'], $attachmentRecord);
        }

        if (function_exists('whatsappLogInfo')) {
            whatsappLogInfo('Mensaje WhatsApp vinculado a ticket', [
                'ticket_id' => $ticketId,
                'ticket_number' => $ticketNumber,
                'created_ticket' => $createdTicket,
                'from' => $phone,
                'requester_id' => $requester['id'],
                'id_empresa' => $empresaId,
            ]);
        }

        return [
            'ok' => true,
            'ticket_id' => $ticketId,
            'ticket_number' => $ticketNumber,
            'created_ticket' => $createdTicket,
        ];
    } catch (Throwable $e) {
        if (function_exists('whatsappLogError')) {
            whatsappLogError('Error vinculando WhatsApp a ticket', ['error' => $e->getMessage()]);
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Reprocesa mensajes inbound del chat local que aún no tienen ticket.
 *
 * @return array{processed:int, tickets_created:int, tickets_linked:int, skipped:int, errors:int}
 */
function ticketsWhatsappReprocessChatLog(int $limit = 1000): array
{
    $conn = ticketsWhatsappDb();
    if (!$conn) {
        return ['processed' => 0, 'tickets_created' => 0, 'tickets_linked' => 0, 'skipped' => 0, 'errors' => 1];
    }
    ticketsWhatsappEnsureSchema($conn);

    $all = whatsappChatLoadMessages(null, $limit);
    $processed = 0;
    $ticketsCreated = 0;
    $ticketsLinked = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($all as $row) {
        if (($row['direction'] ?? '') !== 'inbound') {
            continue;
        }
        $processed++;
        $normalized = [
            'message_id' => (string) ($row['message_id'] ?? ''),
            'from' => (string) ($row['phone'] ?? ''),
            'from_name' => (string) ($row['contact_name'] ?? ''),
            'type' => (string) ($row['type'] ?? 'text'),
            'text' => (string) ($row['body'] ?? ''),
            'media_id' => $row['media_id'] ?? null,
        ];
        if ($normalized['type'] === 'text' && $normalized['text'] === '' && !empty($row['body'])) {
            $normalized['text'] = (string) $row['body'];
        }

        $result = ticketsWhatsappProcessInbound($normalized, $row);
        if (!empty($result['skipped'])) {
            $skipped++;
        } elseif (($result['ok'] ?? false) && !empty($result['created_ticket'])) {
            $ticketsCreated++;
        } elseif ($result['ok'] ?? false) {
            $ticketsLinked++;
        } else {
            $errors++;
        }
    }

    return [
        'processed' => $processed,
        'tickets_created' => $ticketsCreated,
        'tickets_linked' => $ticketsLinked,
        'skipped' => $skipped,
        'errors' => $errors,
    ];
}

function ticketsWhatsappIsTicket(mysqli $conn, array $ticketRow): bool
{
    // Canal controlado únicamente por el flag del ticket (no por modo de contacto).
    if (!ticketsWhatsappColumnExists($conn, 'tickets', 'whatsapp_enabled')) {
        return false;
    }

    if (array_key_exists('whatsapp_enabled', $ticketRow)) {
        return (int) ($ticketRow['whatsapp_enabled'] ?? 0) === 1;
    }

    $ticketId = (int) ($ticketRow['id'] ?? 0);
    if ($ticketId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('SELECT whatsapp_enabled FROM tickets WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['whatsapp_enabled'] ?? 0) === 1;
}

/**
 * @return array{ok:bool,error?:string,ticket_message_id?:int,meta?:array}
 */
function ticketsWhatsappSendFromTechnician(
    mysqli $conn,
    int $ticketId,
    int $technicianId,
    string $phone,
    string $type,
    string $body,
    ?array $uploadedFile = null
): array {
    $phone = whatsappChatNormalizePhone($phone);
    if ($phone === '') {
        return ['ok' => false, 'error' => 'El solicitante no tiene teléfono WhatsApp'];
    }

    $techStmt = $conn->prepare('SELECT name, email FROM technicians WHERE id = ?');
    $techStmt->bind_param('i', $technicianId);
    $techStmt->execute();
    $tech = $techStmt->get_result()->fetch_assoc();
    $techStmt->close();
    if (!$tech) {
        return ['ok' => false, 'error' => 'Técnico no encontrado'];
    }

    $mediaPath = null;
    $mimeType = null;
    $result = null;

    if ($type === 'text') {
        if (trim($body) === '') {
            return ['ok' => false, 'error' => 'Escriba un mensaje'];
        }
        $plainBody = trim(strip_tags($body));
        $result = whatsappSendTextMessage($phone, $plainBody);
        $body = $plainBody;
    } elseif ($type === 'image' || $type === 'audio' || $type === 'document') {
        if ($uploadedFile === null || empty($uploadedFile['tmp_name'])) {
            return ['ok' => false, 'error' => 'Archivo requerido'];
        }
        $uploadFilename = basename((string) ($uploadedFile['name'] ?? 'archivo'));
        $mimeType = mime_content_type($uploadedFile['tmp_name']) ?: ($uploadedFile['type'] ?? 'application/octet-stream');
        $upload = whatsappUploadMedia($uploadedFile['tmp_name'], $mimeType);
        if (!$upload['ok'] || empty($upload['media_id'])) {
            return ['ok' => false, 'error' => $upload['error'] ?? 'Error al subir archivo a Meta'];
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
        if (@copy($uploadedFile['tmp_name'], $localCopy)) {
            $mediaPath = 'storage/chat/media/' . basename($localCopy);
        }
    } else {
        return ['ok' => false, 'error' => 'Tipo no soportado'];
    }

    if (!$result || !$result['ok']) {
        return ['ok' => false, 'error' => $result['error'] ?? 'Meta API rechazó el envío'];
    }

    $waMessageId = (string) ($result['response']['messages'][0]['id'] ?? '');
    $displayBody = $type === 'text' ? $body : ($body !== '' ? $body : '[' . ucfirst($type) . ' WhatsApp]');

    $saved = ticketsWhatsappInsertMessage(
        $conn,
        $ticketId,
        0,
        (string) $tech['name'],
        (string) $tech['email'],
        $displayBody,
        $waMessageId,
        $type,
        'technician',
        $technicianId
    );

    if ($mediaPath !== null) {
        ticketsWhatsappSaveAttachmentForMessage($conn, $ticketId, $saved['ticket_message_id'], [
            'type' => $type,
            'media_path' => $mediaPath,
            'body' => $displayBody,
        ]);
    }

    whatsappChatSaveMessage([
        'direction' => 'outbound',
        'phone' => $phone,
        'type' => $type,
        'body' => $displayBody,
        'media_path' => $mediaPath,
        'mime_type' => $mimeType,
        'message_id' => $waMessageId ?: null,
        'status' => 'sent',
        'ticket_id' => $ticketId,
    ]);

    $upd = $conn->prepare('UPDATE tickets SET status_id = 2 WHERE id = ? AND status_id = 1');
    if ($upd) {
        $upd->bind_param('i', $ticketId);
        $upd->execute();
        $upd->close();
    }

    return ['ok' => true, 'ticket_message_id' => $saved['ticket_message_id'], 'meta' => $result['response'] ?? []];
}

function ticketsWhatsappNotifyTicketClosed(mysqli $conn, int $ticketId, string $ticketNumber): void
{
    if (!whatsappIsConfigured()) {
        return;
    }

    $stmt = $conn->prepare(
        'SELECT r.phone, r.name FROM tickets t JOIN requesters r ON t.requester_id = r.id WHERE t.id = ?'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['phone'])) {
        return;
    }

    $phone = whatsappChatNormalizePhone((string) $row['phone']);
    if ($phone === '') {
        return;
    }

    $name = trim((string) ($row['name'] ?? ''));
    $greeting = $name !== '' ? "Hola {$name}," : 'Hola,';
    $text = "{$greeting} su ticket #{$ticketNumber} ha sido cerrado. "
        . 'Si necesita más ayuda, envíe un nuevo mensaje por WhatsApp y abriremos un nuevo ticket.';

    whatsappSendTextMessage($phone, $text);
}
