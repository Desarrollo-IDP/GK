<?php
/**
 * Estado de configuración del módulo WhatsApp (sin exponer secretos)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

$cfg = whatsappConfig();

echo json_encode([
    'module' => 'whatsapp',
    'configured' => whatsappIsConfigured(),
    'app_id' => !empty($cfg['app_id']),
    'access_token' => !empty($cfg['access_token']),
    'phone_number_id' => $cfg['phone_number_id'] ?? null,
    'business_account_id' => $cfg['business_account_id'] ?? null,
    'verify_token_set' => !empty($cfg['verify_token']),
    'app_secret_set' => !empty($cfg['app_secret']),
    'webhook_url_local' => appLocalUrl('/whatsapp/webhook.php'),
    'chat_url' => appLocalUrl('/whatsapp/chat.php'),
    'graph_api_version' => $cfg['graph_api_version'] ?? 'v22.0',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
