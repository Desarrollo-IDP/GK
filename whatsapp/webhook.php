<?php
/**
 * Webhook Meta WhatsApp Cloud API
 *
 * URL para configurar en Meta Developers:
 *   https://TU-DOMINIO/whatsapp/webhook.php
 *
 * GET  → verificación (hub.verify_token / hub.challenge)
 * POST → mensajes entrantes
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/webhook_handler.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    whatsappHandleWebhookVerification();
    exit;
}

if ($method === 'POST') {
    whatsappHandleWebhookPost();
    exit;
}

http_response_code(405);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
