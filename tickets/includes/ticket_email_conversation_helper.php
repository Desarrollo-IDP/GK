<?php
/**
 * Renderizado de la conversación por correo en vista de ticket.
 */

require_once __DIR__ . '/whatsapp_ticket_bridge.php';

function ticketEmailNormalizeText(string $text): string
{
    if (function_exists('normalizeSpecialCharacters')) {
        return (string) normalizeSpecialCharacters($text);
    }
    return $text;
}

function ticketEmailIsHtml(string $string): bool
{
    return $string !== strip_tags($string);
}

function ticketEmailProcessHtmlBodyWithCidImages(string $html_body, int $ticket_id, array $attachments): string
{
    if ($html_body === '') {
        return '';
    }

    $processed_html = $html_body;

    foreach ($attachments as $attachment) {
        $contentId = isset($attachment['content_id']) ? trim((string) $attachment['content_id'], '<>') : '';
        if ($contentId === '') {
            continue;
        }

        $downloadUrl = 'download_attachment.php?id=' . $attachment['id'] . '&action=preview';
        $patterns = [
            'cid:' . $contentId,
            'CID:' . $contentId,
            'cid:&lt;' . $contentId . '&gt;',
            'CID:&lt;' . $contentId . '&gt;',
            'cid:"' . $contentId . '"',
            'CID:"' . $contentId . '"',
        ];

        foreach ($patterns as $pattern) {
            if (strpos($processed_html, $pattern) !== false) {
                $processed_html = str_replace($pattern, $downloadUrl, $processed_html);
            }
        }
    }

    return $processed_html;
}

/**
 * @return array{messages: array<int, array>, tree: array<int, array>, last_message_id: int}
 */
function ticketEmailFetchConversation(mysqli $conn, int $ticketId, bool $isWhatsappTicket): array
{
    $hasWaCol = ticketsWhatsappColumnExists($conn, 'ticket_messages', 'is_whatsapp');
    $waExpr = $hasWaCol ? 'tm.is_whatsapp = 1' : '0';

    $stmt = $conn->prepare("
        SELECT
            tm.*,
            CASE
                WHEN tm.sender_type = 'requester' THEN r.name
                WHEN tm.sender_type = 'technician' THEN t.name
                ELSE 'Sistema'
            END AS sender_name,
            CASE
                WHEN tm.sender_type = 'requester' THEN r.email
                WHEN tm.sender_type = 'technician' THEN t.email
                ELSE NULL
            END AS sender_email,
            CASE
                WHEN {$waExpr} THEN 'whatsapp'
                WHEN tm.is_email = 1 THEN 'email'
                ELSE 'system'
            END AS message_type
        FROM ticket_messages tm
        LEFT JOIN requesters r ON tm.sender_type = 'requester' AND tm.sender_id = r.id
        LEFT JOIN technicians t ON tm.sender_type = 'technician' AND tm.sender_id = t.id
        WHERE tm.ticket_id = ?
        ORDER BY tm.id ASC
    ");
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $message_map = [];
    $message_tree = [];
    $lastMessageId = 0;

    foreach ($messages as $message) {
        $message_map[$message['id']] = $message;
        $message_map[$message['id']]['replies'] = [];
        $lastMessageId = max($lastMessageId, (int) $message['id']);
    }

    foreach ($messages as $message) {
        if ($isWhatsappTicket && ($message['message_type'] ?? '') === 'whatsapp') {
            continue;
        }
        $messageId = (int) $message['id'];
        if (!empty($message['parent_message_id'])) {
            $parentId = (int) $message['parent_message_id'];
            if (isset($message_map[$parentId])) {
                $message_map[$parentId]['replies'][] = $message_map[$messageId];
            }
        } else {
            $message_tree[] = $message_map[$messageId];
        }
    }

    return [
        'messages' => $messages,
        'tree' => $message_tree,
        'last_message_id' => $lastMessageId,
    ];
}

function ticketEmailFetchAttachments(mysqli $conn, int $ticketId): array
{
    $stmt = $conn->prepare('SELECT *, content_id FROM ticket_attachments WHERE ticket_id = ? ORDER BY uploaded_at DESC');
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $attachments;
}

function ticketEmailRenderAttachmentCard(array $attachment): string
{
    $file_extension = strtolower(pathinfo((string) ($attachment['filename'] ?? ''), PATHINFO_EXTENSION));
    $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'], true);
    $is_pdf = $file_extension === 'pdf';
    $is_doc = in_array($file_extension, ['doc', 'docx'], true);
    $is_xls = in_array($file_extension, ['xls', 'xlsx'], true);
    $filename = htmlspecialchars((string) ($attachment['filename'] ?? 'Archivo'), ENT_QUOTES, 'UTF-8');
    $mime = htmlspecialchars((string) ($attachment['mime_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $id = (int) ($attachment['id'] ?? 0);

    ob_start();
    ?>
    <div class="col-md-3 mb-2">
        <?php if ($is_image): ?>
            <div class="card">
                <img src="<?php echo htmlspecialchars('download_attachment.php?id=' . $id); ?>"
                     class="card-img-top"
                     alt="<?php echo $filename; ?>"
                     style="height: 150px; object-fit: cover;">
                <div class="card-body p-2">
                    <p class="card-text small text-truncate">
                        <i class="fas fa-image text-primary"></i>
                        <?php echo $filename; ?>
                    </p>
                    <a href="#" class="btn btn-sm btn-primary preview-attachment" data-id="<?php echo $id; ?>" data-type="<?php echo $mime; ?>">
                        <i class="fas fa-eye"></i> Vista previa
                    </a>
                </div>
            </div>
        <?php elseif ($is_pdf): ?>
            <div class="card">
                <div class="card-body p-2 text-center">
                    <i class="fas fa-file-pdf fa-3x text-danger mb-2"></i>
                    <p class="card-text small text-truncate"><?php echo $filename; ?></p>
                    <a href="#" class="btn btn-sm btn-primary preview-attachment" data-id="<?php echo $id; ?>" data-type="<?php echo $mime; ?>">
                        <i class="fas fa-eye"></i> Vista previa
                    </a>
                </div>
            </div>
        <?php elseif ($is_doc): ?>
            <div class="card">
                <div class="card-body p-2 text-center">
                    <i class="fas fa-file-word fa-3x text-primary mb-2"></i>
                    <p class="card-text small text-truncate"><?php echo $filename; ?></p>
                    <a href="#" class="btn btn-sm btn-primary preview-attachment" data-id="<?php echo $id; ?>" data-type="<?php echo $mime; ?>">
                        <i class="fas fa-eye"></i> Vista previa
                    </a>
                </div>
            </div>
        <?php elseif ($is_xls): ?>
            <div class="card">
                <div class="card-body p-2 text-center">
                    <i class="fas fa-file-excel fa-3x text-success mb-2"></i>
                    <p class="card-text small text-truncate"><?php echo $filename; ?></p>
                    <a href="#" class="btn btn-sm btn-primary preview-attachment" data-id="<?php echo $id; ?>" data-type="<?php echo $mime; ?>">
                        <i class="fas fa-eye"></i> Vista previa
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body p-2 text-center">
                    <i class="fas fa-file fa-3x text-secondary mb-2"></i>
                    <p class="card-text small text-truncate"><?php echo $filename; ?></p>
                    <a href="#" class="btn btn-sm btn-primary preview-attachment" data-id="<?php echo $id; ?>" data-type="<?php echo $mime; ?>">
                        <i class="fas fa-eye"></i> Vista previa
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function ticketEmailRenderMessage(array $message, int $level, int $ticketId, array $allAttachments): string
{
    $avatar_class = 'system';
    $avatar_icon = 'fa-cogs';
    if (($message['sender_type'] ?? '') === 'requester') {
        $avatar_class = 'requester';
        $avatar_icon = 'fa-user-circle';
    } elseif (($message['sender_type'] ?? '') === 'technician') {
        $avatar_class = 'technician';
        $avatar_icon = 'fa-headset';
    }

    $senderName = htmlspecialchars(ticketEmailNormalizeText((string) ($message['sender_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $messageType = (string) ($message['message_type'] ?? 'system');
    $messageId = (int) ($message['id'] ?? 0);

    ob_start();
    ?>
    <div class="message-container" data-message-id="<?php echo $messageId; ?>" style="margin-left: <?php echo $level; ?>rem;">
        <div class="message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="message-header">
                <div class="message-avatar <?php echo $avatar_class; ?>">
                    <i class="fas <?php echo $avatar_icon; ?>"></i>
                </div>
                <div class="message-info">
                    <h6 class="sender-name"><?php echo $senderName; ?></h6>
                    <div class="message-meta">
                        <div class="message-date">
                            <i class="fas fa-clock"></i>
                            <?php echo date('d/m/Y H:i', strtotime((string) $message['created_at'])); ?>
                        </div>
                        <?php if ($messageType === 'email' && !empty($message['sender_email'])): ?>
                            <a href="mailto:<?php echo htmlspecialchars((string) $message['sender_email'], ENT_QUOTES, 'UTF-8'); ?>" class="message-email">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars((string) $message['sender_email'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($messageType === 'whatsapp'): ?>
                            <span class="badge-channel-inline"><i class="fas fa-sms"></i> Mensajería</span>
                        <?php endif; ?>
                        <?php if (!empty($message['cc_email'])): ?>
                            <span class="message-cc">
                                <i class="fas fa-copy"></i>
                                CC: <?php echo htmlspecialchars((string) $message['cc_email'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="message-badges">
                    <?php if ($messageType === 'email'): ?>
                        <span class="badge-email"><i class="fas fa-envelope"></i> Email</span>
                    <?php endif; ?>
                    <?php if ($messageType === 'whatsapp'): ?>
                        <span class="badge-channel-inline"><i class="fas fa-sms"></i> Mensajería</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="message-content">
                <?php
                $htmlBody = trim((string) ($message['message_html'] ?? ''));
                $textBodyOriginal = trim((string) ($message['message_text'] ?? ''));
                $summaryBody = (string) ($message['message'] ?? '');
                $hasRenderedContent = false;

                if ($htmlBody !== '') {
                    echo '<div class="message-body-html">' . ticketEmailProcessHtmlBodyWithCidImages($htmlBody, $ticketId, $allAttachments) . '</div>';
                    $hasRenderedContent = true;
                } elseif ($textBodyOriginal !== '') {
                    echo '<pre class="message-text-body">' . htmlspecialchars($textBodyOriginal, ENT_QUOTES, 'UTF-8') . '</pre>';
                    $hasRenderedContent = true;
                } elseif ($summaryBody !== '') {
                    if (ticketEmailIsHtml($summaryBody)) {
                        echo '<div class="message-body-html">' . ticketEmailProcessHtmlBodyWithCidImages($summaryBody, $ticketId, $allAttachments) . '</div>';
                    } else {
                        echo nl2br(htmlspecialchars($summaryBody, ENT_QUOTES, 'UTF-8'));
                    }
                    $hasRenderedContent = true;
                }

                if (!$hasRenderedContent) {
                    echo '<em class="text-muted">(Mensaje sin contenido)</em>';
                }
                ?>
            </div>
            <?php
            $msgAttachments = array_filter($allAttachments, static function ($attachment) use ($message) {
                return (
                    (isset($attachment['message_id']) && (int) $attachment['message_id'] === (int) $message['id'])
                    || (isset($attachment['ticket_message_id']) && (int) $attachment['ticket_message_id'] === (int) $message['id'])
                );
            });

            if (!empty($msgAttachments)):
            ?>
                <div class="attachment-list mt-2">
                    <h6>Adjuntos de este mensaje:</h6>
                    <div class="row">
                        <?php foreach ($msgAttachments as $attachment) {
                            echo ticketEmailRenderAttachmentCard($attachment);
                        } ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($message['replies'])): ?>
                <div class="message-replies">
                    <?php foreach ($message['replies'] as $reply) {
                        echo ticketEmailRenderMessage($reply, $level + 1, $ticketId, $allAttachments);
                    } ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * @return array{html: string, count: int, last_message_id: int}
 */
function ticketEmailRenderConversation(mysqli $conn, int $ticketId, bool $isWhatsappTicket, ?array $allAttachments = null): array
{
    if ($allAttachments === null) {
        $allAttachments = ticketEmailFetchAttachments($conn, $ticketId);
    }

    $conversation = ticketEmailFetchConversation($conn, $ticketId, $isWhatsappTicket);
    $messageTree = $conversation['tree'];

    ob_start();
    if (empty($messageTree)) {
        echo '<div class="ticket-chat-empty" id="ticketEmailEmpty"><i class="fas fa-envelope-open-text"></i><p>Sin mensajes por correo aún. La conversación se actualizará aquí automáticamente.</p></div>';
    } else {
        foreach ($messageTree as $message) {
            echo ticketEmailRenderMessage($message, 0, $ticketId, $allAttachments);
        }
    }
    $html = (string) ob_get_clean();

    return [
        'html' => $html,
        'count' => count($conversation['messages']),
        'last_message_id' => (int) $conversation['last_message_id'],
    ];
}
