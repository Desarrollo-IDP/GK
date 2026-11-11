<?php
/**
 * Logger simple para el módulo WhatsApp
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function whatsappLog(string $level, string $message, array $context = []): void
{
    whatsappEnsureStorage();

    $line = sprintf(
        "[%s] [%s] %s",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message
    );

    if (!empty($context)) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $line .= PHP_EOL;
    file_put_contents(WHATSAPP_LOG_DIR . '/whatsapp.log', $line, FILE_APPEND | LOCK_EX);
}

function whatsappLogInfo(string $message, array $context = []): void
{
    whatsappLog('info', $message, $context);
}

function whatsappLogError(string $message, array $context = []): void
{
    whatsappLog('error', $message, $context);
}

function whatsappSaveIncomingPayload(array $payload): void
{
    whatsappEnsureStorage();
    $filename = WHATSAPP_INCOMING_DIR . '/' . date('Y-m-d_H-i-s') . '_' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
