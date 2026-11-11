<?php
/**
 * Cliente HTTP para Meta WhatsApp Cloud API
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';

/**
 * Envía un mensaje de texto por WhatsApp (para pruebas / uso futuro en tickets)
 *
 * @return array{ok:bool, http_code:int, response:mixed, error?:string}
 */
function whatsappSendTextMessage(string $toPhone, string $body): array
{
    $cfg = whatsappConfig();
    if (empty($cfg['access_token']) || empty($cfg['phone_number_id'])) {
        return ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => 'WhatsApp no configurado'];
    }

    $toPhone = preg_replace('/\D+/', '', $toPhone);
    $url = whatsappGraphUrl($cfg['phone_number_id'] . '/messages');

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $toPhone,
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => $body,
        ],
    ];

    return whatsappGraphPost($url, $payload);
}

/**
 * @return array{ok:bool, http_code:int, response:mixed, error?:string}
 */
function whatsappSendImageMessage(string $toPhone, string $mediaId, string $caption = ''): array
{
    $cfg = whatsappConfig();
    $toPhone = preg_replace('/\D+/', '', $toPhone);
    $url = whatsappGraphUrl($cfg['phone_number_id'] . '/messages');

    $image = ['id' => $mediaId];
    if ($caption !== '') {
        $image['caption'] = $caption;
    }

    return whatsappGraphPost($url, [
        'messaging_product' => 'whatsapp',
        'to' => $toPhone,
        'type' => 'image',
        'image' => $image,
    ]);
}

/**
 * @return array{ok:bool, http_code:int, response:mixed, error?:string}
 */
function whatsappSendAudioMessage(string $toPhone, string $mediaId): array
{
    $cfg = whatsappConfig();
    $toPhone = preg_replace('/\D+/', '', $toPhone);
    $url = whatsappGraphUrl($cfg['phone_number_id'] . '/messages');

    return whatsappGraphPost($url, [
        'messaging_product' => 'whatsapp',
        'to' => $toPhone,
        'type' => 'audio',
        'audio' => ['id' => $mediaId],
    ]);
}

/**
 * @return array{ok:bool, http_code:int, response:mixed, error?:string}
 */
function whatsappSendDocumentMessage(string $toPhone, string $mediaId, string $filename = '', string $caption = ''): array
{
    $cfg = whatsappConfig();
    $toPhone = preg_replace('/\D+/', '', $toPhone);
    $url = whatsappGraphUrl($cfg['phone_number_id'] . '/messages');

    $document = ['id' => $mediaId];
    if ($filename !== '') {
        $document['filename'] = $filename;
    }
    if ($caption !== '') {
        $document['caption'] = $caption;
    }

    return whatsappGraphPost($url, [
        'messaging_product' => 'whatsapp',
        'to' => $toPhone,
        'type' => 'document',
        'document' => $document,
    ]);
}

/**
 * Sube un archivo a Meta y devuelve media_id
 *
 * @return array{ok:bool, media_id?:string, http_code:int, response:mixed, error?:string}
 */
function whatsappUploadMedia(string $filePath, string $mimeType): array
{
    $cfg = whatsappConfig();
    if (empty($cfg['access_token']) || empty($cfg['phone_number_id'])) {
        return ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => 'WhatsApp no configurado'];
    }
    if (!is_readable($filePath)) {
        return ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => 'Archivo no legible'];
    }

    $url = whatsappGraphUrl($cfg['phone_number_id'] . '/media');
    $cfile = new CURLFile($filePath, $mimeType, basename($filePath));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cfg['access_token']],
        CURLOPT_POSTFIELDS => [
            'messaging_product' => 'whatsapp',
            'type' => $mimeType,
            'file' => $cfile,
        ],
        CURLOPT_TIMEOUT => 60,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'http_code' => $httpCode, 'response' => null, 'error' => $curlError];
    }

    $decoded = json_decode($raw, true);
    $ok = $httpCode >= 200 && $httpCode < 300;
    $mediaId = $decoded['id'] ?? null;

    return [
        'ok' => $ok && !empty($mediaId),
        'media_id' => $mediaId,
        'http_code' => $httpCode,
        'response' => $decoded,
        'error' => $ok ? null : ($decoded['error']['message'] ?? 'Error al subir media'),
    ];
}

/**
 * @return array{ok:bool, http_code:int, response:mixed, error?:string}
 */
function whatsappGraphGet(string $url): array
{
    $cfg = whatsappConfig();
    $token = $cfg['access_token'] ?? '';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT => 30,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'http_code' => $httpCode, 'response' => null, 'error' => $curlError];
    }

    $decoded = json_decode($raw, true);
    $ok = $httpCode >= 200 && $httpCode < 300;

    return [
        'ok' => $ok,
        'http_code' => $httpCode,
        'response' => $decoded,
        'error' => $ok ? null : ($decoded['error']['message'] ?? 'Error desconocido'),
    ];
}

/**
 * Descarga media de Meta al almacén local del chat
 *
 * @return array{ok:bool, path?:string, mime_type?:string, error?:string}
 */
function whatsappDownloadMediaToChat(string $mediaId): array
{
    require_once __DIR__ . '/message_store.php';
    whatsappChatEnsureStorage();

    $meta = whatsappGraphGet(whatsappGraphUrl($mediaId));
    if (!$meta['ok'] || empty($meta['response']['url'])) {
        return ['ok' => false, 'error' => $meta['error'] ?? 'No se obtuvo URL de media'];
    }

    $mime = $meta['response']['mime_type'] ?? 'application/octet-stream';
    $ext = whatsappMimeToExtension($mime);
    $filename = $mediaId . '.' . $ext;
    $dest = WHATSAPP_CHAT_MEDIA_DIR . '/' . $filename;

    if (is_file($dest)) {
        return ['ok' => true, 'path' => 'storage/chat/media/' . $filename, 'mime_type' => $mime];
    }

    $cfg = whatsappConfig();
    $ch = curl_init($meta['response']['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . ($cfg['access_token'] ?? '')],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $binary = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($binary === false || $httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => 'No se pudo descargar el archivo'];
    }

    file_put_contents($dest, $binary);
    return ['ok' => true, 'path' => 'storage/chat/media/' . $filename, 'mime_type' => $mime];
}

function whatsappMimeToExtension(string $mime): string
{
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'audio/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'video/mp4' => 'mp4',
        'application/pdf' => 'pdf',
    ];
    return $map[$mime] ?? 'bin';
}

/**
 * @return array{ok:bool, http_code:int, response:mixed, error?:string}
 */
function whatsappGraphPost(string $url, array $payload): array
{
    $cfg = whatsappConfig();
    $token = $cfg['access_token'] ?? '';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        whatsappLogError('Error cURL Meta API', ['error' => $curlError, 'url' => $url]);
        return ['ok' => false, 'http_code' => $httpCode, 'response' => null, 'error' => $curlError];
    }

    $decoded = json_decode($raw, true);
    $ok = $httpCode >= 200 && $httpCode < 300;

    if (!$ok) {
        whatsappLogError('Meta API respondió con error', ['http_code' => $httpCode, 'response' => $decoded]);
    }

    return [
        'ok' => $ok,
        'http_code' => $httpCode,
        'response' => $decoded,
        'error' => $ok ? null : ($decoded['error']['message'] ?? 'Error desconocido'),
    ];
}
