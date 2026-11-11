<?php
/**
 * Bootstrap del módulo WhatsApp (Meta Cloud API)
 */

declare(strict_types=1);

define('WHATSAPP_ROOT', dirname(__DIR__));
define('WHATSAPP_STORAGE', WHATSAPP_ROOT . '/storage');
define('WHATSAPP_LOG_DIR', WHATSAPP_STORAGE . '/logs');
define('WHATSAPP_INCOMING_DIR', WHATSAPP_STORAGE . '/incoming');

/**
 * Carga variables desde whatsapp/.env
 */
function whatsappLoadEnv(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $envFile = WHATSAPP_ROOT . '/.env';
    if (!is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
        if (!getenv($key)) {
            putenv("{$key}={$value}");
        }
    }

    $loaded = true;
}

function whatsappEnv(string $key, ?string $default = null): ?string
{
    whatsappLoadEnv();
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

function whatsappEnsureStorage(): void
{
    foreach ([WHATSAPP_STORAGE, WHATSAPP_LOG_DIR, WHATSAPP_INCOMING_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

whatsappLoadEnv();
whatsappEnsureStorage();
