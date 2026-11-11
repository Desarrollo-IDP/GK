<?php
/**
 * Configuración del Procesador de Emails
 * Parámetros globales para el procesamiento automático de emails
 */

// Número máximo de empresas a procesar por ejecución
define('EMAIL_PROCESSOR_MAX_EMPRESAS_PER_RUN', 3);

// Timeout máximo por empresa en segundos (4 minutos)
define('EMAIL_PROCESSOR_TIMEOUT_PER_EMPRESA', 240);

// Número máximo de correos a procesar por empresa por ejecución (por defecto)
define('EMAIL_PROCESSOR_MAX_EMAILS_PER_EMPRESA', 50);

// Intervalo por defecto entre procesamientos (en minutos)
define('EMAIL_PROCESSOR_DEFAULT_INTERVAL', 5);

// Número máximo de errores consecutivos antes de deshabilitar procesamiento
define('EMAIL_PROCESSOR_MAX_ERROR_COUNT', 10);

// Timeout para conexión IMAP (en segundos)
define('EMAIL_PROCESSOR_IMAP_CONNECTION_TIMEOUT', 30);

// Habilitar logging detallado (true/false)
// false = solo errores y avisos importantes
// true  = progreso, DEBUG, omitidos, etc.
if (!defined('EMAIL_PROCESSOR_DEBUG')) {
    define('EMAIL_PROCESSOR_DEBUG', false);
}

