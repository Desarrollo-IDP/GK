<?php
/**
 * Configuración WhatsApp Cloud API
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function whatsappConfig(): array
{
    return [
        'app_id' => whatsappEnv('WHATSAPP_APP_ID'),
        'access_token' => whatsappEnv('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => whatsappEnv('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => whatsappEnv('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'verify_token' => whatsappEnv('WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => whatsappEnv('WHATSAPP_APP_SECRET'),
        'graph_api_version' => whatsappEnv('WHATSAPP_GRAPH_API_VERSION', 'v22.0'),
    ];
}

function whatsappIsConfigured(): bool
{
    $cfg = whatsappConfig();
    return !empty($cfg['access_token'])
        && !empty($cfg['phone_number_id'])
        && !empty($cfg['verify_token']);
}

function whatsappGraphUrl(string $path): string
{
    $cfg = whatsappConfig();
    $version = $cfg['graph_api_version'] ?? 'v22.0';
    $path = ltrim($path, '/');
    return "https://graph.facebook.com/{$version}/{$path}";
}
