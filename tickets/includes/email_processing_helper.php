<?php
/**
 * Helper Functions para Procesamiento de Emails
 * Funciones comunes utilizadas en el procesamiento de emails
 */

if (!defined('EMAIL_RAW_MAX_BYTES')) {
    define('EMAIL_RAW_MAX_BYTES', 512 * 1024); // 512 KB
}
if (!defined('EMAIL_FIELD_SOFT_LIMIT')) {
    define('EMAIL_FIELD_SOFT_LIMIT', 128 * 1024); // 128 KB
}
if (!defined('EMAIL_PROCESSOR_DEBUG')) {
    // Detalle verboso desactivado por defecto (activar en config_email_processor.php)
    define('EMAIL_PROCESSOR_DEBUG', false);
}

/**
 * ¿Logging detallado de correo activo?
 */
function emailProcessorDebugEnabled(): bool
{
    return defined('EMAIL_PROCESSOR_DEBUG') && EMAIL_PROCESSOR_DEBUG;
}

function emailImapSinceTodayDate(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('d-M-Y');
}

/**
 * Log de procesamiento de correo.
 * Por defecto solo errores y avisos reales. Con EMAIL_PROCESSOR_DEBUG=true se registra todo.
 *
 * @param mixed $message
 * @param string $level auto|error|warn|info|debug
 */
function emailProcessLog($message, string $level = 'auto'): void
{
    if (!is_string($message)) {
        $message = print_r($message, true);
    }
    $message = trim($message);
    if ($message === '') {
        return;
    }

    $debug = emailProcessorDebugEnabled();

    if ($level === 'auto') {
        if (preg_match('/^\s*DEBUG\b|\bDEBUG\s*[-:]/iu', $message)) {
            $level = 'debug';
        } elseif (preg_match('/\b(ERROR|EXCEPCI[OÓ]N|CRITICAL|FATAL)\b/iu', $message)) {
            $level = 'error';
        } elseif (preg_match('/\b(ADVERTENCIA|WARNING|WARN)\b/iu', $message)) {
            $level = 'warn';
        } else {
            $level = 'info';
        }
    }

    // Avisos frecuentes / no accionables → solo en debug
    if ($level === 'warn' && preg_match('/ya (fue )?procesado|hu[eé]rfano|omitiendo|lista negra|codificaci[oó]n desconocida/iu', $message)) {
        $level = 'debug';
    }

    // Progreso rutinario → solo en debug
    if ($level === 'info' && preg_match(
        '/^(Iniciando|Ticket encontrado|Archivo adjunto|Message-ID generado|Correo identificado|Remitente identificado|Nuevo ticket|Nuevo solicitante|Solicitante (encontrado|creado)|Procesando email|Email UID|Buscando TODOS|De \d+ correos|Encontrados \d+|Correo UID|Todos los correos|No se encontraron correos|Procesamiento (completado|finalizado)|Esquema actualizado|===|✓|✗|Hora:|Empresas |Procesando empresa|No hay empresas|PHPMailer Debug|Stack trace|#\d+\s+)/iu',
        $message
    )) {
        $level = 'debug';
    }

    if ($level === 'debug' && !$debug) {
        return;
    }

    $prefix = match ($level) {
        'error' => '[EMAIL][ERROR] ',
        'warn' => '[EMAIL][WARN] ',
        'debug' => '[EMAIL][DEBUG] ',
        default => '[EMAIL] ',
    };

    $clean = preg_replace('/^(DEBUG|ERROR|ADVERTENCIA)\s*[-:]?\s*/iu', '', $message) ?? $message;
    error_log($prefix . $clean);
}

/**
 * Cierra de forma segura un statement de MySQLi
 */
function safeCloseStmt(?mysqli_stmt $stmt): void {
    if ($stmt instanceof mysqli_stmt) {
        try {
            // Verificar si el statement tiene un resultado asociado antes de intentar liberarlo
            // No llamar free_result() si el statement ya está cerrado
            if (method_exists($stmt, 'free_result')) {
                @ $stmt->free_result();
            }
        } catch (Exception $e) {
            // Ignorar errores si el statement ya está cerrado o no tiene resultado
        }
        try {
            @ $stmt->close();
        } catch (Exception $e) {
            // Ignorar errores si el statement ya está cerrado
        }
    }
}

/**
 * Asegura que la conexión a la base de datos esté activa
 */
function ensureDbConnection(): void {
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli) || !$conn->ping()) {
        if ($conn instanceof mysqli) {
            @ $conn->close();
        }
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception('No se pudo restablecer la conexión con la base de datos: ' . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
    }
}

/**
 * Registra un log de procesamiento en la base de datos
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $id_empresa ID de la empresa
 * @param string $status Estado: 'success', 'error', 'timeout', 'skipped'
 * @param int $emails_processed Número de correos procesados
 * @param int $emails_found Número de correos encontrados
 * @param float $execution_time Tiempo de ejecución en segundos
 * @param string|null $error_message Mensaje de error (opcional)
 * @return bool True si se registró correctamente
 */
function logEmailProcessing(
    mysqli $conn,
    int $id_empresa,
    string $status,
    int $emails_processed = 0,
    int $emails_found = 0,
    float $execution_time = 0.00,
    ?string $error_message = null
): bool {
    try {
        $stmt = $conn->prepare("
            INSERT INTO email_processing_log 
            (id_empresa, status, emails_processed, emails_found, execution_time, error_message, last_processed_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt) {
            error_log("Error preparando log de procesamiento: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("isiids", 
            $id_empresa,
            $status,
            $emails_processed,
            $emails_found,
            $execution_time,
            $error_message
        );
        
        $result = $stmt->execute();
        if (!$result) {
            error_log("Error ejecutando log de procesamiento: " . $stmt->error);
        }
        
        safeCloseStmt($stmt);
        return $result;
    } catch (Exception $e) {
        error_log("Excepción al registrar log de procesamiento: " . $e->getMessage());
        return false;
    }
}

/**
 * Actualiza el estado de procesamiento de una empresa
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $id_empresa ID de la empresa
 * @param bool $success Si el procesamiento fue exitoso
 * @param string|null $error_message Mensaje de error si hubo error
 * @return bool True si se actualizó correctamente
 */
function updateEmpresaProcessingStatus(
    mysqli $conn,
    int $id_empresa,
    bool $success = true,
    ?string $error_message = null
): bool {
    try {
        if ($success) {
            // Resetear contador de errores y actualizar última ejecución exitosa
            $stmt = $conn->prepare("
                UPDATE empresa_email_config 
                SET last_processed_at = NOW(), 
                    error_count = 0,
                    last_error = NULL
                WHERE id_empresa = ?
            ");
            $stmt->bind_param("i", $id_empresa);
        } else {
            // Incrementar contador de errores
            $stmt = $conn->prepare("
                UPDATE empresa_email_config 
                SET error_count = error_count + 1,
                    last_error = ?,
                    processing_enabled = CASE 
                        WHEN error_count + 1 >= ? THEN 0 
                        ELSE processing_enabled 
                    END
                WHERE id_empresa = ?
            ");
            $max_errors = defined('EMAIL_PROCESSOR_MAX_ERROR_COUNT') ? EMAIL_PROCESSOR_MAX_ERROR_COUNT : 10;
            $stmt->bind_param("sii", $error_message, $max_errors, $id_empresa);
        }
        
        $result = $stmt->execute();
        if (!$result) {
            error_log("Error actualizando estado de procesamiento: " . $stmt->error);
        }
        
        safeCloseStmt($stmt);
        return $result;
    } catch (Exception $e) {
        error_log("Excepción al actualizar estado de procesamiento: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene empresas que necesitan procesamiento
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param int $max_empresas Número máximo de empresas a retornar
 * @return array Array de empresas que necesitan procesamiento
 */
function getEmpresasPendingProcessing(mysqli $conn, int $max_empresas = 3): array {
    try {
        // Obtener todas las empresas activas que tienen configuración de email
        // y que necesitan procesamiento (ya sea que nunca se procesaron o que pasó el intervalo)
        $query = "
            SELECT eec.*, eu.nombre as empresa_nombre
            FROM empresa_email_config eec
            INNER JOIN empresas_usuarios eu ON eec.id_empresa = eu.id_empresa
            WHERE eec.activo = 1 
            AND (
                eec.last_processed_at IS NULL 
                OR eec.last_processed_at < DATE_SUB(NOW(), INTERVAL eec.processing_interval_minutes MINUTE)
            )
            ORDER BY 
                CASE WHEN eec.processing_enabled = 1 THEN 0 ELSE 1 END,
                eec.last_processed_at IS NULL DESC, 
                eec.last_processed_at ASC
            LIMIT ?
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Error preparando consulta de empresas pendientes: " . $conn->error);
            return [];
        }
        
        $stmt->bind_param("i", $max_empresas);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $empresas = [];
        while ($row = $result->fetch_assoc()) {
            $empresas[] = $row;
        }
        
        // Debug: Log de empresas encontradas
        if (emailProcessorDebugEnabled()) {
            emailProcessLog("getEmpresasPendingProcessing: Encontradas " . count($empresas) . " empresas pendientes", 'debug');
            foreach ($empresas as $emp) {
                emailProcessLog(
                    "Empresa ID {$emp['id_empresa']}: {$emp['empresa_nombre']}, " .
                    "processing_enabled: {$emp['processing_enabled']}, " .
                    "último procesamiento: " . ($emp['last_processed_at'] ?? 'Nunca'),
                    'debug'
                );
            }
            
            // También mostrar todas las empresas activas para comparación
            $all_query = "SELECT eec.id_empresa, eu.nombre, eec.activo, eec.processing_enabled, eec.last_processed_at, eec.processing_interval_minutes
                         FROM empresa_email_config eec
                         INNER JOIN empresas_usuarios eu ON eec.id_empresa = eu.id_empresa
                         WHERE eec.activo = 1";
            $all_result = $conn->query($all_query);
            if ($all_result) {
                emailProcessLog("Todas las empresas activas:", 'debug');
                while ($all_row = $all_result->fetch_assoc()) {
                    $is_pending = is_null($all_row['last_processed_at']) || 
                                 strtotime($all_row['last_processed_at']) < (time() - ($all_row['processing_interval_minutes'] * 60));
                    emailProcessLog(
                        "Empresa ID {$all_row['id_empresa']}: {$all_row['nombre']}, " .
                        "activo: {$all_row['activo']}, processing_enabled: {$all_row['processing_enabled']}, " .
                        "último procesamiento: " . ($all_row['last_processed_at'] ?? 'Nunca') .
                        ($is_pending ? " [PENDIENTE]" : " [NO PENDIENTE]"),
                        'debug'
                    );
                }
            }
        }
        
        safeCloseStmt($stmt);
        return $empresas;
    } catch (Exception $e) {
        error_log("Excepción al obtener empresas pendientes: " . $e->getMessage());
        return [];
    }
}

/**
 * Normaliza caracteres especiales en texto
 */
function normalizeSpecialCharacters($text) {
    if (empty($text)) {
        return $text;
    }
    
    // Asegurar que el texto sea UTF-8 válido
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'auto');
    }
    
    // Normalizar caracteres especiales comunes en español
    $normalizations = [
        // Acentos y caracteres especiales
        'á' => 'á', 'é' => 'é', 'í' => 'í', 'ó' => 'ó', 'ú' => 'ú', 'ñ' => 'ñ',
        'Á' => 'Á', 'É' => 'É', 'Í' => 'Í', 'Ó' => 'Ó', 'Ú' => 'Ú', 'Ñ' => 'Ñ',
        'ü' => 'ü', 'Ü' => 'Ü',
        
        // Caracteres de puntuación especiales
        '¿' => '¿', '¡' => '¡', '«' => '«', '»' => '»',
        
        // Eliminar caracteres de control excepto saltos de línea
        "\x00" => '', "\x01" => '', "\x02" => '', "\x03" => '', "\x04" => '',
        "\x05" => '', "\x06" => '', "\x07" => '', "\x08" => '', "\x0B" => '',
        "\x0C" => '', "\x0E" => '', "\x0F" => '', "\x10" => '', "\x11" => '',
        "\x12" => '', "\x13" => '', "\x14" => '', "\x15" => '', "\x16" => '',
        "\x17" => '', "\x18" => '', "\x19" => '', "\x1A" => '', "\x1B" => '',
        "\x1C" => '', "\x1D" => '', "\x1E" => '', "\x1F" => '', "\x7F" => ''
    ];
    
    $text = strtr($text, $normalizations);
    
    // Normalizar saltos de línea
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    
    // Eliminar espacios múltiples
    $text = preg_replace('/[ \t]+/', ' ', $text);
    
    return trim($text);
}

/**
 * Une líneas plegadas de un header RFC 5322 (CRLF + espacio/tab → espacio).
 */
function emailUnfoldHeaders(string $headerRaw): string
{
    $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $headerRaw);
    return is_string($unfolded) ? $unfolded : $headerRaw;
}

/**
 * Decodifica encoded-words MIME (RFC 2047), p. ej. =?iso-8859-1?Q?Luis_...?=
 */
function emailDecodeMimeHeaderValue(string $value): string
{
    $value = trim($value, " \t\"'");
    if ($value === '') {
        return '';
    }

    if (!preg_match('/=\?/', $value)) {
        return $value;
    }

    if (function_exists('imap_mime_header_decode')) {
        $parts = @imap_mime_header_decode($value);
        if (is_array($parts) && $parts !== []) {
            $out = '';
            foreach ($parts as $part) {
                $charset = strtoupper(trim((string) ($part->charset ?? 'UTF-8')));
                $text = (string) ($part->text ?? '');
                if ($charset !== '' && $charset !== 'DEFAULT' && $charset !== 'UTF-8' && function_exists('iconv')) {
                    $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
                    $out .= ($converted !== false) ? $converted : $text;
                } else {
                    $out .= $text;
                }
            }
            return trim($out);
        }
    }

    if (function_exists('mb_decode_mimeheader')) {
        return trim((string) mb_decode_mimeheader($value));
    }

    if (function_exists('imap_utf8')) {
        return trim((string) imap_utf8($value));
    }

    return $value;
}

/**
 * Extrae el valor completo de un campo de header (después de unfold).
 */
function emailExtractHeaderField(string $headerRaw, string $fieldName): string
{
    $unfolded = emailUnfoldHeaders($headerRaw);
    if (preg_match('/^' . preg_quote($fieldName, '/') . ':\s*(.+)$/mi', $unfolded, $matches)) {
        return trim((string) $matches[1]);
    }
    return '';
}

/**
 * Indica si un string parece un encoded-word MIME, no un email.
 */
function emailLooksLikeMimeEncodedWord(string $value): bool
{
    return (bool) preg_match('/^=\?[^?]+\?[bqBQ]\?/u', trim($value));
}

/**
 * Parsea un valor From/To (ya extraído) a email + nombre legible.
 *
 * @return array{email:string,name:string}
 */
function parseEmailAddressValue(string $fromHeader): array
{
    $fromHeader = trim(preg_replace('/\s+/u', ' ', $fromHeader) ?? $fromHeader);
    $email = '';
    $name = '';

    if ($fromHeader === '') {
        return ['email' => '', 'name' => ''];
    }

    // Formato: Nombre <correo@dominio.com>
    if (preg_match('/<([^<>\s]+@[^<>\s]+)>/', $fromHeader, $matches)) {
        $email = strtolower(trim($matches[1]));
        $name = trim(str_replace($matches[0], '', $fromHeader));
        $name = trim($name, " \t\"'");
    } elseif (preg_match('/([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/', $fromHeader, $matches)) {
        // Solo email, o nombre + email sin <>
        $email = strtolower(trim($matches[1]));
        $name = trim(str_replace($matches[1], '', $fromHeader), " \t\"'<>");
    }

    $name = emailDecodeMimeHeaderValue($name);
    if (function_exists('normalizeSpecialCharacters')) {
        $name = normalizeSpecialCharacters($name);
    }

    // Nunca aceptar encoded-word ni texto inválido como email
    if (
        $email === ''
        || emailLooksLikeMimeEncodedWord($email)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        return ['email' => '', 'name' => $name];
    }

    if ($name === '' || emailLooksLikeMimeEncodedWord($name) || strcasecmp($name, $email) === 0) {
        $name = $email;
    }

    return ['email' => $email, 'name' => $name];
}

/**
 * Extrae remitente desde headers IMAP raw.
 *
 * @return array{email:string,name:string}
 */
function parseEmailAddressFromHeader(string $headerRaw): array
{
    $fromValue = emailExtractHeaderField($headerRaw, 'From');
    return parseEmailAddressValue($fromValue);
}
