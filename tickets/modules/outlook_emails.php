<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../conexion/config_imap.php');
require_once(__DIR__ . '/../conexion/config_mailer.php');
require_once(__DIR__ . '/../conexion/db.php');
require_once(__DIR__ . '/../includes/ticket_history.php');
require_once(__DIR__ . '/../conexion/get_empresa_email_config.php');
require_once(__DIR__ . '/../includes/email_processing_helper.php');

emailProcessLog('Iniciando script de procesamiento de correos');

// Verificar la conexión a la base de datos
if (!$conn) {
    emailProcessLog('ERROR: No se pudo establecer la conexión con la base de datos');
    die('Error de conexión a la base de datos');
}

// Verificar si las extensiones necesarias están instaladas
if (!extension_loaded('imap')) {
    emailProcessLog('ERROR: La extensión IMAP no está instalada en el servidor.');
    die('La extensión IMAP no está instalada en el servidor.');
}

function ensureEnhancedEmailStorageSchema() {
    static $checked = false;
    if ($checked) {
        return;
    }

    global $conn;
    if (!$conn) {
        return;
    }

    $columns = [
        'message_html' => "ALTER TABLE ticket_messages ADD COLUMN message_html LONGTEXT NULL AFTER message",
        'message_text' => "ALTER TABLE ticket_messages ADD COLUMN message_text LONGTEXT NULL AFTER message_html",
        'headers_raw' => "ALTER TABLE ticket_messages ADD COLUMN headers_raw LONGTEXT NULL AFTER message_text",
        'headers_json' => "ALTER TABLE ticket_messages ADD COLUMN headers_json LONGTEXT NULL AFTER headers_raw",
        'raw_source' => "ALTER TABLE ticket_messages ADD COLUMN raw_source LONGTEXT NULL AFTER headers_json",
        'content_type' => "ALTER TABLE ticket_messages ADD COLUMN content_type VARCHAR(255) NULL AFTER raw_source"
    ];

    foreach ($columns as $column => $alterSql) {
        $result = $conn->query("SHOW COLUMNS FROM ticket_messages LIKE '" . $conn->real_escape_string($column) . "'");
        if ($result && $result->num_rows === 0) {
            emailProcessLog("Esquema actualizado: Añadiendo columna '{$column}' en ticket_messages");
            if (!$conn->query($alterSql)) {
                emailProcessLog("ERROR al alterar la tabla ticket_messages para añadir {$column}: " . $conn->error);
            }
        }
        if ($result) {
            $result->free();
        }
    }

    $checked = true;
}

ensureEnhancedEmailStorageSchema();

function buildHeaderSummary(
    \ZBateson\MailMimeParser\Message $parsedMessage,
    string $headerRaw,
    string $fromEmail,
    string $fromName,
    ?string $messageId,
    ?string $inReplyTo,
    ?string $references
): array {
    $extractAddresses = function ($headerName) use ($parsedMessage) {
        $addresses = [];
        $header = $parsedMessage->getHeader($headerName);
        if ($header) {
            foreach ($header->getAddresses() as $address) {
                $addresses[] = array_filter([
                    'name' => $address->getName() ?: null,
                    'email' => $address->getEmail() ?: null
                ]);
            }
        }
        return $addresses;
    };

    $subjectHeader = $parsedMessage->getHeader('subject');
    $dateHeader = $parsedMessage->getHeader('date');
    $replyToHeader = $parsedMessage->getHeader('reply-to');

    return [
        'from' => [
            'name' => $fromName,
            'email' => $fromEmail
        ],
        'to' => $extractAddresses('to'),
        'cc' => $extractAddresses('cc'),
        'bcc' => $extractAddresses('bcc'),
        'reply_to' => $replyToHeader ? array_map(function ($address) {
            return array_filter([
                'name' => $address->getName() ?: null,
                'email' => $address->getEmail() ?: null
            ]);
        }, $replyToHeader->getAddresses()) : [],
        'subject' => $subjectHeader ? $subjectHeader->getValue() : null,
        'date' => $dateHeader ? $dateHeader->getDateTime()->format('Y-m-d H:i:s') : null,
        'message_id' => $messageId,
        'in_reply_to' => $inReplyTo,
        'references' => $references
    ];
}

function detectTicketId($subject, $in_reply_to = '', $references = '') {
    global $conn;
    ensureDbConnection();
    
    // Limpiar el asunto de etiquetas de spam comunes para facilitar la detección
    // Esto permite encontrar tickets incluso cuando hay prefijos como ***SPAM***
    $subject_limpio = preg_replace('/\*\*\*SPAM\*\*\*/i', '', $subject);
    $subject_limpio = preg_replace('/\*\*SPAM\*\*/i', '', $subject_limpio);
    $subject_limpio = preg_replace('/\[SPAM\]/i', '', $subject_limpio);
    $subject_limpio = preg_replace('/\s+/', ' ', trim($subject_limpio));
    
    $patterns = [
        '/\[Ticket #(\d+)\]/i',
        '/\[Case #(\d+)\]/i',
        '/Ticket #(\d+)/i',
        '/Case #(\d+)/i',
        '/Ticket: (\d+)/i',
        '/Case: (\d+)/i', 
        '/Re: \[Ticket #(\d+)\]/i',
        '/Re: \[Case #(\d+)\]/i',
        '/Re: Ticket #(\d+)/i',
        '/Re: Case #(\d+)/i',
        '/Re: Ticket: (\d+)/i',
        '/Re: Case: (\d+)/i',
        '/Respuesta: \[Ticket #(\d+)\]/i',
        '/Respuesta: Ticket #(\d+)/i',
        '/RV: \[Ticket #(\d+)\]/i',
        '/RV: Ticket #(\d+)/i',
        '/FWD: \[Ticket #(\d+)\]/i',
        '/FWD: Ticket #(\d+)/i'
    ];
    
    // Obtener empresa_id del contexto global si está disponible
    $empresa_id = $GLOBALS['current_empresa_id'] ?? null;
    
    // Función auxiliar para buscar ticket por ticket_number o ID
    $buscarTicketPorNumero = function($numero) use ($conn, $empresa_id) {
        $ticket_number = trim($numero);
        
        // Primero intentar buscar por ticket_number (considerando empresa si está disponible)
        if ($empresa_id !== null) {
            $stmt = $conn->prepare("SELECT id FROM tickets WHERE ticket_number = ? AND id_empresa = ? AND deleted = 0 LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("si", $ticket_number, $empresa_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $result->free();
                    safeCloseStmt($stmt);
                    emailProcessLog("Ticket encontrado por ticket_number: {$ticket_number} (ID: {$row['id']}) para empresa {$empresa_id}");
                    return (int)$row['id'];
                }
                if ($result) $result->free();
                safeCloseStmt($stmt);
            }
        } else {
            // Si no hay empresa_id, buscar sin filtro de empresa
            $stmt = $conn->prepare("SELECT id FROM tickets WHERE ticket_number = ? AND deleted = 0 LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $ticket_number);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $result->free();
                    safeCloseStmt($stmt);
                    emailProcessLog("Ticket encontrado por ticket_number: {$ticket_number} (ID: {$row['id']}) sin filtro de empresa");
                    return (int)$row['id'];
                }
                if ($result) $result->free();
                safeCloseStmt($stmt);
            }
        }
        
        // Si no se encuentra por ticket_number, intentar como ID (para compatibilidad con tickets antiguos)
        if (is_numeric($ticket_number)) {
            $ticket_id = (int)$ticket_number;
            if ($empresa_id !== null) {
                $stmt = $conn->prepare("SELECT id FROM tickets WHERE id = ? AND id_empresa = ? AND deleted = 0 LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("ii", $ticket_id, $empresa_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result && $row = $result->fetch_assoc()) {
                        $result->free();
                        safeCloseStmt($stmt);
                        emailProcessLog("Ticket encontrado por ID: {$ticket_id} para empresa {$empresa_id} (compatibilidad con tickets antiguos)");
                        return (int)$row['id'];
                    }
                    if ($result) $result->free();
                    safeCloseStmt($stmt);
                }
            } else {
                $stmt = $conn->prepare("SELECT id FROM tickets WHERE id = ? AND deleted = 0 LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $ticket_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result && $row = $result->fetch_assoc()) {
                        $result->free();
                        safeCloseStmt($stmt);
                        emailProcessLog("Ticket encontrado por ID: {$ticket_id} (compatibilidad con tickets antiguos)");
                        return (int)$row['id'];
                    }
                    if ($result) $result->free();
                    safeCloseStmt($stmt);
                }
            }
        }
        
        return null;
    };
    
    // Primero buscar en el asunto original
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $subject, $matches)) {
            $numero_encontrado = $matches[1];
            emailProcessLog("Número de ticket encontrado en asunto original: " . $numero_encontrado . " usando patrón: " . $pattern);
            $ticket_id = $buscarTicketPorNumero($numero_encontrado);
            if ($ticket_id) {
                return $ticket_id;
            }
        }
    }
    
    // Si no se encontró en el asunto original y está limpio, buscar en el asunto limpio
    if ($subject_limpio !== $subject) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $subject_limpio, $matches)) {
                $numero_encontrado = $matches[1];
                emailProcessLog("Número de ticket encontrado en asunto limpio: " . $numero_encontrado . " usando patrón: " . $pattern);
                $ticket_id = $buscarTicketPorNumero($numero_encontrado);
                if ($ticket_id) {
                    return $ticket_id;
                }
            }
        }
    }
    
    // Si no se encuentra en el asunto, buscar en las referencias
    if ($in_reply_to || $references) {
        $reference_ids = array_merge(
            [$in_reply_to],
            explode(' ', $references)
        );
        
        foreach ($reference_ids as $ref) {
            if (empty($ref)) continue;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $ref, $matches)) {
                    $numero_encontrado = $matches[1];
                    emailProcessLog("Número de ticket encontrado en referencias: " . $numero_encontrado . " usando patrón: " . $pattern);
                    $ticket_id = $buscarTicketPorNumero($numero_encontrado);
                    if ($ticket_id) {
                        return $ticket_id;
                    }
                }
            }
        }
    }
    
    return null;
}

function storeAttachment($file_content, $filename, $mime_type, $ticket_id, $message_id, $is_inline = false, $content_id = null) {
    global $conn;
    ensureDbConnection();
    
    try {
        emailProcessLog("Iniciando almacenamiento de adjunto: " . $filename);
        
        // Verificar que tenemos todos los datos necesarios
        if (empty($file_content) || empty($filename) || empty($mime_type) || !$ticket_id || !$message_id) {
            emailProcessLog("ERROR: Datos incompletos para almacenar adjunto - " . 
                     "file_content: " . (empty($file_content) ? "vacío" : "presente") . ", " .
                     "filename: " . $filename . ", " .
                     "mime_type: " . $mime_type . ", " .
                     "ticket_id: " . $ticket_id . ", " .
                     "message_id: " . $message_id);
            return false;
        }
        
        // Limpiar el nombre del archivo
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        if (empty($filename)) {
            emailProcessLog("ERROR: Nombre de archivo inválido después de limpieza");
            return false;
        }
        
        // Verificar el tamaño del archivo (máximo 16MB es una referencia general, el límite real depende de la configuración)
        $size = strlen($file_content);
        emailProcessLog("Tamaño del archivo antes de guardar: " . $size . " bytes");
        
        // No hay un límite estricto aquí ya que no se guarda en LONGBLOB,
        // pero es bueno tener una validación básica si aplica.
        // if ($size > 16 * 1024 * 1024) {
        //     emailProcessLog("ERROR: Archivo demasiado grande para procesar: " . $filename . " (" . $size . " bytes)");
        //     return false;
        // }
        
        // Crear directorio para almacenar archivos si no existe
        $upload_dir = __DIR__ . '/../uploads/attachments/' . date('Y/m/');
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                emailProcessLog("ERROR: No se pudo crear el directorio de uploads: " . $upload_dir);
                return false;
            }
        }
        
        // Generar nombre único para el archivo
        $unique_filename = uniqid() . '_' . $filename;
        $file_path = $upload_dir . $unique_filename;
        
        // Guardar el archivo en el sistema de archivos
        if (file_put_contents($file_path, $file_content) === false) {
            emailProcessLog("ERROR: No se pudo guardar el archivo en el sistema: " . $file_path);
            return false;
        }
        
        // Verificar que el archivo se guardó correctamente
        if (!file_exists($file_path) || filesize($file_path) === false || filesize($file_path) != $size) {
            emailProcessLog("ERROR: Verificación del archivo guardado falló. Tamaño esperado: " . $size . ", Tamaño real: " . (file_exists($file_path) ? filesize($file_path) : 'N/A'));
            @unlink($file_path);
            return false;
        }
        
        // Generar una ruta relativa para la base de datos
        $relative_path = 'uploads/attachments/' . date('Y/m/') . $unique_filename;
        
        // Preparar la consulta SQL
        $stmt = $conn->prepare("
            INSERT INTO ticket_attachments 
            (ticket_id, message_id, filename, file_path, mime_type, is_inline, size, content_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt) {
            emailProcessLog("ERROR al preparar la consulta: " . $conn->error);
            @unlink($file_path);
            return false;
        }
        
        $is_inline_int = $is_inline ? 1 : 0;
        
        // Vincular los parámetros
        if (!$stmt->bind_param("iisssiis", 
            $ticket_id,
            $message_id,
            $filename,
            $relative_path,
            $mime_type,
            $is_inline_int,
            $size,
            $content_id
        )) {
            emailProcessLog("ERROR al vincular parámetros: " . $stmt->error);
            safeCloseStmt($stmt);
            @unlink($file_path);
            return false;
        }
        
        // Ejecutar la consulta
        if (!$stmt->execute()) {
            emailProcessLog("ERROR al ejecutar la consulta: " . $stmt->error);
            safeCloseStmt($stmt);
            @unlink($file_path);
            return false;
        }
        
        $attachment_id = $stmt->insert_id;
        safeCloseStmt($stmt);
        
        emailProcessLog("Archivo adjunto almacenado exitosamente. ID: " . $attachment_id . 
                 ", Tamaño: " . $size . " bytes" . 
                 ", MIME: " . $mime_type . 
                 ", Ruta: " . $relative_path . 
                 ($content_id ? ", Content-ID: " . $content_id : ""));
        
        return $attachment_id;
        
    } catch (Exception $e) {
        emailProcessLog("EXCEPCIÓN al almacenar adjunto: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        if (isset($file_path) && file_exists($file_path)) {
            @unlink($file_path);
        }
        return false;
    }
}

function saveAttachment($ticket_id, $message_id, $attachment) {
    global $conn;
    ensureDbConnection();
    
    if (!$ticket_id || !$message_id) {
        emailProcessLog("ERROR: ticket_id o message_id no válidos para guardar adjunto");
        return false;
    }
    
    // Verificar que el archivo temporal existe y tiene contenido
    if (!isset($attachment['temp_path']) || !file_exists($attachment['temp_path'])) {
        emailProcessLog("ERROR: Archivo temporal no encontrado: " . ($attachment['temp_path'] ?? 'no especificado'));
        return false;
    }
    
    $file_size = filesize($attachment['temp_path']);
    if ($file_size === false || $file_size === 0) {
        emailProcessLog("ERROR: Archivo temporal vacío o no accesible: " . $attachment['temp_path']);
        return false;
    }
    
    // Verificar límite de tamaño (16MB para LONGBLOB)
    if ($file_size > 16 * 1024 * 1024) {
        emailProcessLog("ERROR: Archivo demasiado grande para LONGBLOB: " . $attachment['filename'] . " (" . $file_size . " bytes)");
        return false;
    }
    
    // Determinar el tipo MIME
    $mime_type = $attachment['mime_type'] ?? mime_content_type($attachment['temp_path']);
    if (!$mime_type) {
        $mime_type = 'application/octet-stream';
    }
    
    // Lista de tipos MIME permitidos
    $allowed_types = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.document',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/tiff',
        'image/svg+xml',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/x-rar-compressed'
    ];
    
    // Verificar tipo de archivo
    if (!in_array($mime_type, $allowed_types)) {
        emailProcessLog("ERROR: Tipo de archivo no permitido: " . $mime_type . " para " . $attachment['filename']);
        return false;
    }
    
    try {
        // Leer el contenido del archivo en chunks para manejar archivos grandes
        $file_content = '';
        $handle = fopen($attachment['temp_path'], 'rb');
        if ($handle === false) {
            emailProcessLog("ERROR: No se pudo abrir el archivo para lectura: " . $attachment['temp_path']);
            return false;
        }
        
        while (!feof($handle)) {
            $chunk = fread($handle, 8192); // Leer en chunks de 8KB
            if ($chunk === false) {
                emailProcessLog("ERROR: Error al leer el archivo: " . $attachment['temp_path']);
                fclose($handle);
                return false;
            }
            $file_content .= $chunk;
        }
        fclose($handle);
        
        // Verificar que el contenido no está vacío
        if (empty($file_content)) {
            emailProcessLog("ERROR: Contenido del archivo vacío: " . $attachment['temp_path']);
            return false;
        }
        
        // Intentar almacenar el archivo usando la función storeAttachment
        $attachment_id = storeAttachment(
            $file_content,
            $attachment['filename'],
            $mime_type,
            $ticket_id,
            $message_id,
            $attachment['is_inline'] ?? false,
            $attachment['content_id'] ?? null
        );
        
        if ($attachment_id) {
            emailProcessLog("Archivo adjunto guardado exitosamente en la base de datos: " . $attachment['filename']);
            return true;
        } else {
            emailProcessLog("ERROR: No se pudo guardar el archivo adjunto: " . $attachment['filename']);
            return false;
        }
        
    } catch (Exception $e) {
        emailProcessLog("EXCEPCIÓN al guardar adjunto: " . $e->getMessage());
        return false;
    }
}

// Función auxiliar para convertir límites de memoria a bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = substr($val, 0, -1);
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

// Función auxiliar para normalizar caracteres especiales
// normalizeSpecialCharacters está definida en email_processing_helper.php
// Solo definir si no existe (para compatibilidad cuando se ejecuta directamente)
if (!function_exists('normalizeSpecialCharacters')) {
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
}

function cleanEmailContent($body, $contentType = 'text/html') {
    // Si es HTML, mantener el formato original pero mejorar la codificación
    if ($contentType === 'text/html') {
        // Asegurar que el contenido sea UTF-8 válido
        if (!mb_check_encoding($body, 'UTF-8')) {
            $body = mb_convert_encoding($body, 'UTF-8', 'auto');
        }
        
        // Limpiar posibles inyecciones pero mantener el HTML
        $body = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $body);
        $body = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $body);
        
        // Asegurarse de que las imágenes tengan el atributo alt
        $body = preg_replace('/<img(?![^>]*alt=)[^>]*>/i', '<img alt="Imagen" $0>', $body);
        
        // Asegurarse de que las imágenes tengan el atributo style para max-width
        $body = preg_replace('/<img(?![^>]*style=)[^>]*>/i', '<img style="max-width: 100%; height: auto;" $0>', $body);
        
        // Agregar meta charset UTF-8 si no existe
        if (strpos($body, '<meta charset') === false && strpos($body, '<meta http-equiv="Content-Type"') === false) {
            $body = '<meta charset="UTF-8">' . $body;
        }
        
        // Agregar estilos básicos para mejor visualización
        $body = '<div class="email-content" style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; word-wrap: break-word;">' . $body . '</div>';
        
        return $body;
    } else {
        // Para texto plano, asegurar codificación UTF-8 y limpiar
        if (!mb_check_encoding($body, 'UTF-8')) {
            $body = mb_convert_encoding($body, 'UTF-8', 'auto');
        }
        
        // Mantener el procesamiento existente
        $body = preg_replace('/<br\s*\/?>/i', "\n", $body);
        $body = preg_replace('/<\/div>/i', "\n", $body);
        $body = preg_replace('/<\/p>/i', "\n", $body);
        $body = preg_replace('/<\/tr>/i', "\n", $body);
        $body = preg_replace('/<\/h[1-6]>/i', "\n", $body);
        $body = preg_replace('/<li>/i', "- ", $body);
        $body = strip_tags($body);
        $body = preg_replace('/[\r\n]+/', "\n", $body);
        $body = preg_replace('/[ \t]+/', ' ', $body);
        $body = trim($body);
        
        // Normalizar caracteres especiales comunes
        $body = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            $body
        );
        
        return $body;
    }
}

function soloCuerpoMensaje($body, $contentType = 'text/plain') {
    // Log del contenido original para debugging
    emailProcessLog("DEBUG soloCuerpoMensaje - Contenido original: " . substr($body, 0, 200) . "...");
    emailProcessLog("DEBUG soloCuerpoMensaje - Tipo de contenido: " . $contentType);
    
    // Si es HTML, intenta eliminar bloques de cita comunes
    if ($contentType === 'text/html') {
        // Eliminar bloques <blockquote> (usados por Gmail, Outlook, etc.)
        $body = preg_replace('/<blockquote[\s\S]*?<\/blockquote>/i', '', $body);
        
        // Eliminar bloques de Gmail más específicos
        $body = preg_replace('/<div class="gmail_quote"[\s\S]*?<\/div>/i', '', $body);
        $body = preg_replace('/<div class="gmail_extra"[\s\S]*?<\/div>/i', '', $body);
        
        // Eliminar bloques de Outlook más específicos
        $body = preg_replace('/<div class="WordSection\d+"[\s\S]*?<\/div>/i', '', $body);
        $body = preg_replace('/<div style="[^"]*border-top[^"]*"[\s\S]*?<\/div>/i', '', $body);
        
        // Eliminar líneas de separación típicas
        $body = preg_replace('/<hr[\s\S]*?>/i', '', $body);
        
        // Eliminar firmas automáticas más específicas
        $body = preg_replace('/<div class="signature"[\s\S]*?<\/div>/i', '', $body);
        $body = preg_replace('/<div id="signature"[\s\S]*?<\/div>/i', '', $body);
        
        // Eliminar líneas de información del remitente
        $body = preg_replace('/<div[^>]*>De:.*?<\/div>/i', '', $body);
        $body = preg_replace('/<div[^>]*>From:.*?<\/div>/i', '', $body);
        $body = preg_replace('/<div[^>]*>Enviado:.*?<\/div>/i', '', $body);
        $body = preg_replace('/<div[^>]*>Sent:.*?<\/div>/i', '', $body);
        $body = preg_replace('/<div[^>]*>Para:.*?<\/div>/i', '', $body);
        $body = preg_replace('/<div[^>]*>To:.*?<\/div>/i', '', $body);
        $body = preg_replace('/<div[^>]*>Asunto:.*?<\/div>/i', '', $body);
        $body = preg_replace('/<div[^>]*>Subject:.*?<\/div>/i', '', $body);
        
        // Eliminar patrones de respuesta en español e inglés
        $body = preg_replace('/<div[^>]*>El.*?escribió:.*?<\/div>/i', '', $body);
        $body = preg_replace('/<div[^>]*>On.*?wrote:.*?<\/div>/i', '', $body);
        
        // Buscar y eliminar todo después de patrones comunes de respuesta
        if (preg_match('/<div[^>]*>.*?(De:|From:|El .* escribió:|On .* wrote:).*?<\/div>/i', $body, $matches, PREG_OFFSET_CAPTURE)) {
            $body = substr($body, 0, $matches[0][1]);
        }
        
        // Limpiar espacios extra y etiquetas vacías
        $body = preg_replace('/<div[^>]*>\s*<\/div>/i', '', $body);
        $body = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $body);
        $body = trim($body);
        return $body;
    } else {
        // Para texto plano, patrones más exhaustivos y específicos
        $original_body = $body;
        
        // Patrones más específicos y ordenados por prioridad
        $patrones = [
            // Patrón específico para el caso reportado: detectar "El mié, 17 sept 2025 a la(s) 11:03 a.m.,"
            '/El\s+[a-z]{3,4},?\s*\d{1,2}\s+[a-z]{3,4}\s+\d{4}\s+a\s+la\(s\)\s+\d{1,2}:\d{2}\s+[ap]\.m\.,/i',
            
            // Patrón para detectar "Sistema de Tickets (" seguido de email
            '/Sistema\s+de\s+Tickets\s*\([^)]*@[^)]*\)/i',
            
            // Patrones más generales en español con "escribió:"
            '/El\s+[a-z]{3,9},?\s*\d{1,2}\s+de\s+[a-z]{3,9}\s+de\s+\d{4}.*?escribi[óo]:/i',
            '/El\s+\d{1,2}\/\d{1,2}\/\d{4}.*?escribi[óo]:/i',
            '/El\s+[a-z]{3,4},?\s*\d{1,2}\s+[a-z]{3,4}\s+\d{4}.*?escribi[óo]:/i',
            
            // Patrones de información del remitente
            '/De:.*?Enviado:.*?Para:.*?Asunto:/s',
            '/De:\s*.*?Enviado:/i',
            '/From:.*?Sent:.*?To:.*?Subject:/s',
            '/From:\s*.*?Sent:/i',
            
            // Patrones en inglés
            '/On\s+[a-z]{3,9},?\s*[a-z]{3,9}\s*\d{1,2},?\s*\d{4}.*?wrote:/i',
            '/On\s+\d{1,2}\/\d{1,2}\/\d{4}.*?wrote:/i',
            
            // Patrones de dispositivos móviles
            '/Enviado\s+desde\s+mi\s+iPhone/i',
            '/Enviado\s+desde\s+mi\s+Samsung/i',
            '/Enviado\s+desde\s+mi\s+Android/i',
            '/Sent\s+from\s+my\s+iPhone/i',
            '/Sent\s+from\s+my\s+Samsung/i',
            '/Sent\s+from\s+my\s+Android/i',
            
            // Patrones genéricos
            '/-----Original\s+Message-----/i',
            '/-----Mensaje\s+original-----/i',
            '/_{10,}/i',
            '/\*{10,}/i',
        ];
        
        foreach ($patrones as $patron) {
            if (preg_match($patron, $body, $match, PREG_OFFSET_CAPTURE)) {
                $new_body = substr($body, 0, $match[0][1]);
                emailProcessLog("DEBUG soloCuerpoMensaje - Patrón aplicado: " . $patron);
                emailProcessLog("DEBUG soloCuerpoMensaje - Contenido cortado en posición: " . $match[0][1]);
                emailProcessLog("DEBUG soloCuerpoMensaje - Resultado: '" . $new_body . "'");
                $body = $new_body;
                break;
            }
        }
        
        // Si ningún patrón funcionó, intentar con patrones más simples pero más específicos
        if ($body === $original_body) {
            // Buscar específicamente el patrón que vimos en los logs
            if (preg_match('/(.+?)(El\s+[a-z]{3,4},\s*\d{1,2}\s+[a-z]{3,4}\s+\d{4})/i', $body, $matches)) {
                $body = trim($matches[1]);
                emailProcessLog("DEBUG soloCuerpoMensaje - Patrón específico aplicado, resultado: '" . $body . "'");
            } else {
                emailProcessLog("DEBUG soloCuerpoMensaje - Ningún patrón funcionó, contenido original mantenido");
            }
        }
        
        // Limpiar líneas vacías al final y al principio
        $body = trim($body);
        
        // Eliminar firmas comunes al final
        $firmas = [
            '/\n--\n.*$/s',
            '/\nSaludos[,.]?\s*\n.*$/s',
            '/\nAtentamente[,.]?\s*\n.*$/s',
            '/\nBest regards[,.]?\s*\n.*$/s',
            '/\nRegards[,.]?\s*\n.*$/s',
            '/\nThanks[,.]?\s*\n.*$/s',
            '/\nGracias[,.]?\s*\n.*$/s',
        ];
        
        foreach ($firmas as $firma) {
            $body = preg_replace($firma, '', $body);
        }
        
        $final_body = trim($body);
        emailProcessLog("DEBUG soloCuerpoMensaje - Resultado final: " . $final_body);
        return $final_body;
    }
}

function getEmailBody($inbox, $email_id, $structure) {
    if (!$inbox) {
        emailProcessLog("ERROR: Conexión IMAP no válida en getEmailBody");
        return ['body' => '', 'contentType' => 'text/plain'];
    }

    $decodeContent = function($content, $encoding) {
        if ($content === '' || $content === null) {
            return '';
        }
        switch ((int)$encoding) {
            case 3: // BASE64
                $decoded = base64_decode($content, true);
                if ($decoded === false) {
                    return $content;
                }
                return $decoded;
            case 4: // QUOTED-PRINTABLE
                return quoted_printable_decode($content);
            case 0: // 7BIT
            case 1: // 8BIT
            case 2: // BINARY
            default:
                return $content;
        }
    };

    $convertToUtf8 = function($content, $source_charset = null) {
        if ($content === '' || $content === null) {
            return '';
        }
        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }
        $common_charsets = ['UTF-8','ISO-8859-1','ISO-8859-15','Windows-1252','Windows-1251','ASCII','UTF-7','UTF-16','UTF-32'];
        if ($source_charset && !in_array(strtoupper($source_charset), ['ASCII','BINARY','7BIT','8BIT'])) {
            $map = ['ISO88591'=>'ISO-8859-1','ISO885915'=>'ISO-8859-15','WINDOWS1252'=>'Windows-1252','WINDOWS1251'=>'Windows-1251','UTF8'=>'UTF-8','UTF7'=>'UTF-7','UTF16'=>'UTF-16','UTF32'=>'UTF-32'];
            $upper = strtoupper($source_charset);
            $standard = $map[$upper] ?? $source_charset;
            $converted = @mb_convert_encoding($content, 'UTF-8', $standard);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }
        $detected = mb_detect_encoding($content, $common_charsets, true);
        if ($detected) {
            $converted = @mb_convert_encoding($content, 'UTF-8', $detected);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }
        foreach ($common_charsets as $cs) {
            if ($cs === 'UTF-8') continue;
            $converted = @mb_convert_encoding($content, 'UTF-8', $cs);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }
        $iconv_content = @iconv('', 'UTF-8//IGNORE', $content);
        return $iconv_content !== false ? $iconv_content : $content;
    };

    $getCharset = function($part) {
        if (isset($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (strtoupper($param->attribute ?? '') === 'CHARSET') {
                    return trim($param->value, "\"' ");
                }
            }
        }
        return null;
    };

    $collectTextParts = function($parent, $prefix = '') use ($inbox, $email_id, $decodeContent, $convertToUtf8, $getCharset, &$collectTextParts) {
        $found = [];
        if (!isset($parent->parts) || !is_array($parent->parts)) {
            return $found;
        }
        foreach ($parent->parts as $idx => $part) {
            $num = $prefix ? $prefix . '.' . ($idx + 1) : ($idx + 1);
            $type = $part->type ?? null;
            $subtype = strtoupper($part->subtype ?? '');
            if ($type === 1) {
                $found = array_merge($found, $collectTextParts($part, (string)$num));
                continue;
            }
            if ($type === 0 && ($subtype === 'PLAIN' || $subtype === 'HTML')) {
                $raw = imap_fetchbody($inbox, $email_id, (string)$num, FT_UID);
                if ($raw === false) {
                    continue;
                }
                $decoded = $decodeContent($raw, $part->encoding ?? 0);
                $charset = $getCharset($part);
                $utf8 = $convertToUtf8($decoded, $charset);
                $utf8 = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $utf8);
                $utf8 = str_replace(["\r\n", "\r"], "\n", $utf8);
                $utf8 = trim($utf8);
                $found[] = ['contentType' => 'text/' . strtolower($subtype), 'body' => $utf8, 'length' => strlen($utf8)];
                continue;
            }
            if ($type === 2) {
                if (isset($part->parts) && is_array($part->parts)) {
                    $found = array_merge($found, $collectTextParts($part, (string)$num));
                }
            }
        }
        return $found;
    };

    $processed_parts = [];
    if (isset($structure->parts)) {
        $processed_parts = $collectTextParts($structure, '');
    } else {
        $is_text = isset($structure->type) && (int)$structure->type === 0;
        $sub = strtoupper($structure->subtype ?? '');
        if ($is_text && ($sub === 'PLAIN' || $sub === 'HTML')) {
            $raw = imap_body($inbox, $email_id, FT_UID);
            if ($raw !== false) {
                $decoded = $decodeContent($raw, $structure->encoding ?? 0);
                $charset = null;
                if (isset($structure->parameters)) {
                    foreach ($structure->parameters as $p) {
                        if (strtoupper($p->attribute ?? '') === 'CHARSET') { $charset = trim($p->value, "\"' "); break; }
                    }
                }
                $utf8 = $convertToUtf8($decoded, $charset);
                $utf8 = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $utf8);
                $utf8 = str_replace(["\r\n", "\r"], "\n", $utf8);
                $utf8 = trim($utf8);
                $processed_parts[] = ['contentType' => 'text/' . strtolower($sub), 'body' => $utf8, 'length' => strlen($utf8)];
            }
        }
    }

    // Helper: detectar si HTML tiene texto significativo
    $hasMeaningfulHtml = function($html) {
        if ($html === '' || $html === null) return false;
        
        // Remover comentarios, meta tags, y espacios
        $cleaned = preg_replace('/<!--.*?-->/s', '', $html);
        $cleaned = preg_replace('/<meta[^>]*>/i', '', $cleaned);
        $cleaned = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $cleaned);
        $cleaned = preg_replace('/<xml[^>]*>.*?<\/xml>/is', '', $cleaned);
        $cleaned = preg_replace('/<o:[^>]*>.*?<\/o:[^>]*>/is', '', $cleaned);
        $cleaned = preg_replace('/<v:[^>]*>.*?<\/v:[^>]*>/is', '', $cleaned);
        $cleaned = preg_replace('/<w:[^>]*>.*?<\/w:[^>]*>/is', '', $cleaned);
        $cleaned = preg_replace('/<m:[^>]*>.*?<\/m:[^>]*>/is', '', $cleaned);
        $cleaned = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $cleaned);
        $cleaned = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $cleaned);
        
        // Remover etiquetas body vacías
        $cleaned = preg_replace('/<body[^>]*>\s*<\/body>/is', '', $cleaned);
        
        $text = trim(preg_replace('/\xc2\xa0|&nbsp;|\s+/i', ' ', strip_tags($cleaned)));
        
        // Log para debug
        emailProcessLog("DEBUG hasMeaningfulHtml: HTML original length: " . strlen($html));
        emailProcessLog("DEBUG hasMeaningfulHtml: HTML limpio length: " . strlen($cleaned));
        emailProcessLog("DEBUG hasMeaningfulHtml: Texto extraído: '" . $text . "'");
        emailProcessLog("DEBUG hasMeaningfulHtml: Texto length: " . strlen($text));
        
        return strlen($text) > 10; // Requerir al menos 10 caracteres visibles
    };

    // Helper: extraer texto limpio del HTML
    $extractCleanTextFromHtml = function($html) {
        if ($html === '' || $html === null) return '';
        
        // Remover comentarios, meta tags, y espacios
        $cleaned = preg_replace('/<!--.*?-->/s', '', $html);
        $cleaned = preg_replace('/<meta[^>]*>/i', '', $cleaned);
        $cleaned = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $cleaned);
        $cleaned = preg_replace('/<xml[^>]*>.*?<\/xml>/is', '', $cleaned);
        $cleaned = preg_replace('/<o:[^>]*>.*?<\/o:[^>]*>/is', '', $cleaned);
        $cleaned = preg_replace('/<v:[^>]*>.*?<\/v:[^>]*>/is', '', $cleaned);
        $cleaned = preg_replace('/<w:[^>]*>.*?<\/w:[^>]*>/is', '', $cleaned);
        $cleaned = preg_replace('/<m:[^>]*>.*?<\/m:[^>]*>/is', '', $cleaned);
        $cleaned = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $cleaned);
        $cleaned = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $cleaned);
        
        // Remover etiquetas body vacías
        $cleaned = preg_replace('/<body[^>]*>\s*<\/body>/is', '', $cleaned);
        
        $text = trim(preg_replace('/\xc2\xa0|&nbsp;|\s+/i', ' ', strip_tags($cleaned)));
        
        return $text;
    };

    $best_body = '';
    $best_contentType = 'text/plain';

    // Preferir HTML solo si tiene texto visible
    foreach ($processed_parts as $p) {
        if ($p['contentType'] === 'text/html' && $hasMeaningfulHtml($p['body'])) {
            // Extraer texto limpio del HTML en lugar de guardar el HTML completo
            $clean_text = $extractCleanTextFromHtml($p['body']);
            if (strlen($clean_text) > 10) {
                $best_body = $clean_text;
                $best_contentType = 'text/plain'; // Cambiar a texto plano ya que extraemos el texto
                emailProcessLog("DEBUG getEmailBody: HTML seleccionado, extrayendo texto limpio: '" . substr($clean_text, 0, 100) . "'");
                break;
            }
        }
    }
    // Si HTML no sirve, usar texto plano con contenido
    if ($best_body === '') {
        foreach ($processed_parts as $p) {
            if ($p['contentType'] === 'text/plain' && strlen(trim($p['body'])) > 0) {
                $best_body = $p['body'];
                $best_contentType = 'text/plain';
                break;
            }
        }
    }
    // Fallback a cualquier parte no vacía
    if ($best_body === '' && !empty($processed_parts)) {
        foreach ($processed_parts as $p) {
            $candidate = $p['contentType'] === 'text/html' ? ($hasMeaningfulHtml($p['body']) ? $p['body'] : '') : trim($p['body']);
            if ($candidate !== '') {
                $best_body = $candidate;
                $best_contentType = $p['contentType'] === 'text/html' ? 'text/html' : 'text/plain';
                break;
            }
        }
    }

    // Fallback final con MailMimeParser
    if ($best_body === '' || ($best_contentType === 'text/html' && !$hasMeaningfulHtml($best_body))) {
        emailProcessLog('DEBUG getEmailBody: Usando fallback MailMimeParser');
        try {
            $raw_full = imap_fetchbody($inbox, $email_id, '0', FT_UID | FT_PEEK);
            if ($raw_full === false || $raw_full === '') {
                $hdr = imap_fetchheader($inbox, $email_id, FT_UID | FT_PEEK);
                $bd = imap_body($inbox, $email_id, FT_UID | FT_PEEK);
                $raw_full = ($hdr !== false ? $hdr : '') . "\r\n" . ($bd !== false ? $bd : '');
            }
            if ($raw_full) {
                $parser = new \ZBateson\MailMimeParser\MailMimeParser();
                $message = $parser->parse($raw_full, false);
                $html = $message->getHtmlContent();
                $txt = $message->getTextContent();
                if (!empty($txt) && strlen(trim($txt)) > 10) {
                    $best_body = $txt;
                    $best_contentType = 'text/plain';
                } elseif (!empty($html) && $hasMeaningfulHtml($html)) {
                    $best_body = $html;
                    $best_contentType = 'text/html';
                }
            }
        } catch (\Throwable $t) {
            emailProcessLog('ERROR fallback MailMimeParser: ' . $t->getMessage());
        }
    }

    emailProcessLog("DEBUG getEmailBody: Tipo de contenido final seleccionado: " . $best_contentType);
    emailProcessLog("DEBUG getEmailBody: Longitud del cuerpo final seleccionado: " . strlen($best_body));
    emailProcessLog("DEBUG getEmailBody: Primeros 100 caracteres del cuerpo final seleccionado: " . substr($best_body, 0, 100));

    return [ 'body' => $best_body, 'contentType' => $best_contentType ];
}

function getAttachments($structure, $email_id, $part_number = '') {
    global $inbox;
    $attachments = array();
    
    // Mapeo de tipos MIME comunes
    $mime_type_map = [
        0 => 'text',
        1 => 'multipart',
        2 => 'message',
        3 => 'application',
        4 => 'audio',
        5 => 'image',
        6 => 'video',
        7 => 'other'
    ];
    
    if (isset($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            $current_part_number = $part_number ? $part_number . '.' . ($index + 1) : ($index + 1);
            
            // Llamada recursiva para partes anidadas
            if (isset($part->parts)) {
                $attachments = array_merge($attachments, getAttachments($part, $email_id, $current_part_number));
                continue;
            }
            
            // Verificar si es un adjunto o imagen inline
            $is_attachment = false;
            $is_inline = false;
            
            // Verificar disposición
            if (isset($part->disposition)) {
                $disposition = strtoupper($part->disposition);
                $is_attachment = ($disposition == 'ATTACHMENT');
                $is_inline = ($disposition == 'INLINE');
            }
            
            // Verificar tipo MIME
            $mime_type = '';
            if (isset($part->type) && isset($part->subtype)) {
                $type = isset($mime_type_map[$part->type]) ? $mime_type_map[$part->type] : 'application';
                $mime_type = strtolower($type . '/' . $part->subtype);
            }
            
            // Si no es un adjunto o tipo MIME válido, continuar con el siguiente
            if (!$is_attachment && !$is_inline && !in_array($mime_type, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.document',
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/bmp',
                'image/tiff',
                'image/svg+xml'
            ])) {
                continue;
            }
            
            $filename = '';
            
            // Buscar nombre del archivo en dparameters
            if (isset($part->dparameters)) {
                foreach ($part->dparameters as $param) {
                    if (strtoupper($param->attribute) == 'FILENAME') {
                        $filename = $param->value;
                        if (strpos($filename, '?=') !== false) {
                            $decoded = imap_mime_header_decode($filename);
                            if (!empty($decoded)) {
                                $filename = $decoded[0]->text;
                            }
                        }
                        break;
                    }
                }
            }
            
            // Buscar nombre del archivo en parameters si no se encontró
            if (empty($filename) && isset($part->parameters)) {
                foreach ($part->parameters as $param) {
                    if (strtoupper($param->attribute) == 'NAME') {
                        $filename = $param->value;
                        if (strpos($filename, '?=') !== false) {
                            $decoded = imap_mime_header_decode($filename);
                            if (!empty($decoded)) {
                                $filename = $decoded[0]->text;
                            }
                        }
                        break;
                    }
                }
            }
            
            // Si no hay nombre de archivo pero hay content-id, generar uno
            if (empty($filename) && isset($part->id)) {
                $content_id = trim($part->id, '<>');
                $extension = '.bin';
                
                // Determinar extensión basada en el tipo MIME
                if ($mime_type) {
                    $mime_parts = explode('/', $mime_type);
                    if (isset($mime_parts[1])) {
                        $extension = '.' . $mime_parts[1];
                        if ($extension === '.jpeg') $extension = '.jpg';
                    }
                }
                
                $filename = 'inline_' . preg_replace('/[^a-zA-Z0-9]/is', '', $content_id) . $extension;
            }
            
            // Si no se encontró un nombre de archivo válido, continuar con el siguiente
            if (empty($filename)) {
                emailProcessLog("ADVERTENCIA: No se pudo determinar el nombre del archivo para la parte " . $current_part_number);
                continue;
            }
            
            // Obtener el contenido del archivo
            $content = imap_fetchbody($inbox, $email_id, $current_part_number, FT_UID);
            if ($content === false) {
                emailProcessLog("ERROR: No se pudo obtener el contenido del adjunto: " . $filename);
                continue;
            }
            
            // Decodificar el contenido según la codificación
            $decoded_content = false;
            switch ($part->encoding) {
                case 3: // BASE64
                    $decoded_content = base64_decode($content, true);
                    if ($decoded_content === false) {
                        emailProcessLog("ERROR: Falló la decodificación BASE64 para: " . $filename);
                        continue 2; // Saltar al siguiente adjunto
                    }
                    break;
                case 4: // QUOTED-PRINTABLE
                    $decoded_content = quoted_printable_decode($content);
                    break;
                case 0: // 7BIT
                case 1: // 8BIT
                case 2: // BINARY
                    $decoded_content = $content;
                    break;
                default:
                    emailProcessLog("ADVERTENCIA: Codificación desconocida para " . $filename . ": " . $part->encoding);
                    $decoded_content = $content;
            }
            
            if ($decoded_content === false) {
                emailProcessLog("ERROR: No se pudo decodificar el contenido para: " . $filename);
                continue;
            }
            
            // Crear un directorio temporal para almacenar los adjuntos
            $temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'email_attachments_' . uniqid();
            if (!file_exists($temp_dir)) {
                if (!mkdir($temp_dir, 0755, true)) {
                    emailProcessLog("ERROR: No se pudo crear el directorio temporal: " . $temp_dir);
                    continue;
                }
            }
            
            // Generar un nombre único para el archivo
            $temp_filename = $temp_dir . DIRECTORY_SEPARATOR . $filename;
            
            // Guardar el archivo temporalmente
            if (file_put_contents($temp_filename, $decoded_content) !== false) {
                // Verificar que el archivo se guardó correctamente
                if (file_exists($temp_filename) && filesize($temp_filename) > 0) {
                    $attachments[] = array(
                        'part_number' => $current_part_number,
                        'filename' => $filename,
                        'temp_path' => $temp_filename,
                        'mime_type' => $mime_type,
                        'encoding' => $part->encoding,
                        'is_inline' => $is_inline,
                        'content_id' => isset($part->id) ? trim($part->id, '<>') : null,
                        'size' => strlen($decoded_content),
                        'temp_dir' => $temp_dir
                    );
                    
                    emailProcessLog("Archivo adjunto guardado temporalmente: " . $temp_filename . " (" . strlen($decoded_content) . " bytes)");
                } else {
                    emailProcessLog("ERROR: El archivo temporal no se guardó correctamente: " . $temp_filename);
                    if (file_exists($temp_filename)) {
                        unlink($temp_filename);
                    }
                    if (file_exists($temp_dir)) {
                        rmdir($temp_dir);
                    }
                }
            } else {
                emailProcessLog("ERROR: No se pudo guardar el archivo adjunto temporalmente: " . $filename);
                if (file_exists($temp_dir)) {
                    rmdir($temp_dir);
                }
            }
        }
    }
    
    return $attachments;
}

// Función para limpiar archivos temporales
function cleanupTempAttachments($attachments) {
    foreach ($attachments as $attachment) {
        if (isset($attachment['temp_path']) && file_exists($attachment['temp_path'])) {
            unlink($attachment['temp_path']);
        }
        if (isset($attachment['temp_dir']) && file_exists($attachment['temp_dir'])) {
            rmdir($attachment['temp_dir']);
        }
    }
}

// Función para formatear el tamaño de archivo en un formato legible
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    $bytes = max(0, (int)$bytes);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Genera un Message-ID único cuando el correo no tiene uno
 * Usa UID + fecha + remitente + empresa_id para crear un identificador único
 * 
 * @param int $imap_uid UID del correo IMAP
 * @param string $date Fecha del correo
 * @param string $from_email Email del remitente
 * @param int|null $empresa_id ID de la empresa
 * @return string Message-ID generado
 */
function generarMessageIdUnico($imap_uid, $date, $from_email, $empresa_id = null) {
    $timestamp = !empty($date) ? strtotime($date) : time();
    $date_str = date('YmdHis', $timestamp);
    $from_str = !empty($from_email) ? $from_email : 'unknown';
    $empresa_str = $empresa_id !== null ? $empresa_id : '0';
    $message_id = 'generated-' . md5($imap_uid . '-' . $date_str . '-' . $from_str . '-' . $empresa_str) . '@' . ($empresa_id !== null ? 'empresa' . $empresa_id : 'system') . '.local';
    return $message_id;
}

function verificarCorreoProcesado($imap_uid, $message_id, $date, $empresa_id = null) {
    global $conn;
    ensureDbConnection();

    try {
        // Normalizar message_id: eliminar espacios y convertir a minúsculas para comparación consistente
        if (!empty($message_id)) {
            $message_id = trim($message_id);
            $message_id = strtolower($message_id);
        }
        
        // PRIORIDAD 1: Verificar por Message-ID primero (identificador principal y único)
        // Message-ID es el identificador más confiable para evitar duplicados
        // IMPORTANTE: Solo verificar si existe para la EMPRESA ACTUAL
        // Esto permite que diferentes empresas procesen el mismo correo y creen sus propios tickets
        if (!empty($message_id)) {
            // Verificar si existe el Message-ID para la EMPRESA ACTUAL con ticket válido
            if ($empresa_id !== null) {
                $sql = "
                    SELECT tm.id, tm.ticket_id, tm.created_at, t.id_empresa
                FROM ticket_messages tm
                LEFT JOIN tickets t ON tm.ticket_id = t.id
                    WHERE tm.message_id = ? AND t.id IS NOT NULL AND t.deleted != 1 AND t.id_empresa = ?
                    LIMIT 1
                ";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    // Usar LOWER() en la comparación para hacerla case-insensitive
                    $sql_normalized = "
                        SELECT tm.id, tm.ticket_id, tm.created_at, t.id_empresa
                        FROM ticket_messages tm
                        LEFT JOIN tickets t ON tm.ticket_id = t.id
                        WHERE LOWER(TRIM(tm.message_id)) = ? AND t.id IS NOT NULL AND t.deleted != 1 AND t.id_empresa = ?
                        LIMIT 1
                    ";
                    $stmt_normalized = $conn->prepare($sql_normalized);
                    if ($stmt_normalized) {
                        $stmt_normalized->bind_param("si", $message_id, $empresa_id);
                        $stmt_normalized->execute();
                        $result = $stmt_normalized->get_result();
                        if ($result && $result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $log_msg = "Correo ya procesado anteriormente para esta empresa - Message ID: " . $message_id . ", Ticket ID: " . $row['ticket_id'] . ", Empresa ID: " . $empresa_id;
                            emailProcessLog($log_msg);
                            $result->free();
                            safeCloseStmt($stmt_normalized);
                            return true;
                        }
                        if ($result) $result->free();
                        safeCloseStmt($stmt_normalized);
                    } else {
                        // Fallback a la consulta original si falla la normalizada
                        $stmt->bind_param("si", $message_id, $empresa_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result && $result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $log_msg = "Correo ya procesado anteriormente para esta empresa - Message ID: " . $message_id . ", Ticket ID: " . $row['ticket_id'] . ", Empresa ID: " . $empresa_id;
                            emailProcessLog($log_msg);
                            $result->free();
                            safeCloseStmt($stmt);
                            return true;
                        }
                        if ($result) $result->free();
                        safeCloseStmt($stmt);
                    }
                }
            } else {
                // Si no se proporciona empresa_id, verificar si existe para CUALQUIER empresa
                $sql = "
                    SELECT tm.id, tm.ticket_id, tm.created_at, t.id_empresa
                    FROM ticket_messages tm
                    LEFT JOIN tickets t ON tm.ticket_id = t.id
                    WHERE tm.message_id = ? AND t.id IS NOT NULL AND t.deleted != 1
                    LIMIT 1
                ";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("s", $message_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result && $result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $existing_empresa_id = $row['id_empresa'] ?? null;
                        $log_msg = "Correo ya procesado anteriormente - Message ID: " . $message_id . ", Ticket ID: " . $row['ticket_id'];
                        if ($existing_empresa_id !== null) {
                            $log_msg .= ", Empresa ID: " . $existing_empresa_id;
                        }
                        emailProcessLog($log_msg);
                        $result->free();
                        safeCloseStmt($stmt);
                        return true;
                    }
                    if ($result) $result->free();
                    safeCloseStmt($stmt);
                }
            }
            
            // Si no existe con ticket válido para esta empresa, verificar si existe sin ticket válido SOLO para la empresa actual
            // IMPORTANTE: Solo eliminar mensajes huérfanos que pertenecen a la empresa actual
            // NO eliminar mensajes que pertenecen a otras empresas (permitir que otras empresas procesen el mismo correo)
            if ($empresa_id !== null) {
                // Verificar si existe un mensaje huérfano (sin ticket válido) SOLO para esta empresa específica
                // Un mensaje es huérfano si:
                // 1. No tiene ticket_id, O
                // 2. El ticket asociado está eliminado, O
                // 3. El ticket asociado pertenece a otra empresa
                // Y además NO existe un ticket válido para esta empresa
                // Usar comparación case-insensitive para message_id en la verificación de huérfanos
                $check_orphan_sql_normalized = "SELECT tm.id FROM ticket_messages tm 
                    LEFT JOIN tickets t ON tm.ticket_id = t.id
                    WHERE LOWER(TRIM(tm.message_id)) = ? 
                    AND (
                        tm.ticket_id IS NULL 
                        OR (t.id IS NOT NULL AND (t.deleted = 1 OR t.id_empresa != ?))
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM tickets t2 
                        WHERE t2.id = tm.ticket_id 
                        AND t2.deleted != 1 
                        AND t2.id_empresa = ?
                    )";
                $check_orphan_stmt = $conn->prepare($check_orphan_sql_normalized);
                if ($check_orphan_stmt) {
                    $check_orphan_stmt->bind_param("sii", $message_id, $empresa_id, $empresa_id);
                    $check_orphan_stmt->execute();
                    $orphan_result = $check_orphan_stmt->get_result();
                    if ($orphan_result && $orphan_result->num_rows > 0) {
                        // IMPORTANTE: NO eliminar mensajes huérfanos
                        // Si un mensaje ya fue procesado (aunque el ticket fue eliminado), NO debe procesarse de nuevo
                        // Esto evita crear tickets duplicados
                        emailProcessLog("ADVERTENCIA: Mensaje huérfano encontrado (Message-ID: {$message_id}) sin ticket válido para empresa {$empresa_id}, pero NO se eliminará - ya fue procesado anteriormente");
                        if ($orphan_result) $orphan_result->free();
                        safeCloseStmt($check_orphan_stmt);
                        return true; // Ya fue procesado, no procesar de nuevo
                    }
                    if ($orphan_result) $orphan_result->free();
                    safeCloseStmt($check_orphan_stmt);
                }
            }
        }

        // PRIORIDAD 2: Verificar por UID como respaldo (solo si Message-ID no existe o no se encontró)
        // Esto es útil para correos antiguos que no tienen Message-ID
        // IMPORTANTE: Verificar si el UID existe INDEPENDIENTEMENTE de si tiene ticket válido
        // Si existe el UID, significa que el correo ya fue procesado (aunque el ticket haya sido eliminado)
        // Esto evita crear tickets duplicados y errores de inserción
        if (!empty($imap_uid)) {
            // Verificar si existe el UID en ticket_messages (con o sin ticket válido)
            // Si existe, el correo ya fue procesado y NO debe procesarse de nuevo
            if ($empresa_id !== null) {
                // Primero verificar si existe con ticket válido para esta empresa
                $sql_uid_valid = "
                    SELECT tm.id, tm.ticket_id, t.id_empresa
                    FROM ticket_messages tm
                    LEFT JOIN tickets t ON tm.ticket_id = t.id
                    WHERE tm.imap_uid = ? AND t.id IS NOT NULL AND t.deleted != 1 AND t.id_empresa = ?
                    LIMIT 1
                ";
                
                $stmt_uid_valid = $conn->prepare($sql_uid_valid);
                if ($stmt_uid_valid) {
                    $stmt_uid_valid->bind_param("ii", $imap_uid, $empresa_id);
                    $stmt_uid_valid->execute();
                    $result_uid_valid = $stmt_uid_valid->get_result();
                    if ($result_uid_valid && $result_uid_valid->num_rows > 0) {
                        $row_uid = $result_uid_valid->fetch_assoc();
                        $log_msg = "Correo ya procesado anteriormente para esta empresa - UID: " . $imap_uid . ", Ticket ID: " . $row_uid['ticket_id'] . ", Empresa ID: " . $empresa_id;
                        emailProcessLog($log_msg);
                        $result_uid_valid->free();
                        safeCloseStmt($stmt_uid_valid);
                        return true;
                    }
                    $result_uid_valid->free();
                    safeCloseStmt($stmt_uid_valid);
                }
                
                // VERIFICACIÓN CRÍTICA: Verificar si el UID existe de TODAS FORMAS (sin importar el estado del ticket)
                // El imap_uid es único, así que si existe, el correo ya fue procesado
                // Luego verificamos si pertenece a esta empresa
                $sql_uid_exists = "
                    SELECT tm.id, tm.ticket_id, t.id_empresa, t.deleted
                    FROM ticket_messages tm
                    LEFT JOIN tickets t ON tm.ticket_id = t.id
                    WHERE tm.imap_uid = ?
                    LIMIT 1
                ";
                
                $stmt_uid_exists = $conn->prepare($sql_uid_exists);
                if ($stmt_uid_exists) {
                    $stmt_uid_exists->bind_param("i", $imap_uid);
                    $stmt_uid_exists->execute();
                    $result_uid_exists = $stmt_uid_exists->get_result();
                    if ($result_uid_exists && $result_uid_exists->num_rows > 0) {
                        $row_uid_exists = $result_uid_exists->fetch_assoc();
                        $existing_ticket_id = $row_uid_exists['ticket_id'] ?? null;
                        $existing_empresa_id = $row_uid_exists['id_empresa'] ?? null;
                        $ticket_deleted = $row_uid_exists['deleted'] ?? 1;
                        
                        // Liberar resultado antes de continuar
                        $result_uid_exists->free();
                        
                        // Si el UID existe, verificar si pertenece a esta empresa
                        // Si pertenece a esta empresa (con o sin ticket válido), NO procesar de nuevo
                        // Si pertenece a otra empresa, permitir que esta empresa lo procese (pero se manejará con imap_uid NULL)
                        if ($existing_empresa_id !== null && $existing_empresa_id == $empresa_id) {
                            $log_msg = "VERIFICACIÓN CRÍTICA UID: Correo UID {$imap_uid} ya existe en ticket_messages para empresa {$empresa_id}";
                            if ($existing_ticket_id) {
                                $log_msg .= " (Ticket ID: {$existing_ticket_id}, eliminado: " . ($ticket_deleted ? 'sí' : 'no') . ")";
                            } else {
                                $log_msg .= " (sin ticket asociado - mensaje huérfano)";
                            }
                            $log_msg .= " - NO procesar de nuevo para evitar duplicados";
                            emailProcessLog($log_msg);
                            safeCloseStmt($stmt_uid_exists);
                            return true; // Ya fue procesado para esta empresa, no procesar de nuevo
                        } else if ($existing_empresa_id !== null && $existing_empresa_id != $empresa_id) {
                            // El UID existe pero pertenece a otra empresa
                            // Verificar si hay un ticket válido para esta empresa antes de continuar
                            // Si hay un ticket válido para esta empresa, no procesar de nuevo
                            // Si no hay ticket válido para esta empresa, permitir que continúe (se procesará con imap_uid NULL)
                            $check_valid_ticket_sql = "
                                SELECT tm.id, tm.ticket_id, t.id_empresa
                                FROM ticket_messages tm
                                LEFT JOIN tickets t ON tm.ticket_id = t.id
                                WHERE tm.imap_uid = ? AND t.id IS NOT NULL AND t.deleted != 1 AND t.id_empresa = ?
                                LIMIT 1
                            ";
                            $check_valid_stmt = $conn->prepare($check_valid_ticket_sql);
                            if ($check_valid_stmt) {
                                $check_valid_stmt->bind_param("ii", $imap_uid, $empresa_id);
                                $check_valid_stmt->execute();
                                $check_valid_result = $check_valid_stmt->get_result();
                                if ($check_valid_result && $check_valid_result->num_rows > 0) {
                                    // Hay un ticket válido para esta empresa, no procesar de nuevo
                                    $row_valid = $check_valid_result->fetch_assoc();
                                    emailProcessLog("Correo UID {$imap_uid} ya fue procesado anteriormente para esta empresa - Ticket ID: {$row_valid['ticket_id']}, Empresa ID: {$empresa_id}");
                                    $check_valid_result->free();
                                    safeCloseStmt($check_valid_stmt);
                                    safeCloseStmt($stmt_uid_exists);
                                    return true;
                                }
                                if ($check_valid_result) $check_valid_result->free();
                                safeCloseStmt($check_valid_stmt);
                            }
                            // Si no hay ticket válido para esta empresa, el UID existe para otra empresa
                            // Permitir que esta empresa lo procese, pero se insertará con imap_uid NULL para evitar conflictos
                            // No cerrar el statement aquí, se cerrará al final del bloque
                            // Continuar con el procesamiento (no retornar, permitir que continúe)
                            // El mensaje se procesará normalmente pero con imap_uid NULL en processEmail
                        } else {
                            // El mensaje existe pero no tiene ticket asociado (mensaje huérfano)
                            // Si no tiene empresa asociada, verificar si el UID coincide con esta empresa
                            // Por seguridad, si el mensaje existe sin empresa, NO procesar de nuevo
                            $log_msg = "VERIFICACIÓN CRÍTICA UID: Correo UID {$imap_uid} existe sin empresa asociada (mensaje huérfano) - NO procesar de nuevo para evitar duplicados";
                            emailProcessLog($log_msg);
                            safeCloseStmt($stmt_uid_exists);
                            return true; // Ya existe, no procesar de nuevo
                        }
                    } else {
                        // El resultado existe pero no tiene filas, liberar recursos
                        if ($result_uid_exists) {
                            $result_uid_exists->free();
                        }
                    }
                    // Cerrar el statement al final (safeCloseStmt maneja errores si ya está cerrado)
                    safeCloseStmt($stmt_uid_exists);
                }
            } else {
                // Si no se proporciona empresa_id, verificar si existe para CUALQUIER empresa
                $sql_uid = "
                    SELECT tm.id, tm.ticket_id, t.id_empresa
            FROM ticket_messages tm
            LEFT JOIN tickets t ON tm.ticket_id = t.id
                    WHERE tm.imap_uid = ? AND t.id IS NOT NULL AND t.deleted != 1
                    LIMIT 1
                ";
                
                $stmt_uid = $conn->prepare($sql_uid);
                if ($stmt_uid) {
                    $stmt_uid->bind_param("i", $imap_uid);
                    $stmt_uid->execute();
                    $result_uid = $stmt_uid->get_result();
                    if ($result_uid && $result_uid->num_rows > 0) {
                        $row_uid = $result_uid->fetch_assoc();
                        $existing_empresa_id = $row_uid['id_empresa'] ?? null;
                        $log_msg = "Correo ya procesado anteriormente - UID: " . $imap_uid . ", Ticket ID: " . $row_uid['ticket_id'];
                        if ($existing_empresa_id !== null) {
                            $log_msg .= ", Empresa ID: " . $existing_empresa_id;
                        }
                        emailProcessLog($log_msg);
                        $result_uid->free();
                        safeCloseStmt($stmt_uid);
                        return true;
                    }
                    if ($result_uid) $result_uid->free();
                    safeCloseStmt($stmt_uid);
                }
            }
            
            // Si no existe con ticket válido para esta empresa, verificar si existe sin ticket válido SOLO para la empresa actual
            // IMPORTANTE: Esta verificación solo se ejecuta para mensajes que NO tienen empresa asociada o que pertenecen a esta empresa
            // Si el UID existe para otra empresa (con ticket válido), ya se manejó arriba y se permitió continuar o se retornó true
            if ($empresa_id !== null) {
                // Verificar si existe un mensaje huérfano (sin ticket válido) SOLO para esta empresa específica
                // Un mensaje es huérfano si:
                // 1. No tiene ticket_id, O
                // 2. El ticket asociado está eliminado, O
                // 3. El ticket asociado no tiene empresa o pertenece a esta empresa pero está eliminado
                // Y además NO existe un ticket válido para esta empresa
                // IMPORTANTE: Excluir mensajes que tienen tickets válidos para otras empresas (ya se manejaron arriba)
                $check_orphan_sql = "SELECT tm.id FROM ticket_messages tm 
                    LEFT JOIN tickets t ON tm.ticket_id = t.id
                    WHERE tm.imap_uid = ? 
                    AND (
                        tm.ticket_id IS NULL 
                        OR (t.id IS NOT NULL AND (t.deleted = 1 OR t.id_empresa IS NULL OR t.id_empresa = ?))
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM tickets t2 
                        WHERE t2.id = tm.ticket_id 
                        AND t2.deleted != 1 
                        AND t2.id_empresa = ?
                    )
                    AND (
                        t.id IS NULL 
                        OR t.id_empresa IS NULL 
                        OR t.id_empresa = ?
                        OR t.deleted = 1
                    )";
                
                $check_orphan_stmt = $conn->prepare($check_orphan_sql);
                if ($check_orphan_stmt) {
                    $check_orphan_stmt->bind_param("iiii", $imap_uid, $empresa_id, $empresa_id, $empresa_id);
                    $check_orphan_stmt->execute();
                    $orphan_result = $check_orphan_stmt->get_result();
                    if ($orphan_result && $orphan_result->num_rows > 0) {
                        // IMPORTANTE: NO eliminar mensajes huérfanos
                        // Si un mensaje ya fue procesado (aunque el ticket fue eliminado), NO debe procesarse de nuevo
                        // Esto evita crear tickets duplicados
                        emailProcessLog("ADVERTENCIA: Mensaje huérfano encontrado (UID: {$imap_uid}) sin ticket válido para empresa {$empresa_id}, pero NO se eliminará - ya fue procesado anteriormente");
                        if ($orphan_result) $orphan_result->free();
                        safeCloseStmt($check_orphan_stmt);
                        return true; // Ya fue procesado, no procesar de nuevo
                    }
                    if ($orphan_result) $orphan_result->free();
                    safeCloseStmt($check_orphan_stmt);
                }
            }
        }

        return false;

    } catch (Exception $e) {
        emailProcessLog("ERROR al verificar correo procesado: " . $e->getMessage());
        return false;
    }
}

function esRespuestaCorreo($subject) {
    // Limpiar el asunto de etiquetas de spam y espacios extra
    // Esto permite detectar respuestas incluso cuando tienen prefijos como ***SPAM***
    $subject_limpio = preg_replace('/\*\*\*SPAM\*\*\*/i', '', $subject);
    $subject_limpio = preg_replace('/\s+/', ' ', trim($subject_limpio));
    
    // Patrones comunes de respuestas en asuntos de correo
    // Buscar tanto al inicio como en cualquier parte del asunto (sin ^ para permitir prefijos)
    $patrones_respuesta = [
        '/^Re:/i',           // Al inicio (caso normal)
        '/^RE:/i',           // Al inicio mayúsculas
        '/^Re\s*:/i',        // Al inicio con espacios
        '/^RE\s*:/i',       // Al inicio mayúsculas con espacios
        '/\bRe:/i',          // En cualquier parte (después de palabra)
        '/\bRE:/i',          // En cualquier parte mayúsculas
        '/\bRe\s*:/i',       // En cualquier parte con espacios
        '/\bRE\s*:/i',      // En cualquier parte mayúsculas con espacios
        '/^Respuesta:/i',
        '/^RESPUESTA:/i',
        '/\bRespuesta:/i',
        '/\bRESPUESTA:/i',
        '/^Resp\s*:/i',
        '/^RESP\s*:/i',
        '/\bResp\s*:/i',
        '/\bRESP\s*:/i',
        '/^Fwd:/i',
        '/^FWD:/i',
        '/\bFwd:/i',
        '/\bFWD:/i',
        '/^Fw:/i',
        '/^FW:/i',
        '/\bFw:/i',
        '/\bFW:/i'
    ];
    
    // Primero verificar en el asunto original
    foreach ($patrones_respuesta as $patron) {
        if (preg_match($patron, $subject)) {
            return true;
        }
    }
    
    // Si no se encontró en el original, verificar en el asunto limpio
    if ($subject_limpio !== $subject) {
        foreach ($patrones_respuesta as $patron) {
            if (preg_match($patron, $subject_limpio)) {
                return true;
            }
        }
    }
    
    return false;
}

function buscarTicketPorAsunto($subject) {
    global $conn;
    ensureDbConnection();
    
    try {
        // Limpiar el asunto para la búsqueda
        $subject_limpio = preg_replace('/^(Re:|RE:|Respuesta:|RESPUESTA:|Fwd:|FWD:|Fw:|FW:)\s*/i', '', $subject);
        
        // Buscar tickets con asuntos similares
        $stmt = $conn->prepare("
            SELECT t.id, t.subject, COUNT(tm.id) as mensajes_count
            FROM tickets t
            LEFT JOIN ticket_messages tm ON t.id = tm.ticket_id
            WHERE t.subject LIKE ?
            GROUP BY t.id
            ORDER BY mensajes_count DESC, t.created_at DESC
            LIMIT 1
        ");
        
        $search_pattern = "%" . $conn->real_escape_string($subject_limpio) . "%";
        $stmt->bind_param("s", $search_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                emailProcessLog("Ticket encontrado por asunto similar: ID=" . $row['id'] . ", Asunto=" . $row['subject']);
                $result->free();
                safeCloseStmt($stmt);
                return $row['id'];
            }
            $result->free();
        }
        
        safeCloseStmt($stmt);
        
        return null;
        
    } catch (Exception $e) {
        emailProcessLog("ERROR al buscar ticket por asunto: " . $e->getMessage());
        return null;
    }
}

// --- INICIO: FUNCIONES DE LISTA NEGRA DE DOMINIOS ---
function isBlacklistedDomain($email) {
    global $conn;
    ensureDbConnection();
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    if (!$domain) return false;
    
    // Obtener empresa del contexto global (establecido durante el procesamiento por empresa)
    $empresa_id = $GLOBALS['current_empresa_id'] ?? null;
    
    // Verificar si el dominio está en la lista negra para esta empresa específica
    // También verificar lista negra global (id_empresa IS NULL) que aplica a todas las empresas
    if ($empresa_id !== null) {
        // Verificar dominio para esta empresa específica O dominio global
        $query = "SELECT id FROM blacklisted_domains WHERE domain = ? AND (id_empresa = ? OR id_empresa IS NULL) LIMIT 1";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("si", $domain, $empresa_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $is_blacklisted = false;
            if ($result) {
                $is_blacklisted = $result->num_rows > 0;
                $result->free();
            }
            safeCloseStmt($stmt);
            return $is_blacklisted;
        }
    } else {
        // Si no hay empresa (modo legacy o admin), verificar solo lista global
        // Los dominios específicos de empresa no aplican cuando no hay empresa establecida
        $stmt = $conn->prepare("SELECT id FROM blacklisted_domains WHERE domain = ? AND id_empresa IS NULL LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $domain);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_blacklisted = false;
        if ($result) {
            $is_blacklisted = $result->num_rows > 0;
            $result->free();
        }
        safeCloseStmt($stmt);
        return $is_blacklisted;
        }
    }
    return false;
}
// --- FIN: FUNCIONES DE LISTA NEGRA DE DOMINIOS ---

function processEmail($email_id) {
    global $inbox, $conn;
    ensureDbConnection();
    
    try {
        // Obtener la estructura del email
        $structure = imap_fetchstructure($inbox, $email_id, FT_UID);
        if (!$structure) {
            emailProcessLog("ERROR: No se pudo obtener la estructura del email ID: " . $email_id);
            return false;
        }
        
        // Obtener el encabezado completo del email
        $header_raw = imap_fetchheader($inbox, $email_id, FT_UID);
        if (!$header_raw) {
            emailProcessLog("ERROR: No se pudo obtener el encabezado del email ID: " . $email_id);
            return false;
        }
        
        // Extraer el message_id, in_reply_to y references
        $message_id = '';
        $in_reply_to = '';
        $references = '';
        $date = '';
        
        if (preg_match('/Message-ID: (.*?)(?:\r?\n(?:[ \t].*?)*)*$/m', $header_raw, $matches)) {
            $message_id = trim($matches[1], '<>');
        }
        if (preg_match('/In-Reply-To: (.*?)(?:\r?\n(?:[ \t].*?)*)*$/m', $header_raw, $matches)) {
            $in_reply_to = trim($matches[1], '<>');
        }
        if (preg_match('/References: (.*?)(?:\r?\n(?:[ \t].*?)*)*$/m', $header_raw, $matches)) {
            $references = trim($matches[1]);
        }
        if (preg_match('/Date: (.*?)(?:\r?\n(?:[ \t].*?)*)*$/m', $header_raw, $matches)) {
            $date = date('Y-m-d H:i:s', strtotime(trim($matches[1])));
        }
        
        // Extraer información del remitente ANTES de generar Message-ID si falta
        $fromParsed = parseEmailAddressFromHeader($header_raw);
        $from_email = $fromParsed['email'];
        $from_name = $fromParsed['name'];
        if (empty($from_email)) {
            emailProcessLog("ERROR: No se pudo extraer la dirección de correo del remitente para el email ID: " . $email_id . " | From raw parcial: " . substr(emailExtractHeaderField($header_raw, 'From'), 0, 180));
            return false;
        }
        if (empty($from_name)) {
            $from_name = $from_email;
        }
        
        // Obtener empresa_id del contexto global si está disponible
        $empresa_id = $GLOBALS['current_empresa_id'] ?? null;
        
        // Si Message-ID está vacío, generar uno único usando UID + fecha + remitente + empresa_id
        if (empty($message_id)) {
            $message_id = generarMessageIdUnico($email_id, $date, $from_email, $empresa_id);
            emailProcessLog("Message-ID generado para correo UID {$email_id}: {$message_id}");
        }
        
        // IMPORTANTE: Normalizar message_id ANTES de guardarlo y compararlo
        // Esto asegura consistencia en todas las comparaciones y evita duplicados
        // Normalizar: eliminar espacios y convertir a minúsculas
        if (!empty($message_id)) {
            $message_id = strtolower(trim($message_id));
        }
        
        // VERIFICACIÓN CRÍTICA INICIAL: Verificar si el imap_uid ya existe en la base de datos
        // Esta verificación es la más importante porque el imap_uid es único y si existe,
        // significa que el correo ya fue procesado, independientemente del estado del ticket
        if (!empty($email_id) && $empresa_id !== null) {
            $check_uid_critical = $conn->prepare("
                SELECT tm.id, tm.ticket_id, t.id_empresa, t.deleted
                FROM ticket_messages tm
                LEFT JOIN tickets t ON tm.ticket_id = t.id
                WHERE tm.imap_uid = ?
                LIMIT 1
            ");
            
            if ($check_uid_critical) {
                $check_uid_critical->bind_param("i", $email_id);
                $check_uid_critical->execute();
                $check_uid_critical_result = $check_uid_critical->get_result();
                
                if ($check_uid_critical_result && $check_uid_critical_result->num_rows > 0) {
                    $check_uid_critical_row = $check_uid_critical_result->fetch_assoc();
                    $existing_ticket_id = $check_uid_critical_row['ticket_id'] ?? null;
                    $existing_empresa_id = $check_uid_critical_row['id_empresa'] ?? null;
                    $ticket_deleted = $check_uid_critical_row['deleted'] ?? 1;
                    
                    // Liberar resultado antes de continuar
                    $check_uid_critical_result->free();
                    
                    // Si el UID existe para esta empresa (con o sin ticket válido), NO procesar de nuevo
                    if ($existing_empresa_id !== null && $existing_empresa_id == $empresa_id) {
                        $log_msg = "VERIFICACIÓN CRÍTICA INICIAL: Correo UID {$email_id} ya existe en ticket_messages para empresa {$empresa_id}";
                        if ($existing_ticket_id) {
                            $log_msg .= " (Ticket ID: {$existing_ticket_id}, eliminado: " . ($ticket_deleted ? 'sí' : 'no') . ")";
                        } else {
                            $log_msg .= " (sin ticket asociado - mensaje huérfano)";
                        }
                        $log_msg .= " - NO procesar de nuevo para evitar duplicados";
                        emailProcessLog($log_msg);
                        safeCloseStmt($check_uid_critical);
                        return true; // Ya existe para esta empresa, no procesar de nuevo
                    } else if ($existing_empresa_id !== null && $existing_empresa_id != $empresa_id) {
                        // El UID existe pero pertenece a otra empresa
                        // Permitir que esta empresa lo procese, pero se insertará con imap_uid NULL
                        emailProcessLog("VERIFICACIÓN CRÍTICA INICIAL: Correo UID {$email_id} existe para otra empresa ({$existing_empresa_id}), se permitirá procesar para empresa {$empresa_id} con imap_uid NULL");
                        safeCloseStmt($check_uid_critical);
                        // Continuar con el procesamiento (no retornar true)
                    } else {
                        // El mensaje existe pero no tiene empresa asociada (mensaje huérfano sin ticket)
                        // Por seguridad, si el mensaje existe sin empresa, NO procesar de nuevo
                        $log_msg = "VERIFICACIÓN CRÍTICA INICIAL: Correo UID {$email_id} existe sin empresa asociada (mensaje huérfano) - NO procesar de nuevo para evitar duplicados";
                        emailProcessLog($log_msg);
                        safeCloseStmt($check_uid_critical);
                        return true; // Ya existe, no procesar de nuevo
                    }
                } else {
                    // El resultado existe pero no tiene filas, liberar recursos
                    if ($check_uid_critical_result) {
                        $check_uid_critical_result->free();
                    }
                    safeCloseStmt($check_uid_critical);
                }
            }
        }
        
        // Verificar si el correo ya ha sido procesado usando Message-ID como identificador principal
        // Usar el message_id normalizado para la verificación
        if (verificarCorreoProcesado($email_id, $message_id, $date, $empresa_id)) {
            $log_msg = "Correo ya procesado anteriormente - Message-ID: " . $message_id . ", UID: " . $email_id;
            if ($empresa_id !== null) {
                $log_msg .= ", Empresa ID: " . $empresa_id;
            }
            emailProcessLog($log_msg);
            return true;
        }
        
        // Extraer el asunto del encabezado
        $subject = '';
        if (preg_match('/Subject: (.*?)(?:\r?\n(?:[ \t].*?)*)*$/m', $header_raw, $matches)) {
            $subject = imap_utf8(trim($matches[1]));
            // Normalizar caracteres especiales
            $subject = normalizeSpecialCharacters($subject);
        }
        
        if (empty($subject)) {
            emailProcessLog("ADVERTENCIA: No se pudo extraer el asunto del email ID: " . $email_id);
            $subject = '(Sin asunto)';
        }
        // --- VERIFICAR DOMINIO EN LISTA NEGRA ---
        if (isBlacklistedDomain($from_email)) {
            emailProcessLog("Correo ignorado por dominio en lista negra: " . $from_email);
            return false;
        }
        
        // Guardar o actualizar el solicitante
        $requester_id = saveRequester($from_name, $from_email);
        if (!$requester_id) {
            emailProcessLog("ERROR: No se pudo guardar/obtener el ID del solicitante para el email: " . $from_email);
            return false;
        }
        
        // Verificar si es una respuesta
        $es_respuesta = esRespuestaCorreo($subject);
        $ticket_id = null;
        $parent_message_id = null;
        
        // Intentar detectar ticket ID primero desde el asunto
            $ticket_id = detectTicketId($subject, $in_reply_to, $references);
        
        if ($es_respuesta) {
            emailProcessLog("Correo identificado como respuesta. Asunto: " . $subject);
            
            // Si no se encuentra por el asunto, buscar por el message_id de referencia
            if (!$ticket_id && $in_reply_to) {
                $stmt = $conn->prepare("\n                    SELECT ticket_id, id\n                    FROM ticket_messages \n                    WHERE message_id = ? \n                    LIMIT 1\n                ");
                if ($stmt) {
                    $stmt->bind_param("s", $in_reply_to);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result) {
                        if ($row = $result->fetch_assoc()) {
                            $ticket_id = $row['ticket_id'];
                            $parent_message_id = $row['id']; // ID del mensaje padre en la base de datos
                            emailProcessLog("Ticket encontrado por In-Reply-To: " . $ticket_id . ", mensaje padre: " . $parent_message_id);
                        }
                        $result->free();
                    }
                    safeCloseStmt($stmt);
                } else {
                    emailProcessLog("ERROR al preparar consulta para buscar In-Reply-To: " . $conn->error);
                }
            }
            
            // Si aún no se encuentra, buscar por referencias
            if (!$ticket_id && $references) {
                $reference_ids = array_filter(explode(' ', $references));
                foreach ($reference_ids as $ref) {
                    $stmt = $conn->prepare("\n                        SELECT ticket_id, id\n                        FROM ticket_messages \n                        WHERE message_id = ? \n                        LIMIT 1\n                    ");
                    if (!$stmt) {
                        emailProcessLog("ERROR al preparar consulta para buscar References: " . $conn->error);
                        continue;
                    }
                    $clean_reference = trim($ref, '<>');
                    $stmt->bind_param("s", $clean_reference);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result) {
                        if ($row = $result->fetch_assoc()) {
                            $ticket_id = $row['ticket_id'];
                            $parent_message_id = $row['id'];
                            emailProcessLog("Ticket encontrado por References: " . $ticket_id . ", mensaje padre: " . $parent_message_id);
                            $result->free();
                            safeCloseStmt($stmt);
                            break;
                        }
                        $result->free();
                    }
                    safeCloseStmt($stmt);
                }
            }
            
            // Si aún no se encuentra y es respuesta, buscar por asunto similar
            if (!$ticket_id) {
                $ticket_id = buscarTicketPorAsunto($subject);
                if ($ticket_id) {
                    emailProcessLog("Ticket encontrado por asunto similar: " . $ticket_id);
                }
            }
            
            emailProcessLog("Resultado final - Ticket encontrado: " . ($ticket_id ? $ticket_id : "No encontrado"));
        }
        
        emailProcessLog("ID de ticket detectado: " . ($ticket_id ? $ticket_id : "Nuevo ticket"));
        
        // Obtener el contenido completo del correo para preservar estructura original
        $raw_email_body = imap_body($inbox, $email_id, FT_UID | FT_PEEK);
        if ($raw_email_body === false) {
            $raw_email_body = imap_fetchbody($inbox, $email_id, '0', FT_UID | FT_PEEK);
            if ($raw_email_body === false) {
                $raw_email_body = '';
            }
        }
        $raw_email_source = $header_raw . "\r\n" . ($raw_email_body ?? '');

        $original_html = '';
        $original_text = '';
        $content_type_main = '';
        $headers_summary = null;

        try {
            $mimeParser = new \ZBateson\MailMimeParser\MailMimeParser();
            $parsedMessage = $mimeParser->parse($raw_email_source, false);
            $original_html = $parsedMessage->getHtmlContent() ?? '';
            $original_text = $parsedMessage->getTextContent() ?? '';
            $contentTypeHeader = $parsedMessage->getHeader('content-type');
            $content_type_main = $contentTypeHeader ? $contentTypeHeader->getValue() : '';
            $headers_summary = buildHeaderSummary($parsedMessage, $header_raw, $from_email, $from_name, $message_id, $in_reply_to, $references);
        } catch (\Throwable $e) {
            emailProcessLog("ERROR al parsear el correo con MailMimeParser: " . $e->getMessage());
        }

        if (!is_string($original_html)) {
            $original_html = '';
        }
        if (!is_string($original_text)) {
            $original_text = '';
        }

        if (!empty($original_html) && !mb_check_encoding($original_html, 'UTF-8')) {
            $original_html = mb_convert_encoding($original_html, 'UTF-8', 'auto');
        }
        if (!empty($original_text) && !mb_check_encoding($original_text, 'UTF-8')) {
            $original_text = mb_convert_encoding($original_text, 'UTF-8', 'auto');
        }

        if ($raw_email_source !== null && strlen($raw_email_source) > EMAIL_RAW_MAX_BYTES) {
            emailProcessLog('ADVERTENCIA: raw_email_source excede el límite permitido. Se truncará.');
            $raw_email_source = substr($raw_email_source, 0, EMAIL_RAW_MAX_BYTES);
        }

        if ($original_html !== null && $original_html !== '' && strlen($original_html) > EMAIL_FIELD_SOFT_LIMIT) {
            emailProcessLog('ADVERTENCIA: Contenido HTML excede el límite permitido. Se truncará.');
            $original_html = mb_strcut($original_html, 0, EMAIL_FIELD_SOFT_LIMIT, 'UTF-8');
        }

        if ($original_text !== null && $original_text !== '' && strlen($original_text) > EMAIL_FIELD_SOFT_LIMIT) {
            emailProcessLog('ADVERTENCIA: Contenido de texto excede el límite permitido. Se truncará.');
            $original_text = mb_strcut($original_text, 0, EMAIL_FIELD_SOFT_LIMIT, 'UTF-8');
        }

        if (!empty($content_type_main)) {
            $content_type_main = substr($content_type_main, 0, 255);
        }

        if (empty($content_type_main)) {
            if (!empty($original_html)) {
                $content_type_main = 'text/html';
            } elseif (!empty($original_text)) {
                $content_type_main = 'text/plain';
            } elseif (isset($structure->subtype)) {
                $content_type_main = 'text/' . strtolower($structure->subtype);
            } else {
                $content_type_main = 'text/plain';
            }
        }

        if (!$headers_summary) {
            $headers_summary = [
                'from' => [
                    'name' => $from_name,
                    'email' => $from_email
                ],
                'to' => [],
                'cc' => [],
                'bcc' => [],
                'reply_to' => [],
                'subject' => $subject,
                'date' => $date,
                'message_id' => $message_id,
                'in_reply_to' => $in_reply_to,
                'references' => $references
            ];
        }

        // Generar versión resumida en texto para búsquedas y notificaciones
        $summary_text_candidate = '';
        if (!empty($original_text)) {
            $summary_text_candidate = soloCuerpoMensaje($original_text, 'text/plain');
        }

        $summary_html_candidate = '';
        if (!empty($original_html)) {
            $summary_html_candidate = soloCuerpoMensaje($original_html, 'text/html');
        }

        if (!empty($summary_text_candidate)) {
            $body_summary = $summary_text_candidate;
        } elseif (!empty($summary_html_candidate)) {
            $body_summary = strip_tags($summary_html_candidate);
        } elseif (!empty($original_text)) {
            $body_summary = $original_text;
        } else {
            $body_summary = strip_tags($original_html);
        }

        $body_summary = preg_replace('/[\r\n]{3,}/', "\n\n", (string) $body_summary);
        $body_summary = normalizeSpecialCharacters(trim((string) $body_summary));

        if (mb_strlen($body_summary, 'UTF-8') > 65000) {
            $body_summary = mb_substr($body_summary, 0, 65000, 'UTF-8');
        }

        $headers_json = json_encode($headers_summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($headers_json === false) {
            $headers_json = null;
        }
        if ($headers_json !== null && strlen($headers_json) > EMAIL_FIELD_SOFT_LIMIT) {
            emailProcessLog('ADVERTENCIA: headers_json excede el límite permitido. Se truncará.');
            $headers_json = mb_strcut($headers_json, 0, EMAIL_FIELD_SOFT_LIMIT, 'UTF-8');
        }
        if (!empty($header_raw) && strlen($header_raw) > EMAIL_FIELD_SOFT_LIMIT) {
            emailProcessLog('ADVERTENCIA: headers_raw excede el límite permitido. Se truncará.');
            $header_raw = substr($header_raw, 0, EMAIL_FIELD_SOFT_LIMIT);
        }

        if (empty($date)) {
            $date = date('Y-m-d H:i:s');
        }
        
        // Si no hay ticket_id, crear un nuevo ticket
        $new_ticket_id = null;
        if (!$ticket_id) {
            // Obtener empresa_id del contexto global (se establece en el flujo principal)
            $empresa_id = $GLOBALS['current_empresa_id'] ?? null;
            
            // Validar que empresa_id no sea null antes de crear el ticket
            if ($empresa_id === null) {
                emailProcessLog("ERROR: No se pudo determinar la empresa para el ticket. Correo de: {$from_email}");
                return false;
            }
            
            // Incluir helper de contador de tickets
            require_once __DIR__ . '/../includes/ticket_counter_helper.php';
            
            // Obtener el siguiente número de ticket para esta empresa
            $ticket_number = getNextTicketNumber($conn, $empresa_id);
            
            $stmt = $conn->prepare("INSERT INTO tickets (subject, email_subject, description, status_id, requester_id, id_empresa, ticket_number, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?, ?, NOW(), NOW())");
            if ($stmt) {
                $stmt->bind_param("sssiis", $subject, $subject, $body_summary, $requester_id, $empresa_id, $ticket_number);
                if ($stmt->execute()) {
                    $new_ticket_id = $stmt->insert_id;
                    emailProcessLog("Nuevo ticket creado con ID: " . $new_ticket_id . ", Ticket Number: " . $ticket_number . ", status_id: 1, empresa_id: " . $empresa_id);
                    
                    // Registrar en el historial que el ticket fue creado por correo electrónico
                    $user_name = 'Sistema de Correo';
                    $description = 'Ticket creado automáticamente desde correo electrónico de: ' . $from_email;
                    logTicketHistory($conn, $new_ticket_id, 'created', $user_name, null, null, null, $description);
                } else {
                    emailProcessLog("ERROR al crear nuevo ticket: " . $stmt->error);
                    return false;
                }
                safeCloseStmt($stmt);
            } else {
                emailProcessLog("ERROR al preparar la consulta para crear nuevo ticket: " . $conn->error);
                return false;
            }
        }
        
        // Usar el ID del ticket existente o el nuevo
        $ticket_to_save = $ticket_id ? $ticket_id : $new_ticket_id;
        if (!$ticket_to_save) {
            emailProcessLog("ERROR: No se pudo determinar el ID del ticket para guardar el mensaje");
            return false;
        }
        
        // Determinar el tipo de remitente y su ID
        $sender_type = 'requester';
        $sender_id = $requester_id;
        
        // Verificar si el remitente es un técnico
        $tech_stmt = $conn->prepare("SELECT id FROM technicians WHERE email = ?");
        $tech_stmt->bind_param("s", $from_email);
        $tech_stmt->execute();
        $tech_result = $tech_stmt->get_result();
        if ($tech_result) {
            if ($tech_row = $tech_result->fetch_assoc()) {
                $sender_type = 'technician';
                $sender_id = $tech_row['id'];
                emailProcessLog("Remitente identificado como técnico: " . $from_email . " (ID: " . $sender_id . ")");
            } else {
                emailProcessLog("Remitente identificado como solicitante: " . $from_email . " (ID: " . $sender_id . ")");
            }
            $tech_result->free();
        }
        safeCloseStmt($tech_stmt);
        
        // VERIFICACIÓN ADICIONAL ANTES DE INSERTAR con bloqueo de fila para evitar condiciones de carrera
        // Esto es una capa extra de seguridad para evitar duplicados incluso si dos procesos verifican simultáneamente
        $empresa_id = $GLOBALS['current_empresa_id'] ?? null;
        
        // Iniciar transacción para asegurar atomicidad en la verificación e inserción
        $conn->begin_transaction();
        
        try {
            if (!empty($message_id) && $empresa_id !== null) {
                // Verificar con bloqueo de fila (FOR UPDATE) para evitar condiciones de carrera
                // Usar comparación directa ya que message_id ya está normalizado
                $check_msgid_stmt = $conn->prepare("
                    SELECT tm.id, tm.ticket_id, t.id_empresa 
                    FROM ticket_messages tm
                    LEFT JOIN tickets t ON tm.ticket_id = t.id
                    WHERE tm.message_id = ? AND t.id IS NOT NULL AND t.deleted != 1 AND t.id_empresa = ?
                    LIMIT 1 FOR UPDATE
                ");
                
                if ($check_msgid_stmt) {
                    $check_msgid_stmt->bind_param("si", $message_id, $empresa_id);
                    $check_msgid_stmt->execute();
                    $check_msgid_result = $check_msgid_stmt->get_result();
                    
                    if ($check_msgid_result && $check_msgid_result->num_rows > 0) {
                        $check_msgid_row = $check_msgid_result->fetch_assoc();
                        emailProcessLog("VERIFICACIÓN FINAL: Correo con Message-ID {$message_id} ya fue procesado para esta empresa {$empresa_id}, Ticket ID: {$check_msgid_row['ticket_id']}, omitiendo");
                        if ($check_msgid_result) $check_msgid_result->free();
                        safeCloseStmt($check_msgid_stmt);
                        $conn->rollback();
                        return true; // Ya fue procesado para esta empresa
                    }
                    
                    if ($check_msgid_result) $check_msgid_result->free();
                    safeCloseStmt($check_msgid_stmt);
                }
            } else if (!empty($message_id) && $empresa_id === null) {
                // Si no se proporciona empresa_id, verificar si existe para cualquier empresa
                $check_msgid_stmt = $conn->prepare("
                    SELECT tm.id, tm.ticket_id, t.id_empresa 
                    FROM ticket_messages tm
                    LEFT JOIN tickets t ON tm.ticket_id = t.id
                    WHERE tm.message_id = ? AND t.id IS NOT NULL AND t.deleted != 1
                    LIMIT 1 FOR UPDATE
                ");
                
                if ($check_msgid_stmt) {
                    $check_msgid_stmt->bind_param("s", $message_id);
                    $check_msgid_stmt->execute();
                    $check_msgid_result = $check_msgid_stmt->get_result();
                    
                    if ($check_msgid_result && $check_msgid_result->num_rows > 0) {
                        $check_msgid_row = $check_msgid_result->fetch_assoc();
                        emailProcessLog("VERIFICACIÓN FINAL: Correo con Message-ID {$message_id} ya fue procesado anteriormente, Ticket ID: {$check_msgid_row['ticket_id']}, omitiendo");
                        if ($check_msgid_result) $check_msgid_result->free();
                        safeCloseStmt($check_msgid_stmt);
                        $conn->rollback();
                        return true; // Ya fue procesado
                    }
                    
                    if ($check_msgid_result) $check_msgid_result->free();
                    safeCloseStmt($check_msgid_stmt);
                }
            }
            
            // VERIFICACIÓN CRÍTICA: Verificar si el imap_uid ya existe en la base de datos
            // Esto evita errores de duplicado incluso si el mensaje no tiene ticket válido
            // El imap_uid es único por diseño, así que si existe, el correo ya fue procesado
            if (!empty($email_id)) {
                $check_uid_exists_stmt = $conn->prepare("
                    SELECT tm.id, tm.ticket_id, t.id_empresa, t.deleted
                    FROM ticket_messages tm
                    LEFT JOIN tickets t ON tm.ticket_id = t.id
                    WHERE tm.imap_uid = ?
                    LIMIT 1 FOR UPDATE
                ");
                
                if ($check_uid_exists_stmt) {
                    $check_uid_exists_stmt->bind_param("i", $email_id);
                    $check_uid_exists_stmt->execute();
                    $check_uid_exists_result = $check_uid_exists_stmt->get_result();
                    
                    if ($check_uid_exists_result && $check_uid_exists_result->num_rows > 0) {
                        $check_uid_row = $check_uid_exists_result->fetch_assoc();
                        $existing_ticket_id = $check_uid_row['ticket_id'] ?? null;
                        $existing_empresa_id = $check_uid_row['id_empresa'] ?? null;
                        $ticket_deleted = $check_uid_row['deleted'] ?? 1;
                        
                        // El UID ya existe en la base de datos
                        // Si pertenece a esta empresa, NO procesar de nuevo
                        if ($existing_empresa_id !== null && $existing_empresa_id == $empresa_id) {
                            $log_msg = "VERIFICACIÓN CRÍTICA: Correo UID {$email_id} ya existe en ticket_messages para empresa {$empresa_id}";
                            if ($existing_ticket_id) {
                                $log_msg .= " (Ticket ID: {$existing_ticket_id}, eliminado: " . ($ticket_deleted ? 'sí' : 'no') . ")";
                            } else {
                                $log_msg .= " (sin ticket asociado)";
                            }
                            $log_msg .= " - NO procesar de nuevo para evitar duplicados";
                            emailProcessLog($log_msg);
                            $check_uid_exists_result->free();
                            safeCloseStmt($check_uid_exists_stmt);
                            $conn->rollback();
                            return true; // Ya existe, no procesar de nuevo
                        }
                        
                        // Si pertenece a otra empresa, permitir insertar con imap_uid NULL
                        // para evitar el conflicto del índice único
                        if ($existing_empresa_id !== null && $existing_empresa_id != $empresa_id) {
                            emailProcessLog("VERIFICACIÓN CRÍTICA: Correo UID {$email_id} existe para otra empresa ({$existing_empresa_id}), se insertará con imap_uid NULL");
                            $check_uid_exists_result->free();
                            safeCloseStmt($check_uid_exists_stmt);
                            // Continuar con la inserción pero usando imap_uid NULL
                            $email_id_for_insert = null;
                        } else {
                            // No debería llegar aquí si la verificación anterior funcionó correctamente
                            $check_uid_exists_result->free();
                            safeCloseStmt($check_uid_exists_stmt);
                            $email_id_for_insert = $email_id;
                        }
                    } else {
                        // El UID no existe, se puede insertar normalmente
                        $check_uid_exists_result->free();
                        safeCloseStmt($check_uid_exists_stmt);
                        $email_id_for_insert = $email_id;
                    }
                } else {
                    // Si no se pudo preparar la consulta, usar el email_id original
                    $email_id_for_insert = $email_id;
                }
            } else {
                // Si no hay email_id, usar NULL
                $email_id_for_insert = null;
            }
            
            // Insertar el mensaje en la base de datos (dentro de la transacción)
            $stmt = $conn->prepare("\n            INSERT INTO ticket_messages (\n                ticket_id, \n                sender_type,\n                sender_id,\n                sender_email, \n                sender_name, \n                subject, \n                message, \n                message_html,\n                message_text,\n                headers_raw,\n                headers_json,\n                raw_source,\n                content_type,\n                created_at, \n                message_id,\n                parent_message_id,\n                is_email,\n                imap_uid\n            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)\n        ");
            
            if ($stmt) {
            $stmt->bind_param(
                'isissssssssssssii', 
                $ticket_to_save, 
                $sender_type,
                $sender_id,
                $from_email, 
                $from_name, 
                $subject, 
                $body_summary, 
                $original_html,
                $original_text,
                $header_raw,
                $headers_json,
                $raw_email_source,
                $content_type_main,
                $date, 
                $message_id,
                $parent_message_id,
                $email_id_for_insert // UID (puede ser NULL si ya existe para otra empresa)
            );
            
            try {
            if ($stmt->execute()) {
                $current_message_id = $stmt->insert_id;
                emailProcessLog("Mensaje guardado con ID: " . $current_message_id . ", Message-ID normalizado: {$message_id}");
                
                // Confirmar transacción después de insertar exitosamente
                $conn->commit();
                
                // Si es una respuesta, actualizar solo la fecha de última actividad (mantener status_id actual)
                if ($ticket_id) {
                    $update_status = $conn->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
                    $update_status->bind_param("i", $ticket_id);
                    $update_status->execute();
                    $update_status->close();
                    
                    // Registrar en el historial que se recibió una respuesta por correo
                    $user_name = 'Sistema de Correo';
                    $description = 'Respuesta recibida por correo electrónico de: ' . $from_email;
                    logTicketHistory($conn, $ticket_id, 'updated', $user_name, null, null, null, $description);
                }
            } else {
                    // Error al ejecutar, hacer rollback de la transacción
                    $conn->rollback();
                    
                    // Verificar si es un error de duplicado en imap_uid
                    $error_code = $stmt->errno;
                    $error_message = $stmt->error;
                    
                    // Error 1062 es "Duplicate entry" en MySQL
                    // Manejar tanto idx_imap_uid como idx_imap_uid_message_id
                    if ($error_code == 1062 && (strpos($error_message, 'idx_imap_uid') !== false)) {
                        // El imap_uid o la combinación imap_uid+message_id ya existe, verificar si pertenece a esta empresa o a otra
                        $empresa_id = $GLOBALS['current_empresa_id'] ?? null;
                        
                        if ($empresa_id !== null) {
                            // Verificar si el mensaje existente pertenece a esta empresa
                            $check_stmt = $conn->prepare("
                                SELECT tm.id, tm.ticket_id, t.id_empresa 
                                FROM ticket_messages tm
                                LEFT JOIN tickets t ON tm.ticket_id = t.id
                                WHERE tm.imap_uid = ? AND t.id IS NOT NULL AND t.deleted != 1 AND t.id_empresa = ?
                            ");
                            
                            if ($check_stmt) {
                                $check_stmt->bind_param("ii", $email_id, $empresa_id);
                                $check_stmt->execute();
                                $check_result = $check_stmt->get_result();
                                
                                if ($check_result && $check_result->num_rows > 0) {
                                    // El mensaje pertenece a la misma empresa, esto no debería pasar si verificarCorreoProcesado funciona correctamente
                                    $check_row = $check_result->fetch_assoc();
                                    emailProcessLog("ADVERTENCIA: Correo UID {$email_id} ya procesado para la misma empresa {$empresa_id}, Ticket ID: {$check_row['ticket_id']}, esto no debería pasar");
                                    $check_result->free();
                                    safeCloseStmt($check_stmt);
                                    safeCloseStmt($stmt);
                                    $conn->rollback(); // Hacer rollback de la transacción
                                    return true; // Retornar true porque ya fue procesado para esta empresa
                                }
                                
                                if ($check_result) $check_result->free();
                                safeCloseStmt($check_stmt);
                                
                                // Si no pertenece a esta empresa, verificar si pertenece a otra empresa
                                // Verificar tanto por imap_uid como por la combinación imap_uid+message_id
                                $check_other_stmt = null;
                                $check_other_result = null;
                                
                                // Si el error es del índice compuesto idx_imap_uid_message_id, verificar por ambos campos
                                if (strpos($error_message, 'idx_imap_uid_message_id') !== false) {
                                    $check_other_stmt = $conn->prepare("
                                        SELECT tm.id, tm.ticket_id, t.id_empresa 
                                        FROM ticket_messages tm
                                        LEFT JOIN tickets t ON tm.ticket_id = t.id
                                        WHERE tm.imap_uid = ? AND tm.message_id = ? AND t.id IS NOT NULL AND t.deleted != 1 AND t.id_empresa != ?
                                    ");
                                    
                                    if ($check_other_stmt) {
                                        $check_other_stmt->bind_param("isi", $email_id, $message_id, $empresa_id);
                                        $check_other_stmt->execute();
                                        $check_other_result = $check_other_stmt->get_result();
                                    }
                                } else {
                                    // Si el error es solo de idx_imap_uid, verificar solo por imap_uid
                                    $check_other_stmt = $conn->prepare("
                                        SELECT tm.id, tm.ticket_id, t.id_empresa 
                                        FROM ticket_messages tm
                                        LEFT JOIN tickets t ON tm.ticket_id = t.id
                                        WHERE tm.imap_uid = ? AND t.id IS NOT NULL AND t.deleted != 1 AND t.id_empresa != ?
                                    ");
                                    
                                    if ($check_other_stmt) {
                                        $check_other_stmt->bind_param("ii", $email_id, $empresa_id);
                                        $check_other_stmt->execute();
                                        $check_other_result = $check_other_stmt->get_result();
                                    }
                                }
                                
                                if ($check_other_result && $check_other_result->num_rows > 0) {
                                    $check_other_row = $check_other_result->fetch_assoc();
                                    $other_empresa_id = $check_other_row['id_empresa'] ?? null;
                                    $index_type = strpos($error_message, 'idx_imap_uid_message_id') !== false ? 'idx_imap_uid_message_id' : 'idx_imap_uid';
                                    emailProcessLog("ADVERTENCIA: Correo UID {$email_id} con Message-ID {$message_id} ya fue procesado por otra empresa (ID: {$other_empresa_id}). Intentando insertar con imap_uid NULL para permitir múltiples empresas (índice: {$index_type}).");
                                    $check_other_result->free();
                                    safeCloseStmt($check_other_stmt);
                                    
                                    // Intentar insertar el mensaje con imap_uid = NULL para evitar el conflicto del índice único
                                    // Esto permite que cada empresa tenga su propio registro del mensaje
                                    $stmt_retry = $conn->prepare("\n            INSERT INTO ticket_messages (\n                ticket_id, \n                sender_type,\n                sender_id,\n                sender_email, \n                sender_name, \n                subject, \n                message, \n                message_html,\n                message_text,\n                headers_raw,\n                headers_json,\n                raw_source,\n                content_type,\n                created_at, \n                message_id,\n                parent_message_id,\n                is_email,\n                imap_uid\n            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL)\n        ");
                                    
                                    if ($stmt_retry) {
                                        $stmt_retry->bind_param(
                                            'isissssssssssssi', 
                                            $ticket_to_save, 
                                            $sender_type,
                                            $sender_id,
                                            $from_email, 
                                            $from_name, 
                                            $subject, 
                                            $body_summary, 
                                            $original_html,
                                            $original_text,
                                            $header_raw,
                                            $headers_json,
                                            $raw_email_source,
                                            $content_type_main,
                                            $date, 
                                            $message_id,
                                            $parent_message_id
                                        );
                                        
                                        if ($stmt_retry->execute()) {
                                            $current_message_id = $stmt_retry->insert_id;
                                            emailProcessLog("Mensaje guardado con ID: {$current_message_id} (con imap_uid NULL debido a duplicado de otra empresa)");
                                            
                                            // Confirmar transacción después de insertar exitosamente
                                            $conn->commit();
                                            
                                            // Si es una respuesta, actualizar solo la fecha de última actividad (mantener status_id actual)
                                            if ($ticket_id) {
                                                $update_status = $conn->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
                                                $update_status->bind_param("i", $ticket_id);
                                                $update_status->execute();
                                                $update_status->close();
                                                
                                                // Registrar en el historial que se recibió una respuesta por correo
                                                $user_name = 'Sistema de Correo';
                                                $description = 'Respuesta recibida por correo electrónico de: ' . $from_email;
                                                logTicketHistory($conn, $ticket_id, 'updated', $user_name, null, null, null, $description);
                                            }
                                            
                                            // Continuar con el procesamiento de adjuntos
                                            safeCloseStmt($stmt_retry);
                                            safeCloseStmt($stmt);
                                            // Saltar al procesamiento de adjuntos (no retornar aquí)
                                            goto process_attachments;
                                        } else {
                                            emailProcessLog("ERROR al insertar mensaje con imap_uid NULL: " . $stmt_retry->error);
                                            safeCloseStmt($stmt_retry);
                                            safeCloseStmt($stmt);
                                            $conn->rollback(); // Hacer rollback de la transacción
                                            return false;
            }
                                    } else {
                                        emailProcessLog("ERROR al preparar consulta para insertar con imap_uid NULL: " . $conn->error);
                                        safeCloseStmt($stmt);
                                        $conn->rollback(); // Hacer rollback de la transacción
                                        return false;
                                    }
                                }
                                
                                if ($check_other_result) $check_other_result->free();
                                safeCloseStmt($check_other_stmt);
                            }
                        }
                        
                        emailProcessLog("ERROR de duplicado al guardar mensaje (UID: {$email_id}): " . $error_message);
                        safeCloseStmt($stmt);
                        $conn->rollback(); // Hacer rollback de la transacción
                        return false;
                    } else {
                        emailProcessLog("ERROR al guardar mensaje: " . $error_message);
                        safeCloseStmt($stmt);
                        $conn->rollback(); // Hacer rollback de la transacción
                        return false;
                    }
                }
            } catch (mysqli_sql_exception $e) {
                // Capturar excepciones de MySQL (como errores de duplicado)
                $error_message = $e->getMessage();
                $error_code = $e->getCode();
                
                // Error 1062 es "Duplicate entry" en MySQL
                if ($error_code == 1062 && (strpos($error_message, 'idx_imap_uid') !== false)) {
                    // Manejar el error de duplicado igual que en el bloque else
                    $empresa_id = $GLOBALS['current_empresa_id'] ?? null;
                    
                    if ($empresa_id !== null) {
                        // Verificar si el mensaje existente pertenece a esta empresa
                        $check_stmt = $conn->prepare("
                            SELECT tm.id, tm.ticket_id, t.id_empresa 
                            FROM ticket_messages tm
                            LEFT JOIN tickets t ON tm.ticket_id = t.id
                            WHERE tm.imap_uid = ? AND t.id IS NOT NULL AND t.deleted != 1 AND t.id_empresa = ?
                        ");
                        
                        if ($check_stmt) {
                            $check_stmt->bind_param("ii", $email_id, $empresa_id);
                            $check_stmt->execute();
                            $check_result = $check_stmt->get_result();
                            
                            if ($check_result && $check_result->num_rows > 0) {
                                $check_row = $check_result->fetch_assoc();
                                emailProcessLog("ADVERTENCIA: Correo UID {$email_id} ya procesado para la misma empresa {$empresa_id}, Ticket ID: {$check_row['ticket_id']}, esto no debería pasar");
                                $check_result->free();
                                safeCloseStmt($check_stmt);
                                safeCloseStmt($stmt);
                                $conn->rollback(); // Hacer rollback de la transacción
                                return true;
                            }
                            
                            if ($check_result) $check_result->free();
                            safeCloseStmt($check_stmt);
                            
                            // Verificar si pertenece a otra empresa
                            $check_other_stmt = null;
                            $check_other_result = null;
                            
                            if (strpos($error_message, 'idx_imap_uid_message_id') !== false) {
                                $check_other_stmt = $conn->prepare("
                                    SELECT tm.id, tm.ticket_id, t.id_empresa 
                                    FROM ticket_messages tm
                                    LEFT JOIN tickets t ON tm.ticket_id = t.id
                                    WHERE tm.imap_uid = ? AND tm.message_id = ? AND t.id IS NOT NULL AND t.deleted != 1 AND t.id_empresa != ?
                                ");
                                
                                if ($check_other_stmt) {
                                    $check_other_stmt->bind_param("isi", $email_id, $message_id, $empresa_id);
                                    $check_other_stmt->execute();
                                    $check_other_result = $check_other_stmt->get_result();
                                }
                            } else {
                                $check_other_stmt = $conn->prepare("
                                    SELECT tm.id, tm.ticket_id, t.id_empresa 
                                    FROM ticket_messages tm
                                    LEFT JOIN tickets t ON tm.ticket_id = t.id
                                    WHERE tm.imap_uid = ? AND t.id IS NOT NULL AND t.deleted != 1 AND t.id_empresa != ?
                                ");
                                
                                if ($check_other_stmt) {
                                    $check_other_stmt->bind_param("ii", $email_id, $empresa_id);
                                    $check_other_stmt->execute();
                                    $check_other_result = $check_other_stmt->get_result();
                                }
                            }
                            
                            if ($check_other_result && $check_other_result->num_rows > 0) {
                                $check_other_row = $check_other_result->fetch_assoc();
                                $other_empresa_id = $check_other_row['id_empresa'] ?? null;
                                $index_type = strpos($error_message, 'idx_imap_uid_message_id') !== false ? 'idx_imap_uid_message_id' : 'idx_imap_uid';
                                emailProcessLog("ADVERTENCIA: Correo UID {$email_id} con Message-ID {$message_id} ya fue procesado por otra empresa (ID: {$other_empresa_id}). Intentando insertar con imap_uid NULL para permitir múltiples empresas (índice: {$index_type}).");
                                $check_other_result->free();
                                safeCloseStmt($check_other_stmt);
                                
                                // Intentar insertar el mensaje con imap_uid = NULL
                                $stmt_retry = $conn->prepare("\n            INSERT INTO ticket_messages (\n                ticket_id, \n                sender_type,\n                sender_id,\n                sender_email, \n                sender_name, \n                subject, \n                message, \n                message_html,\n                message_text,\n                headers_raw,\n                headers_json,\n                raw_source,\n                content_type,\n                created_at, \n                message_id,\n                parent_message_id,\n                is_email,\n                imap_uid\n            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL)\n        ");
                                
                                if ($stmt_retry) {
                                    $stmt_retry->bind_param(
                                        'isissssssssssssi', 
                                        $ticket_to_save, 
                                        $sender_type,
                                        $sender_id,
                                        $from_email, 
                                        $from_name, 
                                        $subject, 
                                        $body_summary, 
                                        $original_html,
                                        $original_text,
                                        $header_raw,
                                        $headers_json,
                                        $raw_email_source,
                                        $content_type_main,
                                        $date, 
                                        $message_id,
                                        $parent_message_id
                                    );
                                    
                                    if ($stmt_retry->execute()) {
                                        $current_message_id = $stmt_retry->insert_id;
                                        emailProcessLog("Mensaje guardado con ID: {$current_message_id} (con imap_uid NULL debido a duplicado de otra empresa)");
                                        
                                        // Confirmar transacción después de insertar exitosamente
                                        $conn->commit();
                                        
                                        if ($ticket_id) {
                                            $update_status = $conn->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
                                            $update_status->bind_param("i", $ticket_id);
                                            $update_status->execute();
                                            $update_status->close();
                                            
                                            $user_name = 'Sistema de Correo';
                                            $description = 'Respuesta recibida por correo electrónico de: ' . $from_email;
                                            logTicketHistory($conn, $ticket_id, 'updated', $user_name, null, null, null, $description);
                                        }
                                        
                                        safeCloseStmt($stmt_retry);
                                        safeCloseStmt($stmt);
                                        goto process_attachments;
                                    } else {
                                        emailProcessLog("ERROR al insertar mensaje con imap_uid NULL: " . $stmt_retry->error);
                                        safeCloseStmt($stmt_retry);
                                        safeCloseStmt($stmt);
                                        $conn->rollback(); // Hacer rollback de la transacción
                                        return false;
                                    }
                                } else {
                                    emailProcessLog("ERROR al preparar consulta para insertar con imap_uid NULL: " . $conn->error);
                                    safeCloseStmt($stmt);
                                    $conn->rollback(); // Hacer rollback de la transacción
                                    return false;
                                }
                            }
                            
                            if ($check_other_result) $check_other_result->free();
                            safeCloseStmt($check_other_stmt);
                        }
                    }
                    
                    emailProcessLog("ERROR de duplicado al guardar mensaje (UID: {$email_id}): " . $error_message);
                    safeCloseStmt($stmt);
                    $conn->rollback(); // Hacer rollback de la transacción
                    return false;
                } else {
                    // Re-lanzar la excepción si no es un error de duplicado
                    safeCloseStmt($stmt);
                    $conn->rollback(); // Hacer rollback de la transacción antes de relanzar
                    throw $e;
                }
            }
            safeCloseStmt($stmt);
        } else {
            emailProcessLog("ERROR al preparar la consulta para guardar mensaje: " . $conn->error);
            $conn->rollback(); // Hacer rollback de la transacción
            return false;
        }
        } catch (Exception $e) {
            // Manejar cualquier excepción en la transacción
            if ($conn->in_transaction) {
                $conn->rollback();
            }
            emailProcessLog("EXCEPCIÓN en transacción de inserción de mensaje: " . $e->getMessage());
            throw $e; // Relanzar para que sea capturada por el catch externo
        }
        
        process_attachments:
        // Procesar adjuntos
        $attachments = getAttachments($structure, $email_id);
        if (!empty($attachments)) {
            emailProcessLog("Procesando " . count($attachments) . " adjuntos para el mensaje ID: " . $current_message_id);
            
            foreach ($attachments as $attachment) {
                emailProcessLog("Procesando adjunto: " . ($attachment['filename'] ?? 'desconocido') . 
                         " - MIME: " . ($attachment['mime_type'] ?? 'desconocido') . 
                         " - Tamaño: " . ($attachment['size'] ?? 'desconocido'));
                
                if (!isset($attachment['temp_path']) || !file_exists($attachment['temp_path'])) {
                    emailProcessLog("ERROR: Archivo temporal no encontrado para: " . ($attachment['filename'] ?? 'desconocido'));
                    continue;
                }
                
                // Verificar que el archivo temporal tiene contenido
                $file_size = filesize($attachment['temp_path']);
                if ($file_size === false || $file_size === 0) {
                    emailProcessLog("ERROR: Archivo temporal vacío o no accesible para: " . $attachment['filename']);
                    continue;
                }
                
                emailProcessLog("Tamaño del archivo temporal: " . $file_size . " bytes");
                
                // Verificar límite de tamaño (16MB para LONGBLOB)
                if ($file_size > 16 * 1024 * 1024) {
                    emailProcessLog("ERROR: Archivo demasiado grande para LONGBLOB: " . $attachment['filename'] . " (" . $file_size . " bytes)");
                    continue;
                }
                
                try {
                    // Leer el contenido del archivo en chunks para manejar archivos grandes
                    $file_content = '';
                    $handle = fopen($attachment['temp_path'], 'rb');
                    if ($handle === false) {
                        emailProcessLog("ERROR: No se pudo abrir el archivo para lectura: " . $attachment['temp_path']);
                        continue;
                    }
                    
                    $total_read = 0;
                    while (!feof($handle)) {
                        $chunk = fread($handle, 8192); // Leer en chunks de 8KB
                        if ($chunk === false) {
                            emailProcessLog("ERROR: Error al leer el archivo: " . $attachment['temp_path']);
                            fclose($handle);
                            continue 2; // Saltar al siguiente adjunto
                        }
                        $file_content .= $chunk;
                        $total_read += strlen($chunk);
                        emailProcessLog("Leídos " . $total_read . " bytes del archivo " . $attachment['filename']);
                    }
                    fclose($handle);
                    
                    if (empty($file_content)) {
                        emailProcessLog("ERROR: Contenido del archivo vacío después de la lectura: " . $attachment['filename']);
                        continue;
                    }
                    
                    emailProcessLog("Archivo leído completamente: " . $attachment['filename'] . 
                             " - Tamaño total leído: " . strlen($file_content) . " bytes");
                    
                    // Almacenar el adjunto en la base de datos
                    $attachment_id = storeAttachment(
                        $file_content,
                        $attachment['filename'],
                        $attachment['mime_type'],
                        $ticket_to_save,
                        $current_message_id,
                        $attachment['is_inline'] ?? false,
                        $attachment['content_id'] ?? null
                    );
                    
                    if ($attachment_id) {
                        emailProcessLog("Adjunto almacenado exitosamente - ID: " . $attachment_id . 
                                 " - Nombre: " . $attachment['filename']);
                    } else {
                        emailProcessLog("ERROR: No se pudo almacenar el adjunto: " . $attachment['filename']);
                    }
                    
                } catch (Exception $e) {
                    emailProcessLog("EXCEPCIÓN al procesar adjunto " . $attachment['filename'] . ": " . 
                             $e->getMessage() . "\n" . $e->getTraceAsString());
                    continue;
                }
            }
            
            // Limpiar archivos temporales
            cleanupTempAttachments($attachments);
        } else {
            emailProcessLog("No se encontraron adjuntos en el mensaje ID: " . $current_message_id);
        }
        
        return true;
    } catch (Exception $e) {
        // Asegurar rollback de transacción en caso de excepción
        if ($conn->in_transaction) {
            $conn->rollback();
        }
        emailProcessLog("EXCEPCIÓN en processEmail: " . $e->getMessage());
        emailProcessLog("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

function conectarIMAP($imap_config = null) {
    emailProcessLog("Intentando conectar al servidor IMAP");
    
    // Si se proporciona configuración específica, usarla; si no, usar configuración global
    if ($imap_config) {
        // Construir cadena de conexión IMAP evitando duplicación
        // Si imap_options ya contiene el prefijo de encryption, usar solo options
        $encryption = $imap_config['encryption'] ?? '/imap/ssl';
        $options = $imap_config['options'] ?? '';
        
        // Si options ya contiene el prefijo de encryption, usar solo options
        if (!empty($options) && strpos($options, $encryption) === 0) {
            $imap_path = $options;
        } else {
            // Concatenar encryption + options (sin duplicar el prefijo)
            $imap_path = $encryption . $options;
        }
        
        $mailbox = "{" . $imap_config['host'] . ":" . $imap_config['port'] . $imap_path . "}" . $imap_config['folder'];
        $username = $imap_config['username'];
        $password = $imap_config['password'];
    } else {
    $mailbox = '{' . IMAP_HOST . ':' . IMAP_PORT . IMAP_ENCRYPTION . '}' . IMAP_FOLDER;
        $username = IMAP_USERNAME;
        $password = IMAP_PASSWORD;
    }
    
    emailProcessLog("Configuración IMAP: " . $mailbox);
    
    try {
        $inbox = imap_open($mailbox, $username, $password);
        if (!$inbox) {
            $error = imap_last_error();
            emailProcessLog("ERROR al conectar con el servidor IMAP: " . $error);
            throw new Exception('Error al conectar con el servidor IMAP: ' . $error);
        }
        emailProcessLog("Conexión IMAP exitosa");
        $GLOBALS['inbox'] = $inbox;
        return $inbox;
    } catch (Exception $e) {
        emailProcessLog("ERROR de conexión IMAP: " . $e->getMessage());
        throw $e;
    }
}

function enviarCorreo($to, $subject, $body, $attachments = [], $reply_to = null, $id_empresa = null) {
    global $conn;
    try {
        $empresa_config = getEmpresaEmailConfig($id_empresa, $conn);
        if (!$empresa_config) {
            throw new Exception('No hay configuración de correo activa para esta empresa.');
        }

        $mail = createMailerForEmpresa($id_empresa, $conn);
        if (!$mail) {
            throw new Exception('No se pudo inicializar el servidor de correo.');
        }
        
        // Configurar destinatario
        $mail->addAddress($to);
        
        if ($reply_to) {
            $mail->addReplyTo($reply_to);
        } else {
            $mail->addReplyTo($empresa_config['smtp_from_email'], $empresa_config['smtp_from_name'] ?? 'Sistema de Tickets');
        }
        
        // Configurar asunto y cuerpo
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        
        // Agregar adjuntos si existen
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                $mail->addAttachment($attachment);
            }
        }
        
        // Enviar el correo
        if (!$mail->send()) {
            throw new Exception("Error al enviar el correo: " . $mail->ErrorInfo);
        }
        
        emailProcessLog("Correo enviado exitosamente a: " . $to);
        return true;
    } catch (Exception $e) {
        emailProcessLog("Error al enviar correo: " . $e->getMessage());
        return false;
    }
}

function obtenerCorreos($inbox) {
    global $conn;
    ensureDbConnection();
    emailProcessLog("Iniciando función obtenerCorreos");
    $emails = [];
    
    // Verificar conexiones
    if (!$inbox) {
        emailProcessLog("ERROR: Conexión IMAP no válida");
        return $emails;
    }
    
    if (!$conn) {
        emailProcessLog("ERROR: Conexión a la base de datos no válida");
        return $emails;
    }
    
    // Procesar únicamente correos recibidos desde hoy.
    $since_date = emailImapSinceTodayDate();
    $email_uids = imap_search($inbox, 'UNSEEN SINCE "' . $since_date . '"', FT_UID);
    
    if (!$email_uids) {
        emailProcessLog("No se encontraron correos nuevos desde {$since_date} (UNSEEN). Intentando con correos leídos.");
        $email_uids = imap_search($inbox, 'SEEN SINCE "' . $since_date . '"', FT_UID);
        if (!$email_uids) {
            emailProcessLog("No se encontraron correos leídos (SEEN)");
            return $emails;
        }
        emailProcessLog("Procesando correos leídos (UIDs): " . count($email_uids));
    } else {
        emailProcessLog("Correos nuevos (UNSEEN) encontrados (UIDs): " . count($email_uids));
    }

    $parser = new \ZBateson\MailMimeParser\MailMimeParser();

    foreach ($email_uids as $email_uid) {
        emailProcessLog("Procesando correo UID: " . $email_uid);

        try {
            // Obtener el contenido completo del correo
            $raw_email_content = imap_fetchbody($inbox, $email_uid, '0', FT_UID | true);
            if ($raw_email_content === false) {
                emailProcessLog("ERROR: No se pudo obtener el contenido del correo UID: " . $email_uid);
                continue;
            }

            // Parsear el correo
            processEmail($email_uid);

            // Obtener datos para la visualización
            $subject = $parser->parse($raw_email_content, false)->getHeader('subject') ? $parser->parse($raw_email_content, false)->getHeader('subject')->getValue() : '(Sin asunto)';
            $from_header = $parser->parse($raw_email_content, false)->getHeader('from');
            $date_header = $parser->parse($raw_email_content, false)->getHeader('date');
            $date = $date_header ? date('Y-m-d H:i:s', $date_header->getDateTime()->getTimestamp()) : date('Y-m-d H:i:s');

            // Normalizar caracteres especiales en el asunto
            $subject = normalizeSpecialCharacters($subject);

            // Obtener información del remitente
            $from_email = '';
            $from_name = '';
            if ($from_header && $from_header->getAddresses()) {
                $address = $from_header->getAddresses()[0];
                $from_email = strtolower(trim((string) $address->getEmail()));
                $from_name = $address->getName() ? (string) $address->getName() : '';
                $from_name = emailDecodeMimeHeaderValue($from_name);
                $from_name = normalizeSpecialCharacters($from_name);
            }
            // Fallback / saneamiento por si el parser entrega nombre MIME como email
            if ($from_email === '' || emailLooksLikeMimeEncodedWord($from_email) || !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
                $fallback = parseEmailAddressFromHeader($raw_email_content);
                if ($fallback['email'] !== '') {
                    $from_email = $fallback['email'];
                    if ($fallback['name'] !== '') {
                        $from_name = $fallback['name'];
                    }
                }
            }
            if ($from_name === '') {
                $from_name = $from_email;
            }

            // Obtener cuerpo del mensaje
            $body_html = $parser->parse($raw_email_content, false)->getHtmlContent();
            $body_plain = $parser->parse($raw_email_content, false)->getTextContent();
            $body_content = !empty($body_html) ? $body_html : $body_plain;
            $contentType = !empty($body_html) ? 'text/html' : 'text/plain';

            // Asegurar que el cuerpo sea UTF-8 válido
            if (!mb_check_encoding($body_content, 'UTF-8')) {
                $body_content = mb_convert_encoding($body_content, 'UTF-8', 'auto');
            }

            // Limpiar el contenido para la visualización
            $cleaned_body = cleanEmailContent($body_content, $contentType);
            if ($contentType === 'text/plain') {
                $cleaned_body = soloCuerpoMensaje($cleaned_body);
            }

            // Verificar adjuntos
            $has_attachments = !empty($parser->parse($raw_email_content, false)->getAllAttachmentParts());

            // Detectar ticket ID
            $ticket_id = detectTicketId($subject);

            // Agregar a la lista de correos
            $emails[] = [
                'id' => $email_uid,
                'from' => $from_name,
                'from_email' => $from_email,
                'subject' => $subject,
                'date' => $date,
                'body' => $cleaned_body,
                'contentType' => $contentType,
                'ticket_id' => $ticket_id,
                'has_attachments' => $has_attachments
            ];

            // Marcar como leído
            if (!imap_setflag_full($inbox, $email_uid, "\\Seen", FT_UID)) {
                emailProcessLog("ERROR: No se pudo marcar el correo como leído UID: " . $email_uid);
            } else {
                emailProcessLog("Correo marcado como leído UID: " . $email_uid);
            }

        } catch (\Exception $e) {
            emailProcessLog("ERROR procesando correo UID " . $email_uid . ": " . $e->getMessage());
            continue;
        }
    }

    emailProcessLog("Procesamiento de correos completado. Total procesados: " . count($emails));
    return $emails;
}

// Procesar emails por empresa (solo si no está en modo procesador)
if (!defined('EMAIL_PROCESSOR_MODE')) {
emailProcessLog("Iniciando procesamiento de correos por empresa");

// Obtener todas las empresas con configuración de email activa
$empresas_query = "SELECT eec.*, eu.nombre as empresa_nombre 
                   FROM empresa_email_config eec
                   INNER JOIN empresas_usuarios eu ON eec.id_empresa = eu.id_empresa
                   WHERE eec.activo = 1";
$empresas_result = $conn->query($empresas_query);

$total_emails_processed = 0;

if ($empresas_result && $empresas_result->num_rows > 0) {
    // Procesar emails para cada empresa
    while ($empresa_config = $empresas_result->fetch_assoc()) {
        $empresa_id = $empresa_config['id_empresa'];
        $empresa_nombre = $empresa_config['empresa_nombre'];
        
        emailProcessLog("Procesando emails para empresa: {$empresa_nombre} (ID: {$empresa_id})");
        
        // Establecer empresa_id en contexto global para usar en processEmail
        $GLOBALS['current_empresa_id'] = $empresa_id;
        
        try {
            // Obtener configuración IMAP para esta empresa
            $imap_config = getImapConfigForEmpresa($empresa_id, $conn);
            
            if (!$imap_config) {
                emailProcessLog("ERROR: No se pudo obtener configuración IMAP para empresa {$empresa_id}");
                continue;
            }
            
            // Conectar a IMAP con configuración de la empresa
            $inbox = conectarIMAP($imap_config);
            
            // Obtener y procesar correos para esta empresa
            $emails = obtenerCorreos($inbox);
            $total_emails_processed += count($emails);
            
            emailProcessLog("Procesados " . count($emails) . " correos para empresa {$empresa_nombre}");
            
            // Cerrar conexión IMAP
            if (isset($inbox) && is_resource($inbox)) {
                imap_close($inbox);
            }
            
        } catch (Exception $e) {
            emailProcessLog("ERROR procesando emails para empresa {$empresa_id}: " . $e->getMessage());
            continue;
        }
    }
} else {
    // Si no hay empresas configuradas, usar configuración global (compatibilidad hacia atrás)
    emailProcessLog("No hay empresas con configuración de email activa. Usando configuración global.");
    $GLOBALS['current_empresa_id'] = null;
    
    try {
$inbox = conectarIMAP();
$emails = obtenerCorreos($inbox);
        $total_emails_processed = count($emails);
    } catch (Exception $e) {
        emailProcessLog("ERROR en conexión IMAP global: " . $e->getMessage());
    }
}

emailProcessLog("Procesamiento de correos completado. Total de correos procesados: " . $total_emails_processed);
} // Fin de if (!defined('EMAIL_PROCESSOR_MODE'))

function saveRequester($name, $email) {
    global $conn;
    ensureDbConnection();
    
    try {
        $email = strtolower(trim((string) $email));
        $name = normalizeSpecialCharacters((string) $name);
        $name = emailDecodeMimeHeaderValue($name);

        // Si por un bug antiguo el "email" es un encoded-word, intentar recuperar un correo real del nombre
        if ($email === '' || emailLooksLikeMimeEncodedWord($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recovered = parseEmailAddressValue(trim($name . ' <' . $email . '>'));
            if ($recovered['email'] !== '') {
                $email = $recovered['email'];
                if ($recovered['name'] !== '') {
                    $name = $recovered['name'];
                }
            }
        }

        if ($email === '' || emailLooksLikeMimeEncodedWord($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            emailProcessLog('ERROR saveRequester: email inválido o MIME mal parseado: ' . substr((string) $email, 0, 180));
            return null;
        }

        if ($name === '' || emailLooksLikeMimeEncodedWord($name)) {
            $name = $email;
        }
        
        // Obtener la empresa actual desde el contexto global
        $empresa_id = $GLOBALS['current_empresa_id'] ?? null;
        
        // Si hay una empresa actual, verificar si el email ya existe para esta empresa específica
        if ($empresa_id !== null) {
            $stmt = $conn->prepare("SELECT id FROM requesters WHERE email = ? AND id_empresa = ?");
            if (!$stmt) {
                emailProcessLog("ERROR al preparar la consulta de verificación de email: " . $conn->error);
                return null;
            }
            
            $stmt->bind_param("si", $email, $empresa_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                // El solicitante ya existe para esta empresa, retornar el ID existente
                $row = $result->fetch_assoc();
                $existing_id = $row['id'];
                $result->free();
                safeCloseStmt($stmt);
                emailProcessLog("Solicitante encontrado con email: " . $email . ", ID: " . $existing_id . ", Empresa: " . $empresa_id);
                return $existing_id;
            }
            
            if ($result) $result->free();
            safeCloseStmt($stmt);
            
            // Si no existe para esta empresa, crear un nuevo registro para esta empresa
            // Esto permite que el mismo solicitante tenga registros separados en diferentes empresas
            // Incluir helper de contador de requesters
            require_once __DIR__ . '/../includes/ticket_counter_helper.php';
            
            // Obtener el siguiente número de requester para esta empresa
            $requester_number = getNextRequesterNumber($conn, $empresa_id);
            
            if ($requester_number === null || empty($requester_number)) {
                emailProcessLog("ERROR: No se pudo obtener el número de requester para empresa " . $empresa_id);
                return null;
            }
            
            emailProcessLog("DEBUG saveRequester: Requester number obtenido: " . $requester_number . " para empresa: " . $empresa_id);
            
            $stmt = $conn->prepare("INSERT INTO requesters (name, email, id_empresa, requester_number, created_at) VALUES (?, ?, ?, ?, NOW())");
            if (!$stmt) {
                emailProcessLog("ERROR al preparar la consulta de inserción: " . $conn->error);
                return null;
            }
            
            $stmt->bind_param("ssis", $name, $email, $empresa_id, $requester_number);
            
            emailProcessLog("DEBUG saveRequester: Intentando insertar requester con número: " . $requester_number . ", empresa: " . $empresa_id);
            
            if ($stmt->execute()) {
                $requester_id = $stmt->insert_id;
                emailProcessLog("Nuevo solicitante creado para empresa " . $empresa_id . " - ID: " . $requester_id . ", Requester Number: " . $requester_number . ", Email: " . $email . ", Nombre: " . $name);
                safeCloseStmt($stmt);
                return $requester_id;
            } else {
                emailProcessLog("ERROR al insertar nuevo solicitante: " . $stmt->error);
                safeCloseStmt($stmt);
                return null;
            }
        } else {
            // Si no hay empresa actual, verificar si el email ya existe (comportamiento original)
        $stmt = $conn->prepare("SELECT id FROM requesters WHERE email = ?");
        if (!$stmt) {
            emailProcessLog("ERROR al preparar la consulta de verificación de email: " . $conn->error);
            return null;
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
            if ($result && $result->num_rows > 0) {
                // Si el email existe sin empresa, retornar el ID existente
                $row = $result->fetch_assoc();
                $existing_id = $row['id'];
                $result->free();
                safeCloseStmt($stmt);
                emailProcessLog("Solicitante encontrado con email: " . $email . ", ID: " . $existing_id);
                return $existing_id;
            }
            
            if ($result) $result->free();
        safeCloseStmt($stmt);
        
            // Si no existe, crear nuevo solicitante sin empresa
            // Incluir helper de contador de requesters
            require_once __DIR__ . '/../includes/ticket_counter_helper.php';
            
            // Obtener el siguiente número de requester para empresa global (0)
            $requester_number = getNextRequesterNumber($conn, 0);
            
        $stmt = $conn->prepare("INSERT INTO requesters (name, email, requester_number, created_at) VALUES (?, ?, ?, NOW())");
        if (!$stmt) {
            emailProcessLog("ERROR al preparar la consulta de inserción: " . $conn->error);
            return null;
        }
        
        $stmt->bind_param("sss", $name, $email, $requester_number);
        
        if ($stmt->execute()) {
            $requester_id = $stmt->insert_id;
            emailProcessLog("Nuevo solicitante creado (sin empresa) - ID: " . $requester_id . ", Requester Number: " . $requester_number . ", Email: " . $email . ", Nombre: " . $name);
            safeCloseStmt($stmt);
            return $requester_id;
        } else {
            emailProcessLog("ERROR al insertar nuevo solicitante: " . $stmt->error);
            safeCloseStmt($stmt);
            return null;
            }
        }
        
    } catch (Exception $e) {
        emailProcessLog("EXCEPCIÓN al guardar solicitante: " . $e->getMessage());
        return null;
    }
}

// Solo mostrar HTML si no está en modo procesador
if (!defined('EMAIL_PROCESSOR_MODE')) {
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correos de Outlook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .email-list {
            max-height: 800px;
            overflow-y: auto;
        }
        .email-item {
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .email-item:hover {
            background-color: #f8f9fa;
        }
        #lastUpdate {
            font-size: 0.8em;
            color: #6c757d;
            margin-bottom: 10px;
        }
        .attachment-list {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .attachment-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .attachment-item i {
            margin-right: 8px;
            color: #6c757d;
        }
        .email-content {
            font-family: inherit;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
            color: #212529;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .email-quote {
            border-left: 4px solid #dee2e6;
            margin: 10px 0;
            padding-left: 15px;
            color: #6c757d;
            font-style: italic;
        }
        .email-content img {
            max-width: 100%;
            height: auto;
        }
        .email-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .email-content table td,
        .email-content table th {
            border: 1px solid #dee2e6;
            padding: 8px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-4">Bandeja de entrada - Outlook</h2>
        <div id="lastUpdate">Última actualización: <span></span></div>
        
        <div class="row">
            <div class="col-12">
                <div class="email-list" id="emailContainer">
                    <?php if (empty($emails)): ?>
                        <div class="alert alert-info">No hay correos en la bandeja de entrada.</div>
                    <?php else: ?>
                        <?php foreach ($emails as $correo): ?>
                            <div class="card mb-3 email-item" data-email-id="<?php echo $correo['id']; ?>">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?php if ($correo['ticket_id']): ?>
                                            <span class="badge bg-primary me-2">Ticket #<?php echo htmlspecialchars($correo['ticket_id']); ?></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($correo['subject']); ?>
                                    </h5>
                                    <h6 class="card-subtitle mb-2 text-muted">
                                        De: <?php echo htmlspecialchars($correo['from']); ?>
                                        <small>(<?php echo htmlspecialchars($correo['from_email']); ?>)</small>
                                    </h6>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            Fecha: <?php echo htmlspecialchars($correo['date']); ?>
                                        </small>
                                    </p>
                                     <?php if (isset($correo['has_attachments']) && $correo['has_attachments']): ?>
                                        <div class="mt-2">
                                            <i class="fas fa-paperclip"></i> Tiene adjuntos
                                            <div class="attachment-list">
                                                <?php
                                                // Obtener los adjuntos del mensaje
                                                $stmt = $conn->prepare("
                                                    SELECT filename, file_path, mime_type, size, is_inline 
                                                    FROM ticket_attachments 
                                                    WHERE message_id = ?
                                                ");
                                                if ($stmt) {
                                                    $stmt->bind_param("i", $correo['id']);
                                                    $stmt->execute();
                                                    $result = $stmt->get_result();
                                                    
                                                    while ($adjunto = $result->fetch_assoc()) {
                                                        $icon_class = 'fa-file';
                                                        $file_extension = strtolower(pathinfo($adjunto['filename'], PATHINFO_EXTENSION));
                                                        
                                                        // Determinar el icono según el tipo de archivo
                                                        switch ($file_extension) {
                                                            case 'pdf':
                                                                $icon_class = 'fa-file-pdf';
                                                                break;
                                                            case 'doc':
                                                            case 'docx':
                                                                $icon_class = 'fa-file-word';
                                                                break;
                                                            case 'xls':
                                                            case 'xlsx':
                                                                $icon_class = 'fa-file-excel';
                                                                break;
                                                            case 'jpg':
                                                            case 'jpeg':
                                                            case 'png':
                                                            case 'gif':
                                                            case 'bmp':
                                                            case 'tiff':
                                                            case 'svg':
                                                                $icon_class = 'fa-file-image';
                                                                break;
                                                        }
                                                        
                                                        $file_size = formatFileSize($adjunto['size']);
                                                        $is_previewable = in_array($adjunto['mime_type'], [
                                                            'image/jpeg',
                                                            'image/png',
                                                            'image/gif',
                                                            'image/bmp',
                                                            'image/tiff',
                                                            'application/pdf'
                                                        ]);
                                                ?>
                                                        <div class="attachment-item">
                                                            <i class="fas <?php echo $icon_class; ?>"></i>
                                                            <span><?php echo htmlspecialchars($adjunto['filename']); ?></span>
                                                            <small class="text-muted ms-2">(<?php echo $file_size; ?>)</small>
                                                            <?php if ($is_previewable): ?>
                                                                <button class="btn btn-sm btn-link ms-2 preview-attachment" 
                                                                        data-id="<?php echo $adjunto['id']; ?>"
                                                                        data-mime="<?php echo htmlspecialchars($adjunto['mime_type']); ?>">
                                                                    <i class="fas fa-eye"></i> Vista previa
                                                                </button>
                                                            <?php endif; ?>
                                                            <a href="download_attachment.php?id=<?php echo $adjunto['id']; ?>" 
                                                               class="btn btn-sm btn-link ms-2" 
                                                               download="<?php echo htmlspecialchars($adjunto['filename']); ?>">
                                                                <i class="fas fa-download"></i> Descargar
                                                            </a>
                                                        </div>
                                                <?php
                                                    }
                                                    if ($result) {
                                                        $result->free();
                                                    }
                                                    safeCloseStmt($stmt);
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="collapse" id="email-<?php echo $correo['id']; ?>">
                                        <div class="card-text">
                                            <div class="email-content">
                                                <?php
                                                    $display_body = $correo['body'];
                                                    $display_contentType = $correo['contentType'];

                                                    if ($display_contentType === 'text/html') {
                                                        // Para contenido HTML, mostrar directamente sin htmlspecialchars
                                                        echo $display_body;
                                                    } else {
                                                        // Para texto plano, aplicar nl2br y htmlspecialchars
                                                        echo nl2br(htmlspecialchars($display_body, ENT_QUOTES, 'UTF-8'));
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button class="btn btn-sm btn-primary"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#email-<?php echo $correo['id']; ?>">
                                            Ver contenido
                                        </button>
                                        <?php if ($correo['ticket_id']): ?>
                                            <a href="../tickets/view.php?id=<?php echo htmlspecialchars($correo['ticket_id']); ?>"
                                               class="btn btn-sm btn-info">
                                                Ver ticket
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Agregar modal para vista previa -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">Vista previa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="previewContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let ultimoCorreoId = null;

        function actualizarCorreos() {
            fetch('get_emails.php')
                .then(response => response.text())
                .then(data => {
                    // Extraer el ID del primer correo de la respuesta
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data;
                    const primerCorreo = tempDiv.querySelector('.email-item');
                    
                    if (primerCorreo) {
                        const correoId = primerCorreo.getAttribute('data-email-id');
                        
                        // Si es la primera vez o hay un nuevo correo
                        if (ultimoCorreoId === null) {
                            ultimoCorreoId = correoId;
                        } else if (correoId !== ultimoCorreoId) {
                            // Si hay un nuevo correo, reiniciar la página
                            window.location.href = window.location.href;
                            return;
                        }
                    }
                    
                    document.getElementById('emailContainer').innerHTML = data;
                    document.querySelector('#lastUpdate span').textContent = new Date().toLocaleString();
                })
                .catch(error => console.error('Error:', error));
        }

        // Verificar nuevos correos cada 30 segundos
        setInterval(actualizarCorreos, 30000);
        
        // Actualizar la hora inicial y verificar correos nuevos inmediatamente
        document.addEventListener('DOMContentLoaded', function() {
            actualizarCorreos();
            document.querySelector('#lastUpdate span').textContent = new Date().toLocaleString();
        });

        // Agregar manejo de vista previa de archivos
        document.addEventListener('DOMContentLoaded', function() {
            const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
            const previewContent = document.getElementById('previewContent');
            
            document.addEventListener('click', function(e) {
                if (e.target.closest('.preview-attachment')) {
                    e.preventDefault();
                    const button = e.target.closest('.preview-attachment');
                    const attachmentId = button.dataset.id;
                    const mimeType = button.dataset.mime;
                    
                    previewContent.innerHTML = '';
                    
                    if (mimeType.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.src = `download_attachment.php?id=${attachmentId}&action=preview`;
                        img.className = 'img-fluid';
                        img.alt = 'Vista previa de imagen';
                        previewContent.appendChild(img);
                    } else if (mimeType === 'application/pdf') {
                        const iframe = document.createElement('iframe');
                        iframe.src = `download_attachment.php?id=${attachmentId}&action=preview`;
                        iframe.style.width = '100%';
                        iframe.style.height = '500px';
                        iframe.style.border = 'none';
                        previewContent.appendChild(iframe);
                    }
                    
                    previewModal.show();
                }
            });
        });
    </script>
</body>
</html>

<?php
    // Cerrar la conexión IMAP
    if (isset($inbox) && is_resource($inbox)) {
        imap_close($inbox);
    }
} // Fin de if (!defined('EMAIL_PROCESSOR_MODE')) para HTML
?>


