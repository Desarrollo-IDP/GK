<?php
header('Content-Type: text/html; charset=UTF-8');
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/ticket_display_helper.php';
require_once '../../includes/whatsapp_ticket_bridge.php';
require_once '../../includes/ticket_email_conversation_helper.php';

// Incluir PHPMailer para envío de correos
require_once '../../vendor/autoload.php';
require_once '../../conexion/config_mailer.php';
require_once '../../conexion/get_empresa_email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Fuerza la conexión a utf8mb4 para evitar problemas de caracteres
$conn->set_charset("utf8mb4");

// Función auxiliar para normalizar caracteres especiales
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

// Función para convertir charset (versión simplificada)
function convertToUtf8($content, $source_charset = null) {
    if (empty($content)) {
        return $content;
    }

    // Si el contenido ya es UTF-8 válido, no hacer conversión
    if (mb_check_encoding($content, 'UTF-8')) {
        return $content;
    }

    // Lista de charsets comunes para correos en español
    $common_charsets = [
        'UTF-8',
        'ISO-8859-1',
        'ISO-8859-15',
        'Windows-1252',
        'Windows-1251',
        'ASCII',
        'UTF-7',
        'UTF-16',
        'UTF-32'
    ];

    // Si se especifica un charset, intentarlo primero
    if ($source_charset && !in_array(strtoupper($source_charset), ['ASCII', 'BINARY', '7BIT', '8BIT'])) {
        $standardized_charset = $source_charset;
        
        // Estandarizar nombres de charset
        $charset_map = [
            'ISO88591' => 'ISO-8859-1',
            'ISO885915' => 'ISO-8859-15',
            'WINDOWS1252' => 'Windows-1252',
            'WINDOWS1251' => 'Windows-1251',
            'UTF8' => 'UTF-8',
            'UTF7' => 'UTF-7',
            'UTF16' => 'UTF-16',
            'UTF32' => 'UTF-32'
        ];
        
        $upper_charset = strtoupper($source_charset);
        if (isset($charset_map[$upper_charset])) {
            $standardized_charset = $charset_map[$upper_charset];
        }
        
        $converted = @mb_convert_encoding($content, 'UTF-8', $standardized_charset);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
    }

    // Intentar detección automática de charset
    $detected_charset = mb_detect_encoding($content, $common_charsets, true);
    if ($detected_charset !== false) {
        $converted = @mb_convert_encoding($content, 'UTF-8', $detected_charset);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
    }

    // Intentar conversión con charsets comunes uno por uno
    foreach ($common_charsets as $charset) {
        if ($charset === 'UTF-8') continue;
        
        $converted = @mb_convert_encoding($content, 'UTF-8', $charset);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
    }

    // Último recurso: usar iconv con manejo de errores
    $iconv_content = @iconv('', 'UTF-8//IGNORE', $content);
    if ($iconv_content !== false) {
        return $iconv_content;
    }

    // Si todo falla, devolver el contenido original
    return $content;
}

// Función para procesar contenido de mensaje con mejor manejo de caracteres
function processMessageContent($content) {
    if (empty($content)) {
        return $content;
    }
    // Si parece HTML, no tocar
    if (stripos($content, '<html') !== false || stripos($content, '<body') !== false || stripos($content, '<p') !== false) {
        return $content;
    }
    // Solo convierte si NO es UTF-8
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'auto');
    }
    $content = normalizeSpecialCharacters($content);
    return $content;
}

// Mejor detección de HTML
function isHtml($string) {
    return $string != strip_tags($string);
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: tickets.php');
    exit;
}

$id = $_GET['id'];
$message = '';
$messageType = '';

// Primero obtener los datos del ticket para poder usarlos en el envío de correos
$query = "SELECT t.*, 
          c.name as category_name, 
          s.name as status_name,
          cm.name as contact_mode_name,
          l.name as level_name,
          i.name as impact_name,
        --   u.name as urgency_name,
          p.name as priority_name,
          cl.name as classification_name,
          r.name as requester_name,
          r.email as requester_email,
          r.phone as requester_phone,
          e.nombre as company_name,
          eu.nombre as empresa_nombre,
          tech.name as technician_name,
          tech.email as technician_email,
          tech.phone as technician_phone
          FROM tickets t
          LEFT JOIN categories c ON t.category_id = c.id
          LEFT JOIN statuses s ON t.status_id = s.id
          LEFT JOIN contact_modes cm ON t.contact_mode_id = cm.id
          LEFT JOIN levels l ON t.level_id = l.id
          LEFT JOIN impacts i ON t.impact_id = i.id
        --   LEFT JOIN urgencies u ON t.urgency_id = u.id
          LEFT JOIN priorities p ON t.priority_id = p.id
          LEFT JOIN classifications cl ON t.classification_id = cl.id
          LEFT JOIN requesters r ON t.requester_id = r.id
          LEFT JOIN empresas e ON r.company_id = e.id_empresa
          LEFT JOIN empresas_usuarios eu ON t.id_empresa = eu.id_empresa
          LEFT JOIN technicians tech ON t.technician_id = tech.id
          WHERE t.id = ?";

// Agregar filtro de empresa
$params = [$id];
$types = 'i';
$query = addEmpresaFilter($query, 't.id_empresa', $params, $types);

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: tickets.php?error=no_access');
    exit;
}

$ticket = $result->fetch_assoc();
$stmt->close();

ticketsWhatsappEnsureSchema($conn);
$is_whatsapp_ticket = ticketsWhatsappIsTicket($conn, $ticket);
$wa_last_message_id = 0;
$email_last_message_id = 0;

// Obtener motivos de actualización del ticket
$reasons_query = "SELECT * FROM ticket_update_reasons WHERE ticket_id = ? ORDER BY created_at DESC";
$reasons_stmt = $conn->prepare($reasons_query);
$reasons_stmt->bind_param("i", $id);
$reasons_stmt->execute();
$reasons_result = $reasons_stmt->get_result();
$update_reasons = [];
while ($reason = $reasons_result->fetch_assoc()) {
    $update_reasons[] = $reason;
}
$reasons_stmt->close();

/**
 * Normaliza $_FILES para campos con name="campo[]" cuando PHP devuelve
 * estructura escalar (un solo archivo) o array (varios).
 *
 * @return array<int, array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function normalizeMultiFileUpload(array $fileField): array {
    if (!isset($fileField['name']) || $fileField['name'] === '' || $fileField['name'] === null) {
        return [];
    }
    if (!is_array($fileField['name'])) {
        return [[
            'name' => (string) $fileField['name'],
            'type' => (string) ($fileField['type'] ?? ''),
            'tmp_name' => (string) ($fileField['tmp_name'] ?? ''),
            'error' => (int) ($fileField['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($fileField['size'] ?? 0),
        ]];
    }
    $out = [];
    $n = count($fileField['name']);
    for ($i = 0; $i < $n; $i++) {
        $out[] = [
            'name' => (string) $fileField['name'][$i],
            'type' => (string) ($fileField['type'][$i] ?? ''),
            'tmp_name' => (string) ($fileField['tmp_name'][$i] ?? ''),
            'error' => (int) ($fileField['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($fileField['size'][$i] ?? 0),
        ];
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    $isAjaxReply = (
        (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    );

    $reply_message = $_POST['message'] ?? '';
    error_log("POST MESSAGE CONTENT: " . $reply_message);
    $technician_id = $_POST['technician_id'] ?? '';
    $email_attachments = [];
    
    if (empty($technician_id)) {
        $message = "Debe asignar un técnico al ticket antes de responder.";
        $messageType = "danger";
    } else {
        // Procesar múltiples correos en CC separados por coma o punto y coma
        $raw_cc = $_POST['cc_email'] ?? '';
        $cc_emails = array_filter(array_map('trim', preg_split('/[;,]+/', $raw_cc)));
        $valid_cc_emails = [];
        foreach ($cc_emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid_cc_emails[] = $email;
            }
        }

        // Agregar automáticamente correos en copia configurados por empresa
        $ticket_empresa_id = $ticket['id_empresa'] ?? null;
        if ($ticket_empresa_id !== null) {
            $auto_cc_emails = [];
            $cc_stmt = $conn->prepare("SELECT name FROM ticket_cc_emails WHERE id_empresa = ? AND is_active = 1");
            if ($cc_stmt) {
                $cc_stmt->bind_param("i", $ticket_empresa_id);
                if ($cc_stmt->execute()) {
                    $cc_result = $cc_stmt->get_result();
                    while ($cc_row = $cc_result->fetch_assoc()) {
                        $auto_email = trim($cc_row['name']);
                        if ($auto_email && filter_var($auto_email, FILTER_VALIDATE_EMAIL)) {
                            $auto_cc_emails[] = $auto_email;
                        }
                    }
                }
                $cc_stmt->close();
            }

            // Unir correos automáticos con los ingresados manualmente evitando duplicados
            foreach ($auto_cc_emails as $auto_email) {
                if (!in_array($auto_email, $valid_cc_emails, true)) {
                    $valid_cc_emails[] = $auto_email;
                }
            }
        }

        $cc_email = !empty($valid_cc_emails) ? implode(',', $valid_cc_emails) : NULL;

        $stmt = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, cc_email) VALUES (?, 'technician', ?, ?, ?)");
        $stmt->bind_param("iiss", $id, $technician_id, $reply_message, $cc_email);
        
        if ($stmt->execute()) {
            $ticket_message_id = $stmt->insert_id;

            $update_status = $conn->prepare("UPDATE tickets SET status_id = 2 WHERE id = ? AND status_id = 1");
            $update_status->bind_param("i", $id);
            $update_status->execute();
            $update_status->close();

            // Procesar archivos adjuntos de la respuesta para incluirlos en el correo saliente
            if (!empty($_FILES['reply_attachments']) && is_array($_FILES['reply_attachments'])) {
                $uploaded_reply_files = normalizeMultiFileUpload($_FILES['reply_attachments']);
                if (!empty($uploaded_reply_files)) {
                $upload_dir = '../../uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                foreach ($uploaded_reply_files as $uf) {
                    if ($uf['error'] !== UPLOAD_ERR_OK) {
                        error_log("Adjunto respuesta ticket {$id}: error de subida codigo " . $uf['error'] . " archivo " . ($uf['name'] ?? ''));
                        continue;
                    }
                    $name = basename($uf['name']);
                    $tmp_name = $uf['tmp_name'];
                    $file_type = $uf['type'];
                    $file_size = $uf['size'];

                        $year = date('Y');
                        $month = date('m');
                        $upload_path = $upload_dir . 'attachments/' . $year . '/' . $month . '/';
                        if (!file_exists($upload_path)) {
                            mkdir($upload_path, 0777, true);
                        }

                        $unique_filename = uniqid() . '_' . $name;
                        $file_path = $upload_path . $unique_filename;

                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $relative_path = 'uploads/attachments/' . $year . '/' . $month . '/' . $unique_filename;
                            $file_stmt = $conn->prepare("INSERT INTO ticket_attachments (ticket_id, message_id, ticket_message_id, filename, file_name, file_path, mime_type, size, is_inline, created_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 0, NOW())");
                            if ($file_stmt) {
                                $file_stmt->bind_param("iissssi", $id, $ticket_message_id, $name, $name, $relative_path, $file_type, $file_size);
                                if ($file_stmt->execute()) {
                                    $absolute_path = realpath(__DIR__ . '/../../' . $relative_path);
                                    if ($absolute_path !== false && file_exists($absolute_path)) {
                                        $email_attachments[] = [
                                            'path' => $absolute_path,
                                            'name' => $name
                                        ];
                                    }
                                } else {
                                    error_log("Error al guardar archivo adjunto de respuesta: " . $file_stmt->error);
                                }
                                $file_stmt->close();
                            }
                        } else {
                            error_log("Error al mover archivo adjunto de respuesta: " . $name);
                        }
                }
                }
            }
            $tech_stmt = $conn->prepare("SELECT name, email FROM technicians WHERE id = ?");
            $tech_stmt->bind_param("i", $technician_id);
            $tech_stmt->execute();
            $tech_result = $tech_stmt->get_result();
            $technician = $tech_result->fetch_assoc();
            $tech_stmt->close();
            $req_stmt = $conn->prepare("SELECT r.email, r.name, t.email_subject FROM tickets t JOIN requesters r ON t.requester_id = r.id WHERE t.id = ?");
            $req_stmt->bind_param("i", $id);
            $req_stmt->execute();
            $req_result = $req_stmt->get_result();
            $req_data = $req_result->fetch_assoc();
            $req_stmt->close();
            
            // Obtener ticket_number del ticket cargado anteriormente
            $ticket_number = $ticket['ticket_number'] ?? $id;
            $email_subject = "Re: [Ticket #$ticket_number] " . ($req_data['email_subject'] ?? 'Actualización de Ticket');
            
            // Diseño mejorado del correo con firma profesional
            $email_body = "
                <!DOCTYPE html>
                <html lang='es'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Respuesta Ticket #$ticket_number</title>
                </head>
                <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f9fa;'>
                    <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;'>
                        <!-- Header del correo -->
                        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white;'>
                            <h2 style='margin: 0; font-size: 24px; font-weight: 600;'>Sistema de Tickets</h2>
                            <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>Ticket #$ticket_number - Actualización</p>
                        </div>
                        
                        <!-- Contenido principal -->
                        <div style='padding: 40px 30px;'>
                            <p style='font-size: 18px; margin-bottom: 25px; color: #2c3e50;'>
                                Hola <strong>{$req_data['name']}</strong>,
                            </p>
                            
                            <!-- Mensaje del técnico -->
                            <div style='background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 12px; border-left: 5px solid #007bff; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
                                {$reply_message}
                            </div>
                            
                            <p style='color: #6c757d; font-size: 14px; margin-top: 30px; text-align: center;'>
                                Si tiene alguna pregunta adicional, no dude en responder a este correo.
                            </p>
                        </div>
                        

                            <div style='border-top: 2px solid #dee2e6; margin: 20px 0;'></div>
                            <div style='text-align: center; color: #6c757d; font-size: 12px;'>
                                <p style='margin: 0 0 10px 0;'>
                                    <strong>GK </strong> 
                                </p>
                                <p style='margin: 0; opacity: 0.8;'>
                                    Este es un mensaje automático del sistema de tickets. 
                                    Para continuar la conversación, responda directamente a este correo.
                                </p>
                            </div>
                        </div>
                    </div>
                </body>
                </html>";
            $ticket_empresa_id = isset($ticket['id_empresa']) ? (int)$ticket['id_empresa'] : null;
                $empresa_email_config = getEmpresaEmailConfig($ticket_empresa_id, $conn);

            if (!$empresa_email_config) {
                $message = 'Respuesta guardada pero no hay configuración de correo activa para esta empresa. Configúrela en Configuración → Email por empresa.';
                $messageType = 'warning';
                error_log('ERROR - Sin configuración de correo para empresa ' . ($ticket_empresa_id ?? 'N/A') . ' al responder ticket ID ' . $id);
            } else {
                $from_email = $empresa_email_config['smtp_from_email'];
                    $from_name = $empresa_email_config['smtp_from_name'] ?? $technician['name'];
                $reply_to = $empresa_email_config['smtp_from_email'];
            
            $email_result = sendEmail(
                $req_data['email'],
                $email_subject,
                $email_body,
                $from_email,
                $from_name,
                $reply_to,
                $cc_email,
                $ticket_empresa_id,
                $email_attachments
            );

            if ($email_result['success']) {
                    $message = 'Respuesta enviada correctamente y notificación por correo enviada.';
                    $messageType = 'success';
                    error_log('Correo de respuesta enviado exitosamente para el ticket ID: ' . $id);
            } else {
                    $message = 'Respuesta guardada pero hubo un problema al enviar el correo: ' . $email_result['message'];
                    $messageType = 'warning';
                    error_log('ERROR - Fallo al enviar correo de respuesta para ticket ID ' . $id . ': ' . $email_result['message']);
                }
            }
        } else {
            $message = "Error al guardar la respuesta: " . $stmt->error;
            $messageType = "danger";
        }
        $stmt->close();
    }

    if ($isAjaxReply) {
        header('Content-Type: application/json; charset=utf-8');
        $ok = in_array($messageType, ['success', 'warning'], true);
        echo json_encode([
            'ok' => $ok,
            'success' => ($messageType === 'success'),
            'warning' => ($messageType === 'warning'),
            'message' => $message,
            'messageType' => $messageType ?: 'danger',
            'ticket_id' => (int) $id,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$attachments_query = "SELECT *, content_id FROM ticket_attachments WHERE ticket_id = ? ORDER BY uploaded_at DESC";
$stmt = $conn->prepare($attachments_query);
$stmt->bind_param("i", $id);
$stmt->execute();
$attachments_result = $stmt->get_result();
$all_attachments = [];
while ($attachment = $attachments_result->fetch_assoc()) {
    $all_attachments[] = $attachment;
}
$stmt->close();

// Debug: Log información sobre los adjuntos
error_log("DEBUG - Ticket ID: " . $id . " - Total adjuntos encontrados: " . count($all_attachments));
foreach ($all_attachments as $idx => $attachment) {
    error_log("DEBUG - Adjunto " . ($idx + 1) . ": ID=" . $attachment['id'] . 
              ", filename=" . $attachment['filename'] . 
              ", file_path=" . $attachment['file_path'] . 
              ", ticket_message_id=" . ($attachment['ticket_message_id'] ?? 'NULL') . 
              ", message_id=" . ($attachment['message_id'] ?? 'NULL'));
}
// Obtener los costos del ticket con conteo de archivos adjuntos
$costs_query = "
    SELECT tc.*, 
           COUNT(tca.id) as attachments_count
    FROM ticket_costs tc
    LEFT JOIN ticket_cost_attachments tca ON tc.id = tca.cost_id
    WHERE tc.ticket_id = ?
    GROUP BY tc.id
    ORDER BY tc.created_at DESC
";
$stmt = $conn->prepare($costs_query);
$stmt->bind_param("i", $id);
$stmt->execute();
$costs_result = $stmt->get_result();
$ticket_costs = [];
while ($cost = $costs_result->fetch_assoc()) {
    $ticket_costs[] = $cost;
}
$stmt->close();
function processHtmlBodyWithCidImages($html_body, $ticket_id, $attachments) {
    if (empty($html_body)) {
        return '';
    }

    $processed_html = $html_body;

    foreach ($attachments as $attachment) {
        $contentId = isset($attachment['content_id']) ? trim($attachment['content_id'], '<>') : '';
        if (empty($contentId)) {
            continue;
        }

        $downloadUrl = "download_attachment.php?id=" . $attachment['id'] . "&action=preview";

        // Reemplazar coincidencias comunes de cid (respetando mayúsculas/minúsculas)
        $patterns = [
            'cid:' . $contentId,
            'CID:' . $contentId,
            'cid:&lt;' . $contentId . '&gt;',
            'CID:&lt;' . $contentId . '&gt;',
            'cid:"' . $contentId . '"',
            'CID:"' . $contentId . '"'
        ];

        foreach ($patterns as $pattern) {
            if (strpos($processed_html, $pattern) !== false) {
                $processed_html = str_replace($pattern, $downloadUrl, $processed_html);
            }
        }
    }

    return $processed_html;
}

function sendEmail($to, $subject, $body, $from_email = null, $from_name = null, $reply_to = null, $cc = null, $id_empresa = null, $attachments = []) {
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

                    if ($from_email === null) {
                        $from_email = $empresa_config['smtp_from_email'];
                    }
        if ($from_name === null) {
            $from_name = $empresa_config['smtp_from_name'] ?? 'Sistema de Tickets';
                    }
                    if ($reply_to === null) {
                        $reply_to = $empresa_config['smtp_from_email'];
        }

        $from_email = resolveSmtpFromEmail(array_merge($empresa_config, ['smtp_from_email' => $from_email]));
        $reply_to = resolveSmtpFromEmail(array_merge($empresa_config, ['smtp_from_email' => $reply_to]));

        $mail->setFrom($from_email, $from_name);
        if (!empty($mail->Username)) {
            $mail->Sender = $mail->Username;
        }
        
        // Configurar destinatario
        $mail->addAddress($to);
        
        // Configurar CC si existe (admite múltiples correos separados por coma o punto y coma o arreglo)
        if ($cc) {
            if (is_array($cc)) {
                $cc_list = $cc;
            } else {
                $cc_list = array_filter(array_map('trim', preg_split('/[;,]+/', $cc)));
            }
            foreach ($cc_list as $cc_addr) {
                if (filter_var($cc_addr, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($cc_addr);
                }
            }
        }
        
        if ($reply_to) {
            $mail->addReplyTo($reply_to, $from_name ?? 'Sistema de Tickets');
        }
        
        // Configurar asunto y cuerpo
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        // Adjuntar archivos si existen
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (!empty($attachment['path']) && file_exists($attachment['path'])) {
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? basename($attachment['path']));
                }
            }
        }
        
        // Enviar el correo
        if (!$mail->send()) {
            throw new Exception("Error al enviar el correo: " . $mail->ErrorInfo);
        }
        
        $smtpUser = $mail->Username ?? 'desconocido';
        $fromUsed = $mail->From ?? ($from_email ?? 'desconocido');
        error_log("Correo enviado exitosamente a: {$to} desde: {$fromUsed} (SMTP auth: {$smtpUser})" . ($id_empresa ? " (Empresa: {$id_empresa})" : ""));
        return [
            'success' => true,
            'message' => 'Correo enviado exitosamente'
        ];
    } catch (Exception $e) {
        error_log("ERROR - Fallo al enviar correo: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al enviar el correo: ' . $e->getMessage()
        ];
    }
}

function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'abierto':
        case 'nuevo':
            return 'primary';
        case 'en progreso':
        case 'asignado':
            return 'info';
        case 'pendiente':
            return 'warning';
        case 'resuelto':
            return 'success';
        case 'cerrado':
            return 'secondary';
        case 'cancelado':
            return 'danger';
        default:
            return 'secondary';
    }
}

?>
<?php include '../../includes/modern-header.php'; ?>
<style>
.ticket-email-composer #editor,
.reply-card #editor {
    min-height: 90px;
    max-height: 180px;
    height: 110px;
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 0 0 0.5rem 0.5rem;
    margin-bottom: 0;
}
.ticket-email-composer .ql-toolbar,
.reply-card .ql-toolbar {
    border-radius: 0.5rem 0.5rem 0 0;
    background: #f8fafc;
}
.ticket-email-composer .ql-container,
.reply-card .ql-container {
    border-radius: 0 0 0.5rem 0.5rem;
    font-size: 0.92rem;
}
.reply-attachments-box {
    border: 1px solid #d8dee6;
    border-radius: 0.9rem;
    background: #fff;
    padding: 0.95rem;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.85);
}
.reply-attachments-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.reply-attachments-dropzone {
    margin-top: 0.75rem;
    border: 1px dashed #c8d1dc;
    background: #f8fafc;
    border-radius: 0.75rem;
    padding: 0.85rem;
    color: #5c6b7a;
    font-size: 0.88rem;
}
.reply-attachments-dropzone.dragover {
    background: #e9f2ff;
    border-color: #6ea8fe;
    color: #0d6efd;
}
.reply-attachments-list {
    margin-top: 0.8rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.reply-attachment-chip {
    border: 1px solid #d6dde7;
    border-radius: 0.65rem;
    background: #f8fbff;
    padding: 0.45rem 0.55rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    max-width: 100%;
}
.reply-attachment-chip-name {
    max-width: 230px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #34495e;
}
.reply-attachment-chip-size {
    font-size: 0.75rem;
    color: #6c757d;
}
.reply-attachment-remove-btn {
    border: 0;
    background: transparent;
    color: #6c757d;
    border-radius: 999px;
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}
.reply-attachment-remove-btn:hover {
    background: #f8d7da;
    color: #b02a37;
}
.reply-attachments-empty {
    margin-top: 0.7rem;
}
.quill-editor {
    min-height: 90px;
    max-height: 180px;
    height: 110px;
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.5rem;
    margin-bottom: 0;
}
/* --- ESTILOS MODERNOS PARA MENSAJES DE CONVERSACIÓN --- */
.ticket-messages:not(.ticket-email-thread) {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 1rem;
    padding: 1.5rem;
    margin: 1rem 0;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.ticket-messages .message-container {
    margin-bottom: 2rem;
    position: relative;
    animation: fadeInUp 0.5s ease-out;
}

.ticket-messages .message {
    background: #ffffff;
    border-radius: 1.5rem;
    padding: 1.5rem 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    position: relative;
    border: 1px solid rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.ticket-messages .message:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

/* Tipos de mensaje con colores modernos */
.ticket-messages .message.email {
    border-left: 5px solid #1d4ed8;
    background: linear-gradient(135deg, #ffffff 0%, #f8faff 100%);
}

.ticket-messages .message.system {
    border-left: 5px solid #64748b;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
}

/* Header del mensaje mejorado */
.ticket-messages .message-header {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.ticket-messages .message-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
}

.ticket-messages .message-avatar.requester {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
}

.ticket-messages .message-avatar.technician {
    background: linear-gradient(135deg, #059669 0%, #0d9488 100%);
    color: white;
}

.ticket-messages .message-avatar.system {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
    color: white;
}

.ticket-messages .message-info {
    flex: 1;
    min-width: 0;
}

.ticket-messages .message-info .sender-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 0.25rem 0;
    line-height: 1.4;
}

.ticket-messages .message-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
    font-size: 0.875rem;
    color: #64748b;
}

.ticket-messages .message-date {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.ticket-messages .message-email {
    color: #1d4ed8;
    text-decoration: none;
    font-weight: 500;
}

.ticket-messages .message-email:hover {
    color: #3730a3;
    text-decoration: underline;
}

.ticket-messages .message-cc {
    background: #f1f5f9;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #475569;
}

.ticket-messages .message-badges {
    display: flex;
    gap: 0.5rem;
    margin-left: auto;
    flex-shrink: 0;
}

.ticket-messages .badge-email {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

/* Contenido del mensaje */
.ticket-messages .message-content {
    font-size: 1rem;
    line-height: 1.6;
    color: #374151;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.ticket-messages .message-content p {
    margin-bottom: 1rem;
}

.ticket-messages .message-content p:last-child {
    margin-bottom: 0;
}

/* Lista de adjuntos mejorada */
.ticket-messages .attachment-list {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
}

.ticket-messages .attachment-list h6 {
    font-size: 0.875rem;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.ticket-messages .attachment-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 1rem;
    margin-bottom: 0.75rem;
    transition: all 0.2s ease;
}

.ticket-messages .attachment-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}

/* Respuestas anidadas */
.ticket-messages .message-replies {
    margin-left: 2rem;
    margin-top: 1.5rem;
    padding-left: 1.5rem;
    border-left: 3px solid #e2e8f0;
    position: relative;
}

.ticket-messages .message-replies::before {
    content: '';
    position: absolute;
    left: -3px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(to bottom, #4f46e5, #7c3aed);
    border-radius: 1.5px;
}

/* Animaciones */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive design mejorado */
@media (max-width: 1199.98px) {
    .ticket-messages {
        padding: 1.25rem;
    }
    
    .ticket-messages .message {
        padding: 1.25rem 1.5rem;
    }
}

@media (max-width: 991.98px) {
    .ticket-messages {
        padding: 1rem;
        border-radius: 0.75rem;
    }
    
    .ticket-messages .message {
        padding: 1rem 1.25rem;
        border-radius: 1rem;
    }
    
    .ticket-messages .message-avatar {
        width: 40px;
        height: 40px;
        margin-right: 0.75rem;
    }
    
    .ticket-messages .message-info .sender-name {
        font-size: 1rem;
    }
    
    .ticket-messages .message-replies {
        margin-left: 1rem;
        padding-left: 1rem;
    }
}

@media (max-width: 767.98px) {
    .ticket-messages {
        padding: 0.75rem;
        margin: 0.5rem 0;
        border-radius: 0.5rem;
    }
    
    .ticket-messages .message {
        padding: 1rem;
        border-radius: 0.75rem;
    }
    
    .ticket-messages .message-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .ticket-messages .message-avatar {
        width: 36px;
        height: 36px;
        margin-right: 0.5rem;
    }
    
    .ticket-messages .message-info .sender-name {
        font-size: 0.95rem;
    }
    
    .ticket-messages .message-meta {
        font-size: 0.8125rem;
        gap: 0.5rem;
    }
    
    .ticket-messages .message-content {
        font-size: 0.9375rem;
    }
    
    .ticket-messages .message-replies {
        margin-left: 0.5rem;
        padding-left: 0.75rem;
    }
    
    .ticket-messages .attachment-item {
        padding: 0.75rem;
    }
}

@media (max-width: 575.98px) {
    .ticket-messages .message-header {
        flex-direction: row;
        align-items: center;
    }
    
    .ticket-messages .message-info {
        flex: 1;
        min-width: 0;
    }
    
    .ticket-messages .message-badges {
        margin-left: 0.5rem;
        margin-top: 0;
    }
    
    .ticket-messages .message-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
}

/* Estilos adicionales para mejor UX */
.ticket-messages .message-content img {
    max-width: 100%;
    height: auto;
    border-radius: 0.5rem;
    margin: 0.5rem 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.ticket-messages .message-content pre {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 1rem;
    overflow-x: auto;
    font-size: 0.875rem;
    margin: 1rem 0;
}

.ticket-messages .message-content blockquote {
    border-left: 4px solid #e2e8f0;
    margin: 1rem 0;
    padding-left: 1rem;
    color: #64748b;
    font-style: italic;
}

/* Indicadores de estado del mensaje */
.ticket-messages .message-status {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 0.75rem;
    color: #64748b;
}

/* Mejoras para el contenido de email inline */
.ticket-messages .email-inline-image {
    max-width: 100%;
    height: auto;
    border-radius: 0.5rem;
    margin: 0.5rem 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Estilos para enlaces dentro de mensajes */
.ticket-messages .message-content a {
    color: #1d4ed8;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
}

.ticket-messages .message-content a:hover {
    color: #3730a3;
    text-decoration: underline;
}

/* Scroll suave para la sección de conversación */
.ticket-messages {
    scroll-behavior: smooth;
}

/* Indicador de carga para mensajes */
.message-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: #64748b;
    font-size: 0.875rem;
}

.message-loading::after {
    content: '';
    width: 20px;
    height: 20px;
    border: 2px solid #e2e8f0;
    border-top: 2px solid #1d4ed8;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 0.5rem;
}

@keyframes ticket-email-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.35; }
}

.ticket-email-live {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    color: #86efac;
}

.ticket-email-live.is-error {
    color: #fde68a;
}

.ticket-email-live .ticket-email-pulse {
    font-size: 0.55rem;
    animation: ticket-email-pulse 1.5s ease-in-out infinite;
}

.ticket-messages.is-updating {
    opacity: 0.92;
    transition: opacity 0.2s ease;
}


/* Estilos para modales - evitar superposición con header */
.modal {
    z-index: 1050 !important;
}
.modal-backdrop {
    z-index: 1040 !important;
}

/* Asegurar que el header tenga un z-index apropiado */
header, .navbar, .modern-header {
    z-index: 1030 !important;
}

/* Mejorar apariencia de modales */
.modal-content {
    border: none;
    border-radius: 1rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.modal-header {
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 1rem 1rem 0 0;
}

.modal-title {
    font-weight: 600;
    color: #2c3e50;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid rgba(0,0,0,0.05);
    padding: 1rem 1.5rem;
    background: #f8f9fa;
    border-radius: 0 0 1rem 1rem;
}
</style>
<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-2">Detalle del Ticket #<?php echo $ticket['ticket_number']; ?></h1>
                    <p class="text-muted mb-0">Consulta toda la información y seguimiento del ticket</p>
                </div>
                <div class="tickets-page-actions">
                    <a href="tickets.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                    <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-edit me-2"></i>Editar
                    </a>
                </div>
            </div>

                <div class="ticket-overview mb-4">
                    <div class="ticket-overview-card">
                        <div class="ticket-overview-header">
                            <div>
                                <!--<p class="ticket-overview-kicker">Ticket #<?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></p>-->
                                <h5 class="ticket-overview-title mb-0">Información general</h5>
                            </div>
                            <span class="ticket-status-pill ticket-status-pill--<?php echo ticketGetStatusColorClass($ticket['status_name'] ?? '', ticketIsClosedByInactivity($ticket)); ?>">
                                <?php if (ticketIsClosedByInactivity($ticket)): ?>
                                    <i class="fas fa-clock"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars(ticketGetDisplayStatusNameFromRow($ticket)); ?>
                        </span>
                    </div>
                        <div class="ticket-meta-grid">
                            <div class="ticket-meta-item">
                                <span class="ticket-meta-icon"><i class="fas fa-calendar-alt"></i></span>
                                <div>
                                    <span class="ticket-meta-label">Fecha de creación</span>
                                    <span class="ticket-meta-value"><?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="ticket-meta-item">
                                <span class="ticket-meta-icon"><i class="fas fa-tag"></i></span>
                                <div>
                                    <span class="ticket-meta-label">Categoría</span>
                                    <span class="ticket-meta-value"><?php echo htmlspecialchars($ticket['category_name'] ?? 'No asignada'); ?></span>
                                </div>
                            </div>
                            <div class="ticket-meta-item">
                                <span class="ticket-meta-icon"><i class="fas fa-comments"></i></span>
                                <div>
                                    <span class="ticket-meta-label">Modo de contacto</span>
                                    <span class="ticket-meta-value"><?php echo htmlspecialchars($ticket['contact_mode_name'] ?? 'No especificado'); ?></span>
                                </div>
                            </div>
                            <div class="ticket-meta-item">
                                <span class="ticket-meta-icon"><i class="fab fa-whatsapp"></i></span>
                                <div>
                                    <span class="ticket-meta-label">Conversación WhatsApp</span>
                                    <span class="ticket-meta-value">
                                        <?php if (!empty($ticket['whatsapp_enabled'])): ?>
                                            <span class="badge bg-success">Habilitada</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Deshabilitada</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="ticket-meta-item">
                                <span class="ticket-meta-icon"><i class="fas fa-layer-group"></i></span>
                                <div>
                                    <span class="ticket-meta-label">Clasificación</span>
                                    <span class="ticket-meta-value"><?php echo htmlspecialchars($ticket['classification_name'] ?? 'No especificada'); ?></span>
                                </div>
                            </div>
                            <div class="ticket-meta-item">
                                <span class="ticket-meta-icon"><i class="fas fa-signal"></i></span>
                                <div>
                                    <span class="ticket-meta-label">Nivel</span>
                                    <span class="ticket-meta-value"><?php echo htmlspecialchars($ticket['level_name'] ?? 'No especificado'); ?></span>
                                </div>
                            </div>
                            <div class="ticket-meta-item">
                                <span class="ticket-meta-icon"><i class="fas fa-bolt"></i></span>
                                <div>
                                    <span class="ticket-meta-label">Impacto</span>
                                    <span class="ticket-meta-value"><?php echo htmlspecialchars($ticket['impact_name'] ?? 'No especificado'); ?></span>
                                </div>
                            </div>
                            <div class="ticket-meta-item">
                                <span class="ticket-meta-icon"><i class="fas fa-flag"></i></span>
                                <div>
                                    <span class="ticket-meta-label">Prioridad</span>
                                    <span class="ticket-meta-value"><?php echo htmlspecialchars($ticket['priority_name'] ?? 'No especificada'); ?></span>
                                </div>
                            </div>
                            <?php if (!empty($ticket['closed_at'])): ?>
                            <div class="ticket-meta-item">
                                <span class="ticket-meta-icon"><i class="fas fa-lock"></i></span>
                                <div>
                                    <span class="ticket-meta-label">Fecha de cierre</span>
                                    <span class="ticket-meta-value"><?php echo date('d/m/Y H:i', strtotime($ticket['closed_at'])); ?></span>
                                </div>
                            </div>
                                    <?php endif; ?>
                            </div>
                    </div>

                    <div class="ticket-people-grid">
                        <div class="ticket-person-card">
                            <div class="ticket-person-header">
                                <span class="ticket-person-avatar ticket-person-avatar--requester">
                                    <i class="fas fa-user"></i>
                                </span>
                                <div>
                                    <p class="ticket-person-role mb-0">Solicitante</p>
                                    <h6 class="ticket-person-name mb-0"><?php echo htmlspecialchars(normalizeSpecialCharacters($ticket['requester_name'] ?? 'No asignado'), ENT_QUOTES, 'UTF-8'); ?></h6>
                            </div>
                        </div>
                            <div class="ticket-person-body">
                                <div class="ticket-person-row">
                                    <span class="ticket-person-label"><i class="fas fa-envelope me-2"></i>Email</span>
                                    <span class="ticket-person-value">
                                        <?php if (!empty($ticket['requester_email'])): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($ticket['requester_email']); ?>"><?php echo htmlspecialchars($ticket['requester_email']); ?></a>
                                        <?php else: ?>
                                            No disponible
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="ticket-person-row">
                                    <span class="ticket-person-label"><i class="fas fa-phone me-2"></i>Teléfono</span>
                                    <span class="ticket-person-value"><?php echo htmlspecialchars($ticket['requester_phone'] ?? 'No disponible'); ?></span>
                                </div>
                                <div class="ticket-person-row">
                                    <span class="ticket-person-label"><i class="fas fa-building me-2"></i>Empresa</span>
                                    <span class="ticket-person-value"><?php echo htmlspecialchars(normalizeSpecialCharacters($ticket['company_name'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                    </div>
                </div>

                        <div class="ticket-person-card">
                            <div class="ticket-person-header">
                                <span class="ticket-person-avatar ticket-person-avatar--technician">
                                    <i class="fas fa-headset"></i>
                                </span>
                                <div>
                                    <p class="ticket-person-role mb-0">Técnico asignado</p>
                                    <h6 class="ticket-person-name mb-0">
                                        <?php echo !empty($ticket['technician_id'])
                                            ? htmlspecialchars(normalizeSpecialCharacters($ticket['technician_name'] ?? ''), ENT_QUOTES, 'UTF-8')
                                            : 'Sin asignar'; ?>
                                    </h6>
                            </div>
                            </div>
                            <div class="ticket-person-body">
                                <?php if (!empty($ticket['technician_id'])): ?>
                                    <div class="ticket-person-row">
                                        <span class="ticket-person-label"><i class="fas fa-envelope me-2"></i>Email</span>
                                        <span class="ticket-person-value">
                                            <?php if (!empty($ticket['technician_email'])): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($ticket['technician_email']); ?>"><?php echo htmlspecialchars($ticket['technician_email']); ?></a>
                                            <?php else: ?>
                                                No disponible
                                            <?php endif; ?>
                                        </span>
                        </div>
                                    <div class="ticket-person-row">
                                        <span class="ticket-person-label"><i class="fas fa-phone me-2"></i>Teléfono</span>
                                        <span class="ticket-person-value"><?php echo htmlspecialchars($ticket['technician_phone'] ?? 'No disponible'); ?></span>
                    </div>
                                <?php else: ?>
                                    <div class="ticket-empty-tech">
                                        <i class="fas fa-user-clock"></i>
                                        <p class="mb-1">Todavía no hay técnico asignado</p>
                                        <a href="edit.php?id=<?php echo (int) $id; ?>" class="btn btn-sm btn-outline-primary">
                                            Asignar técnico
                                        </a>
                            </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Descripción del Problema</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Siempre mostrar la descripción del ticket desde la tabla tickets
                            ?>
                             <div class="message-container requester-message mb-3">
                                <div class="message-header d-flex align-items-center">
                                     <div class="me-2"><i class="fas fa-user-circle text-primary"></i></div>
                                    <div>
                                        <strong><?php echo htmlspecialchars(normalizeSpecialCharacters($ticket['requester_name'] ?? 'Solicitante'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></small>
                                    </div>
                                </div>
                                <div class="message-body p-3 bg-light rounded mt-1" style="border-radius: 10px;">
                                    <?php 
                                    $desc_content = $ticket['description'];
                                    if (!empty($desc_content) && isHtml($desc_content)) {
                                        echo processHtmlBodyWithCidImages($desc_content, $id, $all_attachments);
                                    } else {
                                        echo nl2br(htmlspecialchars($desc_content ?? '', ENT_QUOTES, 'UTF-8'));
                                    }
                                    ?>
                                </div>
                            
                                <?php
                            // Mostrar adjuntos generales del ticket (no asociados a mensajes específicos)
                            $general_attachments = array_filter($all_attachments, function($attachment) {
                                return (is_null($attachment['message_id']) && is_null($attachment['ticket_message_id']));
                            });
                            
                            if (!empty($general_attachments)):
                                ?>
                                    <div class="attachment-list mt-2">
                                        <h6>Adjuntos:</h6>
                                        <div class="row">
                                        <?php foreach ($general_attachments as $attachment): 
                                                $file_extension = strtolower(pathinfo($attachment['filename'], PATHINFO_EXTENSION));
                                                $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
                                                $is_pdf = $file_extension === 'pdf';
                                                $is_doc = in_array($file_extension, ['doc', 'docx']);
                                                $is_xls = in_array($file_extension, ['xls', 'xlsx']);
                                            ?>
                                                <div class="col-md-3 mb-2">
                                                    <?php if ($is_image): ?>
                                                        <div class="card">
                                                            <img src="<?php echo htmlspecialchars('download_attachment.php?id=' . $attachment['id']); ?>" 
                                                                 class="card-img-top" 
                                                                 alt="<?php echo htmlspecialchars($attachment['filename']); ?>"
                                                                 style="height: 150px; object-fit: cover;">
                                                            <div class="card-body p-2">
                                                                <p class="card-text small text-truncate">
                                                                    <i class="fas fa-image text-primary"></i>
                                                                    <?php echo htmlspecialchars($attachment['filename']); ?>
                                                                </p>
                                                                <a href="#" class="btn btn-sm btn-primary preview-attachment" data-id="<?php echo $attachment['id']; ?>" data-type="<?php echo htmlspecialchars($attachment['mime_type'] ?? ''); ?>">
                                                                    <i class="fas fa-eye"></i> Vista previa
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php elseif ($is_pdf): ?>
                                                        <div class="card">
                                                            <div class="card-body p-2 text-center">
                                                                <i class="fas fa-file-pdf fa-3x text-danger mb-2"></i>
                                                                <p class="card-text small text-truncate">
                                                                    <?php echo htmlspecialchars($attachment['filename']); ?>
                                                                </p>
                                                                <a href="#" class="btn btn-sm btn-primary preview-attachment" data-id="<?php echo $attachment['id']; ?>" data-type="<?php echo $attachment['mime_type'] ?? ''; ?>">
                                                                    <i class="fas fa-eye"></i> Vista previa
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php elseif ($is_doc): ?>
                                                        <div class="card">
                                                            <div class="card-body p-2 text-center">
                                                                <i class="fas fa-file-word fa-3x text-primary mb-2"></i>
                                                                <p class="card-text small text-truncate">
                                                                    <?php echo htmlspecialchars($attachment['filename']); ?>
                                                                </p>
                                                                <a href="#" class="btn btn-sm btn-primary preview-attachment" data-id="<?php echo $attachment['id']; ?>" data-type="<?php echo $attachment['mime_type'] ?? ''; ?>">
                                                                    <i class="fas fa-eye"></i> Vista previa
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php elseif ($is_xls): ?>
                                                        <div class="card">
                                                            <div class="card-body p-2 text-center">
                                                                <i class="fas fa-file-excel fa-3x text-success mb-2"></i>
                                                                <p class="card-text small text-truncate">
                                                                    <?php echo htmlspecialchars($attachment['filename']); ?>
                                                                </p>
                                                                <a href="#" class="btn btn-sm btn-primary preview-attachment" data-id="<?php echo $attachment['id']; ?>" data-type="<?php echo $attachment['mime_type'] ?? ''; ?>">
                                                                    <i class="fas fa-eye"></i> Vista previa
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="card">
                                                            <div class="card-body p-2 text-center">
                                                                <i class="fas fa-file fa-3x text-secondary mb-2"></i>
                                                                <p class="card-text small text-truncate">
                                                                    <?php echo htmlspecialchars($attachment['filename']); ?>
                                                                </p>
                                                                <a href="#" class="btn btn-sm btn-primary preview-attachment" data-id="<?php echo $attachment['id']; ?>" data-type="<?php echo $attachment['mime_type'] ?? ''; ?>">
                                                                    <i class="fas fa-eye"></i> Vista previa
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                        // Comentario: Esta sección ahora siempre muestra la descripción del ticket desde tickets.description
                        ?>
                    </div>
                </div>

                <!-- Sección de Motivos de Actualización -->
                <?php if (!empty($update_reasons)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-list me-2"></i>Motivos de Actualización
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($update_reasons as $reason): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($reason['reason'], ENT_QUOTES, 'UTF-8')); ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($reason['created_at'])); ?>
                                                <?php if (!empty($reason['created_by'])): ?>
                                                    | <i class="fas fa-user"></i> <?php echo htmlspecialchars($reason['created_by']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sección de Costos del Ticket -->
                <div class="card mb-4 ticket-costs-card">
                    <div class="card-header ticket-costs-header">
                        <h5 class="mb-0">Costos del Ticket</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ticket_costs)): ?>
                            <p class="text-muted mb-0">No hay costos registrados para este ticket.</p>
                        <?php else: ?>
                            <div class="ticket-costs-wrap">
                                <table class="table ticket-costs-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Descripción</th>
                                            <th scope="col">Monto</th>
                                            <th scope="col">Fecha</th>
                                            <th scope="col">Archivo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ticket_costs as $cost): ?>
                                            <tr>
                                                <td data-label="Descripción"><?php echo htmlspecialchars($cost['description']); ?></td>
                                                <td data-label="Monto" class="cost-amount-cell"><strong>$<?php echo number_format($cost['amount'], 2); ?></strong></td>
                                                <td data-label="Fecha"><?php echo date('d/m/Y H:i', strtotime($cost['created_at'])); ?></td>
                                                <td data-label="Archivo" class="cost-files-cell" data-cost-id="<?php echo $cost['id']; ?>">
                                                    <?php if ($cost['attachments_count'] > 0): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="showCostFiles(<?php echo $cost['id']; ?>)">
                                                            <i class="fas fa-paperclip"></i> 
                                                            <?php echo $cost['attachments_count']; ?> archivo<?php echo $cost['attachments_count'] > 1 ? 's' : ''; ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin archivos</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- <?php if (!empty($ticket['impact_details'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Detalles del Impacto</h5>
                    </div>
                    <div class="card-body">
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($ticket['impact_details'])); ?>
                        </div>
                    </div>
                </div> 
                <?php endif; ?> -->

                <?php if (!empty($ticket['resolution'])): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Resolución</h5>
                        <span class="badge bg-success">Resuelto</span>
                    </div>
                    <div class="card-body">
                        <div class="p-3 bg-light rounded">
                            <?php 
                            $resolution_content = $ticket['resolution'];
                            if (isHtml($resolution_content)) {
                                echo processHtmlBodyWithCidImages($resolution_content, $id, $all_attachments);
                            } else {
                                echo nl2br(htmlspecialchars($resolution_content, ENT_QUOTES, 'UTF-8'));
                            }
                            ?>
                        </div>
                    </div>
                </div> 
                <?php endif; ?>

                <?php 
                // Check if there are any non-image attachments to display in the dedicated section
                $has_non_images = false;
                foreach ($all_attachments as $attachment) {
                    $file_extension = strtolower(pathinfo($attachment['filename'], PATHINFO_EXTENSION));
                    $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
                    if (!$is_image) {
                        $has_non_images = true;
                        break;
                    }
                }
                
                // Comentario de depuración removido
                ?>

                <!-- Sección de Todos los Adjuntos del Ticket -->
                <?php if (!empty($all_attachments)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Todos los Archivos Adjuntos del Ticket</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($all_attachments as $attachment): 
                                // Manejar archivos sin nombre
                                $filename = $attachment['filename'] ?: basename($attachment['file_path']);
                                $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
                                $is_pdf = $file_extension === 'pdf';
                                $is_doc = in_array($file_extension, ['doc', 'docx']);
                                $is_xls = in_array($file_extension, ['xls', 'xlsx']);
                            ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100">
                                                                                 <?php if ($is_image): ?>
                                             <img src="<?php echo htmlspecialchars('download_attachment.php?id=' . $attachment['id']); ?>" 
                                                  class="card-img-top" 
                                                  alt="<?php echo htmlspecialchars($filename); ?>"
                                                  style="height: 150px; object-fit: cover;">
                                        <?php elseif ($is_pdf): ?>
                                            <div class="card-body text-center">
                                                <i class="fas fa-file-pdf fa-3x text-danger mb-2"></i>
                                            </div>
                                        <?php elseif ($is_doc): ?>
                                            <div class="card-body text-center">
                                                <i class="fas fa-file-word fa-3x text-primary mb-2"></i>
                                            </div>
                                        <?php elseif ($is_xls): ?>
                                            <div class="card-body text-center">
                                                <i class="fas fa-file-excel fa-3x text-success mb-2"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="card-body text-center">
                                                <i class="fas fa-file fa-3x text-secondary mb-2"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="card-body">
                                            <h6 class="card-title small text-truncate">
                                                <?php echo htmlspecialchars($filename); ?>
                                            </h6>
                                            <p class="card-text small text-muted">
                                                Tamaño: <?php echo number_format($attachment['size'] / 1024, 1); ?> KB<br>
                                                Tipo: <?php echo htmlspecialchars($attachment['mime_type'] ?? 'N/A'); ?>
                                            </p>
                                            <div class="d-flex gap-2">
                                                <a href="<?php echo htmlspecialchars('download_attachment.php?id=' . $attachment['id']); ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-download"></i> Descargar
                                                </a>
                                                <?php if ($is_image || $is_pdf): ?>
                                                <a href="#" class="btn btn-sm btn-outline-secondary preview-attachment" 
                                                   data-id="<?php echo $attachment['id']; ?>" 
                                                   data-type="<?php echo htmlspecialchars($attachment['mime_type'] ?? ''); ?>">
                                                    <i class="fas fa-eye"></i> Vista previa
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Sección de Conversación -->
                <?php if ($is_whatsapp_ticket): ?>
                <div class="ticket-channel-switch-wrap mt-4">
                    <span class="ticket-channel-switch-label">Canal de conversación</span>
                    <div class="ticket-channel-switch" id="ticketChannelSwitch" role="tablist" aria-label="Canal de conversación">
                        <button type="button" class="ticket-channel-switch-btn is-active" data-channel="whatsapp" role="tab" aria-selected="true" aria-controls="ticketConversationWa">
                            <i class="fas fa-comments me-1"></i> Mensajería
                        </button>
                        <button type="button" class="ticket-channel-switch-btn" data-channel="email" role="tab" aria-selected="false" aria-controls="ticketConversationEmail">
                            <i class="fas fa-envelope me-1"></i> Correo electrónico
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($is_whatsapp_ticket): ?>
                <div class="ticket-conversation-panel ticket-conversation-panel--wa is-active" id="ticketConversationWa">
                <div class="row">
                    <div class="col-12">
                        <div class="card ticket-chat-card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Conversación por mensajería</h5>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge-channel-msg"><i class="fas fa-sms me-1"></i>+<?php echo htmlspecialchars(whatsappChatNormalizePhone((string) ($ticket['requester_phone'] ?? ''))); ?></span>
                                    <!--<span class="ticket-chat-live" id="ticketWaLiveStatus">
                                        <i class="fas fa-circle ticket-chat-pulse"></i> En vivo
                                    </span>-->
                            </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="ticket-chat-panel">
                                <div class="ticket-chat-thread" id="ticketWaThread">
                                    <?php
                                    $wa_has_col = ticketsWhatsappColumnExists($conn, 'ticket_messages', 'is_whatsapp');
                                    $wa_sql = "
                                        SELECT tm.*,
                                        CASE WHEN tm.sender_type = 'requester' THEN r.name WHEN tm.sender_type = 'technician' THEN t.name ELSE 'Sistema' END AS sender_name
                                        FROM ticket_messages tm
                                        LEFT JOIN requesters r ON tm.sender_type = 'requester' AND tm.sender_id = r.id
                                        LEFT JOIN technicians t ON tm.sender_type = 'technician' AND tm.sender_id = t.id
                                        WHERE tm.ticket_id = ?";
                                    if ($wa_has_col) {
                                        $wa_sql .= " AND (tm.is_whatsapp = 1 OR tm.message_id LIKE 'wamid.%')";
                                    } else {
                                        $wa_sql .= " AND tm.message_id LIKE 'wamid.%'";
                                    }
                                    $wa_sql .= " ORDER BY tm.id ASC";
                                    $wa_stmt = $conn->prepare($wa_sql);
                                    $wa_stmt->bind_param('i', $id);
                                    $wa_stmt->execute();
                                    $wa_messages = $wa_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                    $wa_stmt->close();
                                    foreach ($wa_messages as $wm):
                                        $wa_last_message_id = max($wa_last_message_id, (int) $wm['id']);
                                        $wa_dir = ($wm['sender_type'] === 'technician') ? 'out' : 'in';
                                        $sender_label = (string) ($wm['sender_name'] ?? ($wa_dir === 'out' ? 'Técnico' : 'Cliente'));
                                        $initials = '';
                                        foreach (preg_split('/\s+/', trim($sender_label)) as $part) {
                                            if ($part !== '') {
                                                $initials .= mb_strtoupper(mb_substr($part, 0, 1));
                                            }
                                            if (mb_strlen($initials) >= 2) {
                                                break;
                                            }
                                        }
                                        if ($initials === '') {
                                            $initials = $wa_dir === 'out' ? 'T' : 'C';
                                        }
                                        $att_q = $conn->prepare('SELECT id, filename, file_path, mime_type FROM ticket_attachments WHERE ticket_message_id = ?');
                                        $att_q->bind_param('i', $wm['id']);
                                        $att_q->execute();
                                        $wa_atts = $att_q->get_result()->fetch_all(MYSQLI_ASSOC);
                                        $att_q->close();
                                    ?>
                                    <div class="ticket-chat-msg ticket-chat-msg--<?php echo $wa_dir; ?>" data-message-id="<?php echo (int) $wm['id']; ?>">
                                        <div class="ticket-chat-avatar" title="<?php echo htmlspecialchars($sender_label, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="ticket-chat-body">
                                            <div class="ticket-chat-meta">
                                                <span class="ticket-chat-sender"><?php echo htmlspecialchars($sender_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="ticket-chat-time"><?php echo date('d/m/Y H:i', strtotime($wm['created_at'])); ?></span>
                                                    </div>
                                            <div class="ticket-chat-bubble">
                                        <?php foreach ($wa_atts as $att):
                                            $attUrl = '../../' . ltrim($att['file_path'], '/');
                                            if (str_starts_with((string) ($att['mime_type'] ?? ''), 'image/')): ?>
                                                <div class="ticket-chat-media-item">
                                                    <img class="ticket-chat-image-zoom" src="<?php echo htmlspecialchars($attUrl); ?>" alt="imagen" role="button" tabindex="0">
                                                            </div>
                                            <?php elseif (str_starts_with((string) ($att['mime_type'] ?? ''), 'audio/')): ?>
                                                <audio controls src="<?php echo htmlspecialchars($attUrl); ?>"></audio>
                                            <?php else: ?>
                                                <a class="ticket-chat-file" href="<?php echo htmlspecialchars($attUrl); ?>" target="_blank" rel="noopener noreferrer">
                                                    <i class="fas fa-file-alt"></i>
                                                    <span><?php echo htmlspecialchars($att['filename'] ?? 'Archivo', ENT_QUOTES, 'UTF-8'); ?></span>
                                                </a>
                                            <?php endif;
                                        endforeach; ?>
                                                    <?php 
                                        $wa_visible_message = ticketsWhatsappChatVisibleMessageText(
                                            $wm['message'] ?? '',
                                            $wa_atts,
                                            $wm['whatsapp_type'] ?? null
                                        );
                                        if ($wa_visible_message !== ''): ?>
                                            <div><?php echo nl2br(htmlspecialchars($wa_visible_message, ENT_QUOTES, 'UTF-8')); ?></div>
                                        <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                            </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($wa_messages)): ?>
                                    <div class="ticket-chat-empty" id="ticketWaEmpty">
                                        <i class="fas fa-comment-dots"></i>
                                        <p>Sin mensajes aún. La conversación se actualizará aquí automáticamente.</p>
                                                                        </div>
                                    <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                <?php if (!$ticket['closed_at']): ?>
                                <div class="ticket-chat-composer">
                                    <?php if (empty($ticket['technician_id'])): ?>
                                    <div class="alert alert-warning mb-0 py-2">
                                        <i class="fas fa-exclamation-triangle"></i> Asigne un técnico al ticket para responder por mensajería.
                                                                        </div>
                                                                    <?php else: ?>
                                    <div class="ticket-chat-attachments" id="ticketWaAttachments" hidden>
                                        <div class="ticket-chat-attachments-header">
                                            <span id="ticketWaAttachCounter">0 archivos</span>
                                            <button type="button" class="ticket-chat-attachments-clear" id="ticketWaClearAttach">Quitar todos</button>
                                                                            </div>
                                        <div class="ticket-chat-attachments-grid" id="ticketWaAttachGrid"></div>
                                                                        </div>
                                    <div class="ticket-chat-composer-row">
                                        <label class="btn-attach" title="Adjuntar archivos" id="ticketWaAttachBtn">
                                            <i class="fas fa-paperclip"></i>
                                            <input type="file" id="ticketWaFiles" multiple accept="image/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar" hidden>
                                        </label>
                                        <input type="text" class="form-control" id="ticketWaInput" placeholder="Escriba su respuesta al cliente..." autocomplete="off">
                                        <button type="button" class="btn btn-primary" id="ticketWaSend">
                                            <i class="fas fa-paper-plane me-1"></i> Enviar
                                        </button>
                                    </div>
                                    <small class="ticket-chat-composer-hint d-block">Puede adjuntar varias imágenes, audios o documentos. Se enviarán vía mensajería al +<?php echo htmlspecialchars(whatsappChatNormalizePhone((string) ($ticket['requester_phone'] ?? ''))); ?></small>
                                                                    <?php endif; ?>
                                                                </div>
                                <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    </div>
                                            </div>
                                        </div>
                <?php endif; ?>

                <div class="ticket-conversation-panel ticket-conversation-panel--email<?php echo !$is_whatsapp_ticket ? ' is-active' : ''; ?>" id="ticketConversationEmail">
                <div class="row<?php echo $is_whatsapp_ticket ? '' : ' mt-4'; ?>">
                    <div class="col-12">
                        <div class="card ticket-chat-card ticket-email-conversation-card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Conversación por correo electrónico</h5>
                                <?php if (!empty($ticket['requester_email'])): ?>
                                <span class="ticket-chat-channel"><i class="fas fa-at me-1"></i><?php echo htmlspecialchars((string) $ticket['requester_email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <div class="ticket-chat-panel">
                                    <div class="ticket-messages ticket-email-thread" id="ticketEmailMessages">
                                        <?php
                                        $email_conversation = ticketEmailRenderConversation($conn, (int) $id, $is_whatsapp_ticket, $all_attachments);
                                        echo $email_conversation['html'];
                                        $email_last_message_id = (int) $email_conversation['last_message_id'];
                                        ?>
                                    </div>
                                </div>

                                <?php if (!$ticket['closed_at']): ?>
                                <div class="ticket-chat-composer ticket-email-composer reply-card">
                                    <div id="ticketEmailReplyStatus" class="mb-2" style="display:none;"></div>
                                    <form method="POST" action="" id="ticketEmailReplyForm" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="reply">
                                        <input type="hidden" name="ajax" value="1">
                                        <input type="hidden" name="technician_id" value="<?php echo $ticket['technician_id'] ?? ''; ?>">

                                        <div class="ticket-email-composer-cc mb-2">
                                            <label for="cc_email" class="form-label mb-1">Con copia (CC)</label>
                                            <input
                                                type="email"
                                                class="form-control form-control-sm"
                                                id="cc_email"
                                                name="cc_email"
                                                placeholder="correo1@ejemplo.com, correo2@ejemplo.com"
                                                multiple
                                            >
                                        </div>

                                        <div class="ticket-email-composer-editor mb-2">
                                            <div id="editor" class="quill-editor"></div>
                                            <input type="hidden" name="message" id="hiddenMessageInput">
                                        </div>

                                        <div class="reply-attachments-box mb-2">
                                            <input
                                                type="file"
                                                class="d-none"
                                                id="reply_attachments"
                                                name="reply_attachments[]"
                                                multiple
                                            >
                                            <div class="reply-attachments-actions">
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="selectReplyFilesBtn">
                                                    <i class="fas fa-paperclip me-1"></i> Adjuntar
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="clearReplyFilesBtn" style="display:none;">
                                                    <i class="fas fa-trash-alt me-1"></i> Limpiar
                                                </button>
                                                <span class="text-muted small" id="replyAttachmentsCounter">Sin archivos</span>
                                            </div>
                                            <div class="reply-attachments-dropzone" id="replyAttachmentsDropzone">
                                                Arrastre archivos aquí o use Adjuntar.
                                            </div>
                                            <div class="reply-attachments-list" id="replyAttachmentsList" style="display:none;"></div>
                                            <div class="text-muted small reply-attachments-empty" id="replyAttachmentsEmpty">
                                                Los archivos se enviarán con la respuesta por correo.
                                            </div>
                                        </div>

                                        <?php if (empty($ticket['technician_id'])): ?>
                                        <div class="alert alert-warning mb-0 py-2">
                                            <i class="fas fa-exclamation-triangle"></i> Asigne un técnico al ticket para responder por correo.
                                        </div>
                                        <button type="submit" class="btn btn-primary mt-2" disabled>Enviar respuesta</button>
                                        <?php else: ?>
                                        <div class="ticket-email-composer-footer">
                                            <small class="ticket-chat-composer-hint mb-0">
                                                Se enviará como <?php echo htmlspecialchars(normalizeSpecialCharacters($ticket['technician_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                            </small>
                                            <button type="submit" class="btn btn-primary" id="ticketEmailReplySubmit">
                                                <i class="fas fa-paper-plane me-1"></i> Enviar
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
                <!-- FIN Conversación -->

                <?php if ($ticket['closed_at']): ?>
                <div class="alert alert-secondary mt-3">
                    <i class="fas fa-lock"></i> Este ticket está cerrado. No se pueden enviar más respuestas.
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>

<link href="css/ticket-whatsapp.css" rel="stylesheet">

<?php if ($is_whatsapp_ticket): ?>
<div class="ticket-chat-lightbox" id="ticketChatLightbox" hidden aria-hidden="true">
    <div class="ticket-chat-lightbox-backdrop" data-lightbox-close></div>
    <div class="ticket-chat-lightbox-panel" role="dialog" aria-modal="true" aria-label="Vista ampliada de imagen">
        <div class="ticket-chat-lightbox-toolbar">
            <button type="button" class="ticket-chat-lightbox-btn" data-lightbox-zoom="out" title="Alejar">
                <i class="fas fa-search-minus"></i>
            </button>
            <span class="ticket-chat-lightbox-zoom-label" id="ticketChatLightboxZoom">100%</span>
            <button type="button" class="ticket-chat-lightbox-btn" data-lightbox-zoom="in" title="Acercar">
                <i class="fas fa-search-plus"></i>
            </button>
            <button type="button" class="ticket-chat-lightbox-btn" data-lightbox-zoom="fit" title="Ajustar a pantalla">
                <i class="fas fa-compress"></i>
            </button>
            <button type="button" class="ticket-chat-lightbox-btn" data-lightbox-zoom="reset" title="Tamaño real">
                <i class="fas fa-expand"></i>
            </button>
            <a class="ticket-chat-lightbox-btn" id="ticketChatLightboxDownload" href="#" download title="Descargar" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-download"></i>
            </a>
            <button type="button" class="ticket-chat-lightbox-btn ticket-chat-lightbox-close" data-lightbox-close title="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="ticket-chat-lightbox-stage" id="ticketChatLightboxStage">
            <img id="ticketChatLightboxImg" class="ticket-chat-lightbox-img" src="" alt="">
        </div>
        <p class="ticket-chat-lightbox-hint">Rueda del mouse para zoom · Arrastre para mover · Esc para cerrar</p>
    </div>
</div>
<script>
window.TICKET_WA = {
    enabled: true,
    apiUrl: 'whatsapp_api.php',
    ticketId: <?php echo (int) $id; ?>,
    technicianId: <?php echo json_encode((string) ($ticket['technician_id'] ?? '')); ?>,
    lastMessageId: <?php echo (int) $wa_last_message_id; ?>
};
</script>
<script src="js/ticket-whatsapp.js"></script>
<?php endif; ?>

<script>
window.TICKET_EMAIL = {
    enabled: true,
    apiUrl: 'api/ticket_email_messages.php',
    ticketId: <?php echo (int) $id; ?>,
    lastMessageId: <?php echo (int) $email_last_message_id; ?>
};
</script>
<script src="js/ticket-email.js"></script>
    
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
    function printTicket() {
        window.print();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar el editor solo si existe el contenedor (no hay formulario cuando el ticket está cerrado)
        const editorElement = document.getElementById('editor');
        if (!editorElement) {
            return;
        }

        const quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'Escribe tu respuesta aquí...'
        });

        // El formulario de respuesta solo existe cuando el ticket no está cerrado
        const form = document.getElementById('ticketEmailReplyForm') || document.querySelector('.reply-card form');
        if (!form) {
            return;
        }

        const fileInput = document.getElementById('reply_attachments');
        const selectFilesBtn = document.getElementById('selectReplyFilesBtn');
        const clearFilesBtn = document.getElementById('clearReplyFilesBtn');
        const attachmentsList = document.getElementById('replyAttachmentsList');
        const attachmentsCounter = document.getElementById('replyAttachmentsCounter');
        const attachmentsEmpty = document.getElementById('replyAttachmentsEmpty');
        const attachmentsDropzone = document.getElementById('replyAttachmentsDropzone');
        const selectedFiles = [];

        function getFileKey(file) {
            return [file.name, file.size, file.lastModified, file.type].join('::');
        }

        function syncFileInputFromSelectedFiles() {
            if (!fileInput) return;
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(function (file) {
                dataTransfer.items.add(file);
            });
            fileInput.files = dataTransfer.files;
        }

        function updateAttachmentsCounter() {
            if (!attachmentsCounter) return;
            if (selectedFiles.length === 0) {
                attachmentsCounter.textContent = 'No hay archivos seleccionados';
            } else if (selectedFiles.length === 1) {
                attachmentsCounter.textContent = '1 archivo cargado';
            } else {
                attachmentsCounter.textContent = selectedFiles.length + ' archivos cargados';
            }
        }

        function renderSelectedFiles() {
            if (!attachmentsList || !attachmentsEmpty) return;
            attachmentsList.innerHTML = '';

            if (selectedFiles.length === 0) {
                attachmentsList.style.display = 'none';
                attachmentsEmpty.style.display = 'block';
                if (clearFilesBtn) clearFilesBtn.style.display = 'none';
                updateAttachmentsCounter();
                return;
            }

            selectedFiles.forEach(function (file, index) {
                const chip = document.createElement('div');
                chip.className = 'reply-attachment-chip';
                chip.innerHTML = `
                    <i class="fas fa-file text-secondary"></i>
                    <span class="reply-attachment-chip-name" title="${file.name}">${file.name}</span>
                    <span class="reply-attachment-chip-size">${formatFileSize(file.size)}</span>
                    <button type="button" class="reply-attachment-remove-btn" data-index="${index}" title="Eliminar archivo">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                attachmentsList.appendChild(chip);
            });

            attachmentsList.style.display = 'flex';
            attachmentsEmpty.style.display = 'none';
            if (clearFilesBtn) clearFilesBtn.style.display = 'inline-flex';
            updateAttachmentsCounter();
            syncFileInputFromSelectedFiles();
        }

        function addNewFiles(files) {
            if (!files || files.length === 0) return;
            const existingKeys = new Set(selectedFiles.map(getFileKey));
            Array.from(files).forEach(function (file) {
                const key = getFileKey(file);
                if (!existingKeys.has(key)) {
                    selectedFiles.push(file);
                    existingKeys.add(key);
                }
            });
            renderSelectedFiles();
        }

        if (selectFilesBtn && fileInput) {
            selectFilesBtn.addEventListener('click', function () {
                // Limpiar solo al abrir el selector: permite elegir de nuevo el mismo archivo
                // sin borrar la lista que ya esta en memoria (selectedFiles se vuelve a sincronizar en change/submit).
                fileInput.value = '';
                fileInput.click();
            });
        }

        if (fileInput && attachmentsList && attachmentsCounter && attachmentsEmpty) {
            fileInput.addEventListener('change', function () {
                addNewFiles(fileInput.files || []);
                // No poner fileInput.value = '' aqui: vacia el input y el formulario se envia sin archivos.
            });
        }

        if (attachmentsList) {
            attachmentsList.addEventListener('click', function (event) {
                const removeButton = event.target.closest('.reply-attachment-remove-btn');
                if (!removeButton) return;
                const index = parseInt(removeButton.getAttribute('data-index'), 10);
                if (!Number.isNaN(index)) {
                    selectedFiles.splice(index, 1);
                    renderSelectedFiles();
                }
            });
        }

        if (clearFilesBtn) {
            clearFilesBtn.addEventListener('click', function () {
                selectedFiles.length = 0;
                syncFileInputFromSelectedFiles();
                if (fileInput) {
                    fileInput.value = '';
                }
                renderSelectedFiles();
            });
        }

        if (attachmentsDropzone && fileInput) {
            ['dragenter', 'dragover'].forEach(function (eventName) {
                attachmentsDropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    attachmentsDropzone.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach(function (eventName) {
                attachmentsDropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    attachmentsDropzone.classList.remove('dragover');
                });
            });

            attachmentsDropzone.addEventListener('drop', function (event) {
                const droppedFiles = event.dataTransfer ? event.dataTransfer.files : null;
                addNewFiles(droppedFiles || []);
            });
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            syncFileInputFromSelectedFiles();

            const hiddenMessageInput = document.querySelector('#hiddenMessageInput');
            if (hiddenMessageInput) {
                hiddenMessageInput.value = quill.root.innerHTML;
            }

            const plainText = (quill.getText() || '').trim();
            if (!plainText && !quill.root.querySelector('img')) {
                showReplyStatus('Escriba un mensaje antes de enviar.', 'danger');
                return;
            }

            const submitBtn = document.getElementById('ticketEmailReplySubmit');
            const previousBtnHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Enviando…';
            }

            try {
                const formData = new FormData(form);
                formData.set('ajax', '1');
                formData.set('message', hiddenMessageInput ? hiddenMessageInput.value : quill.root.innerHTML);

                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                const raw = await res.text();
                let data;
                try {
                    data = JSON.parse(raw);
                } catch (parseErr) {
                    throw new Error('Respuesta inválida del servidor');
                }

                if (!data.ok && data.messageType === 'danger') {
                    throw new Error(data.message || 'No se pudo enviar la respuesta');
                }

                showReplyStatus(data.message || 'Respuesta enviada.', data.messageType || 'success');

                quill.setContents([]);
                if (hiddenMessageInput) {
                    hiddenMessageInput.value = '';
                }
                const ccInput = document.getElementById('cc_email');
                if (ccInput) {
                    ccInput.value = '';
                }
                selectedFiles.length = 0;
                if (fileInput) {
                    fileInput.value = '';
                }
                syncFileInputFromSelectedFiles();
                renderSelectedFiles();

                if (window.TICKET_EMAIL && typeof window.TICKET_EMAIL.refresh === 'function') {
                    await window.TICKET_EMAIL.refresh();
                }

                const messagesBox = document.getElementById('ticketEmailMessages');
                if (messagesBox) {
                    messagesBox.scrollTop = messagesBox.scrollHeight;
                }
            } catch (err) {
                console.warn('Email reply:', err.message);
                showReplyStatus(err.message || 'Error al enviar la respuesta', 'danger');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = previousBtnHtml || '<i class="fas fa-paper-plane me-1"></i> Enviar Respuesta';
                }
            }
        });

        function showReplyStatus(text, type) {
            const box = document.getElementById('ticketEmailReplyStatus');
            if (!box) {
                alert(text);
                return;
            }
            const safeType = ['success', 'warning', 'danger', 'info'].includes(type) ? type : 'info';
            box.style.display = 'block';
            box.className = 'mb-3 alert alert-' + safeType;
            box.textContent = text;
            box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });

    // Remover este bloque duplicado de JavaScript
</script>

<!-- Modal para vista previa de adjuntos -->
<div class="modal fade" id="attachmentPreviewModal" tabindex="-1" aria-labelledby="attachmentPreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="attachmentPreviewModalLabel">Vista previa del archivo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="attachmentPreviewContent" style="min-height:60vh; display:flex; align-items:center; justify-content:center;"></div>
    </div>
  </div>
</div>

<!-- Modal para ver archivos de un costo -->
<div class="modal fade" id="viewCostFilesModal" tabindex="-1" aria-labelledby="viewCostFilesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewCostFilesModalLabel">
                    <i class="fas fa-paperclip"></i> Archivos Adjuntos del Costo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cost_files_loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted">Cargando archivos...</p>
                </div>
                <div id="cost_files_container" style="display: none;">
                    <div id="cost_files_list" class="list-group">
                        <!-- Los archivos se cargarán dinámicamente aquí -->
                    </div>
                </div>
                <div id="cost_files_empty" style="display: none;" class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No hay archivos adjuntos en este costo.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Formatear tamaño de archivo
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Mostrar modal con los archivos de un costo
function showCostFiles(costId) {
    const modal = new bootstrap.Modal(document.getElementById('viewCostFilesModal'));
    const loadingDiv = document.getElementById('cost_files_loading');
    const containerDiv = document.getElementById('cost_files_container');
    const emptyDiv = document.getElementById('cost_files_empty');
    const filesList = document.getElementById('cost_files_list');
    
    // Mostrar loading
    loadingDiv.style.display = 'block';
    containerDiv.style.display = 'none';
    emptyDiv.style.display = 'none';
    
    // Mostrar el modal
    modal.show();
    
    // Cargar los archivos
    fetch('get_cost_files.php?cost_id=' + costId)
    .then(response => response.json())
    .then(data => {
        loadingDiv.style.display = 'none';
        
        if (data.success && data.files.length > 0) {
            filesList.innerHTML = '';
            
            data.files.forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                fileItem.innerHTML = `
                    <div class="d-flex align-items-center flex-grow-1">
                        <i class="fas fa-file me-3 text-primary" style="font-size: 1.5rem;"></i>
                        <div>
                            <strong>${file.file_name}</strong><br>
                            <small class="text-muted">${formatFileSize(file.file_size)}</small>
                        </div>
                    </div>
                    <div class="btn-group">
                        <a href="download_cost_file.php?id=${costId}&file_id=${file.id}" 
                           class="btn btn-sm btn-outline-primary" 
                           target="_blank"
                           title="Descargar">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                `;
                filesList.appendChild(fileItem);
            });
            
            containerDiv.style.display = 'block';
        } else {
            emptyDiv.style.display = 'block';
        }
    })
    .catch(error => {
        loadingDiv.style.display = 'none';
        emptyDiv.style.display = 'block';
        console.error('Error al cargar archivos:', error);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    var previewModalEl = document.getElementById('attachmentPreviewModal');
    var previewContent = document.getElementById('attachmentPreviewContent');
    
    // Verificar que Bootstrap esté disponible y que el modal exista
    if (!previewModalEl || !previewContent || typeof bootstrap === 'undefined') {
        console.log('Modal o Bootstrap no disponible');
        return;
    }
    
    var previewModal = new bootstrap.Modal(previewModalEl);

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.preview-attachment');
        if (btn) {
            e.preventDefault();
            var id = btn.getAttribute('data-id');
            var type = btn.getAttribute('data-type');
            console.log('Preview clicked - ID:', id, 'Type:', type);
            previewContent.innerHTML = '';
            if (type && type.startsWith('image/')) {
                var img = document.createElement('img');
                img.src = 'download_attachment.php?id=' + encodeURIComponent(id) + '&action=preview';
                img.style.maxWidth = '100%';
                img.style.maxHeight = '70vh';
                img.style.display = 'block';
                img.style.margin = '0 auto';
                previewContent.appendChild(img);
            } else if (type === 'application/pdf') {
                var iframe = document.createElement('iframe');
                iframe.src = 'download_attachment.php?id=' + encodeURIComponent(id) + '&action=preview';
                iframe.style.width = '100%';
                iframe.style.height = '70vh';
                iframe.style.border = 'none';
                previewContent.appendChild(iframe);
            } else {
                console.log('Tipo no soportado para vista previa:', type);
                previewContent.innerHTML = '<div class="alert alert-info">No se puede mostrar vista previa para este tipo de archivo.</div>';
            }
            previewModal.show();
        }
    });
});
</script>
</body>
</html>