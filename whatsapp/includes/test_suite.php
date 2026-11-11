<?php
/**
 * Suite de pruebas del módulo WhatsApp (CLI y panel web)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/message_store.php';
require_once __DIR__ . '/meta_api.php';

/**
 * @return list<array{name:string,group:string,ok:bool,message:string,details?:mixed}>
 */
function whatsappRunTestSuite(string $baseUrl = 'http://localhost:90/GK', bool $liveSend = false, ?string $liveSendPhone = null): array
{
    $baseUrl = rtrim($baseUrl, '/');
    $webhookUrl = $baseUrl . '/whatsapp/webhook.php';
    $results = [];

    $add = static function (string $group, string $name, bool $ok, string $message, $details = null) use (&$results): void {
        $row = compact('group', 'name', 'ok', 'message');
        if ($details !== null) {
            $row['details'] = $details;
        }
        $results[] = $row;
    };

    // --- Configuración ---
    $cfg = whatsappConfig();
    $add('Configuración', 'Variables .env cargadas', whatsappIsConfigured(), whatsappIsConfigured() ? 'OK' : 'Faltan access_token, phone_number_id o verify_token');
    $add('Configuración', 'App ID', !empty($cfg['app_id']), !empty($cfg['app_id']) ? $cfg['app_id'] : 'No definido');
    $add('Configuración', 'Phone Number ID', !empty($cfg['phone_number_id']), (string) ($cfg['phone_number_id'] ?? 'No definido'));
    $add('Configuración', 'Business Account ID', !empty($cfg['business_account_id']), (string) ($cfg['business_account_id'] ?? 'No definido'));
    $add('Configuración', 'Verify Token', !empty($cfg['verify_token']), !empty($cfg['verify_token']) ? 'Configurado' : 'No definido');
    $add('Configuración', 'App Secret (firma webhook)', true, !empty($cfg['app_secret']) ? 'Configurado — firma POST activa' : 'Opcional — sin App Secret (recomendado en producción)');

    // --- Almacenamiento ---
    whatsappChatEnsureStorage();
    $writable = is_writable(WHATSAPP_STORAGE) && is_writable(WHATSAPP_LOG_DIR) && is_writable(WHATSAPP_INCOMING_DIR) && is_writable(WHATSAPP_CHAT_DIR);
    $add('Almacenamiento', 'Directorios escribibles', $writable, $writable ? 'storage, logs, incoming, chat OK' : 'Revisar permisos en whatsapp/storage/');

    $testMsg = whatsappChatSaveMessage([
        'direction' => 'outbound',
        'phone' => '0000000000',
        'contact_name' => 'Test Suite',
        'type' => 'system',
        'body' => 'Prueba de almacén ' . date('H:i:s'),
        'status' => 'test',
    ]);
    $loaded = whatsappChatLoadMessages('0000000000', 5);
    $add('Almacenamiento', 'Guardar y leer mensajes', !empty($loaded), count($loaded) . ' mensaje(s) en conversación de prueba', ['id' => $testMsg['id'] ?? null]);

    // --- Webhook GET ---
    $challenge = 'suite_' . bin2hex(random_bytes(4));
    $verifyToken = $cfg['verify_token'] ?? '';
    $getUrl = $webhookUrl . '?' . http_build_query([
        'hub.mode' => 'subscribe',
        'hub.verify_token' => $verifyToken,
        'hub.challenge' => $challenge,
    ]);

    $getResponse = whatsappHttpRequest('GET', $getUrl);
    $getOk = $getResponse['http_code'] === 200 && trim((string) $getResponse['body']) === $challenge;
    $add('Webhook', 'GET verificación Meta', $getOk, $getOk ? 'Challenge devuelto correctamente' : 'HTTP ' . $getResponse['http_code'], $getResponse['body'] !== '' ? substr($getResponse['body'], 0, 120) : $getResponse['error']);

    // --- Webhook POST texto ---
    $textPayload = whatsappBuildTestPayload('text', 'Mensaje de prueba suite — texto');
    $postText = whatsappHttpRequest('POST', $webhookUrl, $textPayload);
    $postTextOk = $postText['http_code'] === 200;
    $postTextJson = json_decode((string) $postText['body'], true);
    $add('Webhook', 'POST mensaje texto', $postTextOk && ($postTextJson['messages_received'] ?? 0) >= 1, $postTextOk ? 'messages_received=' . ($postTextJson['messages_received'] ?? 0) : 'HTTP ' . $postText['http_code']);

    // --- Webhook POST imagen simulada ---
    $imagePayload = whatsappBuildTestPayload('image', 'Foto de prueba', ['caption' => 'Imagen simulada suite']);
    $postImage = whatsappHttpRequest('POST', $webhookUrl, $imagePayload);
    $postImageOk = $postImage['http_code'] === 200;
    $postImageJson = json_decode((string) $postImage['body'], true);
    $add('Webhook', 'POST mensaje imagen (simulado)', $postImageOk && ($postImageJson['messages_received'] ?? 0) >= 1, $postImageOk ? 'OK' : 'HTTP ' . $postImage['http_code']);

    // --- Webhook POST audio simulado ---
    $audioPayload = whatsappBuildTestPayload('audio', '');
    $postAudio = whatsappHttpRequest('POST', $webhookUrl, $audioPayload);
    $postAudioOk = $postAudio['http_code'] === 200;
    $postAudioJson = json_decode((string) $postAudio['body'], true);
    $add('Webhook', 'POST mensaje audio (simulado)', $postAudioOk && ($postAudioJson['messages_received'] ?? 0) >= 1, $postAudioOk ? 'OK' : 'HTTP ' . $postAudio['http_code']);

    // --- Webhook POST vacío (debe fallar) ---
    $postEmpty = whatsappHttpRequest('POST', $webhookUrl, '');
    $add('Webhook', 'POST cuerpo vacío (esperado 400)', $postEmpty['http_code'] === 400, 'HTTP ' . $postEmpty['http_code']);

    // --- Meta API ---
    $metaPing = whatsappGraphGet(whatsappGraphUrl($cfg['phone_number_id'] ?? ''));
    $add('Meta API', 'Conexión Graph API (phone number)', $metaPing['ok'], $metaPing['ok'] ? 'Token y phone_number_id válidos' : ($metaPing['error'] ?? 'Error'), $metaPing['response']['display_phone_number'] ?? null);

    if ($liveSend && $liveSendPhone) {
        $send = whatsappSendTextMessage($liveSendPhone, 'Prueba SecuriTI WhatsApp — ' . date('d/m/Y H:i:s'));
        $add('Meta API', 'Envío real de texto', $send['ok'], $send['ok'] ? 'Mensaje enviado' : ($send['error'] ?? 'Error'), $send['response'] ?? null);
        if ($send['ok']) {
            whatsappChatSaveMessage([
                'direction' => 'outbound',
                'phone' => preg_replace('/\D+/', '', $liveSendPhone),
                'type' => 'text',
                'body' => 'Prueba SecuriTI WhatsApp — ' . date('d/m/Y H:i:s'),
                'status' => 'sent',
                'message_id' => $send['response']['messages'][0]['id'] ?? null,
            ]);
        }
    }

    // --- Log ---
    $logFile = WHATSAPP_LOG_DIR . '/whatsapp.log';
    $add('Logs', 'Archivo whatsapp.log', is_readable($logFile), is_readable($logFile) ? 'Última modificación: ' . date('Y-m-d H:i:s', filemtime($logFile)) : 'No existe');

    $incoming = glob(WHATSAPP_INCOMING_DIR . '/*.json') ?: [];
    $add('Logs', 'Payloads incoming guardados', count($incoming) > 0, count($incoming) . ' archivo(s)');

    $conversations = whatsappChatListConversations();
    $add('Chat', 'Conversaciones en almacén local', true, count($conversations) . ' conversación(es)');

    return $results;
}

function whatsappBuildTestPayload(string $type, string $text, array $extra = []): string
{
    $cfg = whatsappConfig();
    $phone = '5215599998888';
    $msg = [
        'from' => $phone,
        'id' => 'wamid.SUITE_' . bin2hex(random_bytes(6)),
        'timestamp' => (string) time(),
        'type' => $type,
    ];

    switch ($type) {
        case 'text':
            $msg['text'] = ['body' => $text];
            break;
        case 'image':
            $msg['image'] = [
                'id' => 'media_suite_image_' . bin2hex(random_bytes(4)),
                'mime_type' => 'image/jpeg',
                'caption' => $extra['caption'] ?? $text,
            ];
            break;
        case 'audio':
            $msg['audio'] = [
                'id' => 'media_suite_audio_' . bin2hex(random_bytes(4)),
                'mime_type' => 'audio/ogg',
            ];
            break;
    }

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => $cfg['business_account_id'] ?? '',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => [
                        'phone_number_id' => $cfg['phone_number_id'] ?? '',
                        'display_phone_number' => '15550000000',
                    ],
                    'contacts' => [[
                        'profile' => ['name' => 'Suite Tester'],
                        'wa_id' => $phone,
                    ]],
                    'messages' => [$msg],
                ],
            ]],
        ]],
    ];

    return json_encode($payload, JSON_UNESCAPED_UNICODE);
}

/**
 * @return array{http_code:int, body:string, error:?string}
 */
function whatsappHttpRequest(string $method, string $url, ?string $body = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $body ?? '';
        $opts[CURLOPT_HTTPHEADER] = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body ?? ''),
        ];
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'body' => $response === false ? '' : (string) $response,
        'error' => $error ?: null,
    ];
}

function whatsappTestSuiteSummary(array $results): array
{
    $passed = count(array_filter($results, fn($r) => $r['ok']));
    $failed = count($results) - $passed;
    return [
        'total' => count($results),
        'passed' => $passed,
        'failed' => $failed,
        'all_ok' => $failed === 0,
    ];
}
