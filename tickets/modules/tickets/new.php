<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
include '../../includes/ticket_history.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../includes/whatsapp_ticket_bridge.php';

// Incluir PHPMailer para envío de correos
require_once '../../vendor/autoload.php';
require_once '../../conexion/config_mailer.php';
require_once '../../conexion/get_empresa_email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$message = '';
$messageType = '';

// Función para verificar si una columna existe en una tabla
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

ticketsWhatsappEnsureSchema($conn);

// Obtener empresa del usuario actual
$user_empresa_id = getCurrentUserEmpresaId();

// Filtrar configuraciones por empresa del usuario (solo si el campo existe)
$has_categories_empresa = columnExists($conn, 'categories', 'id_empresa');
$categories_query = "SELECT id, name FROM categories";
$categories_params = [];
$categories_types = '';
if ($has_categories_empresa && $user_empresa_id !== null) {
    $categories_query .= " WHERE id_empresa = ?";
    $categories_params[] = $user_empresa_id;
    $categories_types = 'i';
}
$categories_query .= " ORDER BY name";
if (!empty($categories_params)) {
    $categories_stmt = $conn->prepare($categories_query);
    $categories_stmt->bind_param($categories_types, ...$categories_params);
    $categories_stmt->execute();
    $categories = $categories_stmt->get_result();
} else {
    $categories = $conn->query($categories_query);
}

// Los estados no se filtran por empresa - se muestran todos para uso en creación por correos
$statuses_query = "SELECT id, name FROM statuses WHERE id != 3 ORDER BY name";
    $statuses = $conn->query($statuses_query);

$has_contact_modes_empresa = columnExists($conn, 'contact_modes', 'id_empresa');
$contact_modes_query = "SELECT id, name FROM contact_modes";
$contact_modes_params = [];
$contact_modes_types = '';
if ($has_contact_modes_empresa && $user_empresa_id !== null) {
    $contact_modes_query .= " WHERE id_empresa = ?";
    $contact_modes_params[] = $user_empresa_id;
    $contact_modes_types = 'i';
}
$contact_modes_query .= " ORDER BY name";
if (!empty($contact_modes_params)) {
    $contact_modes_stmt = $conn->prepare($contact_modes_query);
    $contact_modes_stmt->bind_param($contact_modes_types, ...$contact_modes_params);
    $contact_modes_stmt->execute();
    $contact_modes = $contact_modes_stmt->get_result();
} else {
    $contact_modes = $conn->query($contact_modes_query);
}

$has_levels_empresa = columnExists($conn, 'levels', 'id_empresa');
$levels_query = "SELECT id, name FROM levels";
$levels_params = [];
$levels_types = '';
if ($has_levels_empresa && $user_empresa_id !== null) {
    $levels_query .= " WHERE id_empresa = ?";
    $levels_params[] = $user_empresa_id;
    $levels_types = 'i';
}
$levels_query .= " ORDER BY name";
if (!empty($levels_params)) {
    $levels_stmt = $conn->prepare($levels_query);
    $levels_stmt->bind_param($levels_types, ...$levels_params);
    $levels_stmt->execute();
    $levels = $levels_stmt->get_result();
} else {
    $levels = $conn->query($levels_query);
}

$has_impacts_empresa = columnExists($conn, 'impacts', 'id_empresa');
$impacts_query = "SELECT id, name FROM impacts";
$impacts_params = [];
$impacts_types = '';
if ($has_impacts_empresa && $user_empresa_id !== null) {
    $impacts_query .= " WHERE id_empresa = ?";
    $impacts_params[] = $user_empresa_id;
    $impacts_types = 'i';
}
$impacts_query .= " ORDER BY name";
if (!empty($impacts_params)) {
    $impacts_stmt = $conn->prepare($impacts_query);
    $impacts_stmt->bind_param($impacts_types, ...$impacts_params);
    $impacts_stmt->execute();
    $impacts = $impacts_stmt->get_result();
} else {
    $impacts = $conn->query($impacts_query);
}

$has_urgencies_empresa = columnExists($conn, 'urgencies', 'id_empresa');
$urgencies_query = "SELECT id, name FROM urgencies";
$urgencies_params = [];
$urgencies_types = '';
if ($has_urgencies_empresa && $user_empresa_id !== null) {
    $urgencies_query .= " WHERE id_empresa = ?";
    $urgencies_params[] = $user_empresa_id;
    $urgencies_types = 'i';
}
$urgencies_query .= " ORDER BY name";
if (!empty($urgencies_params)) {
    $urgencies_stmt = $conn->prepare($urgencies_query);
    $urgencies_stmt->bind_param($urgencies_types, ...$urgencies_params);
    $urgencies_stmt->execute();
    $urgencies = $urgencies_stmt->get_result();
} else {
    $urgencies = $conn->query($urgencies_query);
}

$has_priorities_empresa = columnExists($conn, 'priorities', 'id_empresa');
$priorities_query = "SELECT id, name FROM priorities";
$priorities_params = [];
$priorities_types = '';
if ($has_priorities_empresa && $user_empresa_id !== null) {
    $priorities_query .= " WHERE id_empresa = ?";
    $priorities_params[] = $user_empresa_id;
    $priorities_types = 'i';
}
$priorities_query .= " ORDER BY name";
if (!empty($priorities_params)) {
    $priorities_stmt = $conn->prepare($priorities_query);
    $priorities_stmt->bind_param($priorities_types, ...$priorities_params);
    $priorities_stmt->execute();
    $priorities = $priorities_stmt->get_result();
} else {
    $priorities = $conn->query($priorities_query);
}

$has_classifications_empresa = columnExists($conn, 'classifications', 'id_empresa');
$classifications_query = "SELECT id, name FROM classifications";
$classifications_params = [];
$classifications_types = '';
if ($has_classifications_empresa && $user_empresa_id !== null) {
    $classifications_query .= " WHERE id_empresa = ?";
    $classifications_params[] = $user_empresa_id;
    $classifications_types = 'i';
}
$classifications_query .= " ORDER BY name";
if (!empty($classifications_params)) {
    $classifications_stmt = $conn->prepare($classifications_query);
    $classifications_stmt->bind_param($classifications_types, ...$classifications_params);
    $classifications_stmt->execute();
    $classifications = $classifications_stmt->get_result();
} else {
    $classifications = $conn->query($classifications_query);
}

// Requesters y technicians ya tienen filtros de empresa aplicados
$requesters_query = "SELECT r.id, r.name, e.nombre as company_name FROM requesters r LEFT JOIN empresas e ON r.company_id = e.id_empresa";
$requesters_params = [];
$requesters_types = '';
$requesters_query = addEmpresaFilter($requesters_query, 'r.id_empresa', $requesters_params, $requesters_types);
$requesters_query .= " ORDER BY r.name";
$requesters_stmt = $conn->prepare($requesters_query);
if (!empty($requesters_params)) {
    $requesters_stmt->bind_param($requesters_types, ...$requesters_params);
}
$requesters_stmt->execute();
$requesters = $requesters_stmt->get_result();

$technicians_query = "SELECT id, name FROM technicians";
$technicians_params = [];
$technicians_types = '';
$technicians_query = addEmpresaFilter($technicians_query, 'id_empresa', $technicians_params, $technicians_types);
$technicians_query .= " ORDER BY name";
$technicians_stmt = $conn->prepare($technicians_query);
if (!empty($technicians_params)) {
    $technicians_stmt->bind_param($technicians_types, ...$technicians_params);
}
$technicians_stmt->execute();
$technicians = $technicians_stmt->get_result();

// Función para enviar correo de notificación de ticket creado
function sendTicketCreationNotification($ticket_id, $ticket_number, $requester_email, $requester_name, $ticket_description, $technician_name = null, $technician_email = null, $id_empresa = null) {
    global $conn;
    try {
        $empresa_config = getEmpresaEmailConfig($id_empresa, $conn);
        if (!$empresa_config) {
            throw new Exception('No hay configuración de correo activa para esta empresa.');
        }

        $mail = createMailerForEmpresa($id_empresa, $conn);
        
        // Configurar destinatario
        $mail->addAddress($requester_email, $requester_name);
        
        // Agregar copia al técnico asignado si existe
        if (!empty($technician_email)) {
            $mail->addCC($technician_email, $technician_name);
        }
        
        // Configurar asunto
        $mail->Subject = "[Ticket #$ticket_number] Nuevo ticket creado - Sistema de Tickets";
        
        // Diseño del correo con información del ticket
        $email_body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Ticket Creado #$ticket_number</title>
            </head>
            <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f9fa;'>
                <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;'>
                    <!-- Header del correo -->
                    <div style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 30px; text-align: center; color: white;'>
                        <h2 style='margin: 0; font-size: 24px; font-weight: 600;'>Sistema de Tickets</h2>
                        <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>Ticket #$ticket_number - Creado</p>
                    </div>
                    
                    <!-- Contenido principal -->
                    <div style='padding: 40px 30px;'>
                        <p style='font-size: 18px; margin-bottom: 25px; color: #2c3e50;'>
                            Hola <strong>$requester_name</strong>,
                        </p>
                        
                        <p style='font-size: 16px; margin-bottom: 20px; color: #495057;'>
                            Se ha creado un nuevo ticket con los siguientes detalles:
                        </p>
                        
                        <!-- Información del ticket -->
                        <div style='background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 12px; border-left: 5px solid #28a745; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
                            <h3 style='margin: 0 0 15px 0; color: #2c3e50; font-size: 18px;'>Descripción del problema:</h3>
                            <div style='background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 15px;'>
                                " . nl2br(htmlspecialchars($ticket_description)) . "
                            </div>";
                            
        if ($technician_name) {
            $email_body .= "
                            <p style='margin: 10px 0 0 0; font-size: 14px; color: #6c757d;'>
                                <strong>Técnico asignado:</strong> $technician_name
                            </p>";
        }
        
        $email_body .= "
                        </div>
                        
                        <p style='color: #6c757d; font-size: 14px; margin-top: 30px; text-align: center;'>
                            Recibirá notificaciones sobre las actualizaciones de este ticket. Si tiene alguna pregunta adicional, puede responder directamente a este correo.
                        </p>
                    </div>
                    
                    <!-- Footer -->
                    <div style='background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #dee2e6;'>
                        <div style='color: #6c757d; font-size: 12px;'>
                            <p style='margin: 0 0 10px 0;'>
                                <strong>GK</strong> 
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
        
        $mail->Body = $email_body;
        $mail->AltBody = strip_tags($email_body);
        
        $mail->addReplyTo($empresa_config['smtp_from_email'], $empresa_config['smtp_from_name'] ?? 'Sistema de Tickets');
        
        // Enviar el correo
        if (!$mail->send()) {
            throw new Exception("Error al enviar el correo: " . $mail->ErrorInfo);
        }
        
        error_log("Correo de notificación de ticket creado enviado exitosamente a: " . $requester_email . " para ticket ID: " . $ticket_id);
        return [
            'success' => true,
            'message' => 'Correo de notificación enviado exitosamente'
        ];
    } catch (Exception $e) {
        error_log("ERROR - Fallo al enviar correo de notificación de ticket creado para ticket ID " . $ticket_id . ": " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al enviar el correo de notificación: ' . $e->getMessage()
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = $_POST['category_id'] ?? null;
    $status_id = $_POST['status_id'] ?? null;
    $contact_mode_id = $_POST['contact_mode_id'] ?? null;
    $level_id = $_POST['level_id'] ?? null;
    $impact_id = $_POST['impact_id'] ?? null;
    $urgency_id = $_POST['urgency_id'] ?? null;
    $priority_id = $_POST['priority_id'] ?? null;
    $impact_details = $_POST['impact_details'] ?? null;
    $requester_id = $_POST['requester_id'] ?? null;
    $classification_id = $_POST['classification_id'] ?? null;
    $technician_id = $_POST['technician_id'] ?? null;
    $description = $_POST['description'] ?? null;
    $resolution = $_POST['resolution'] ?? null;
    $cost_description = $_POST['cost_description'] ?? null;
    $cost_amount = $_POST['cost_amount'] ?? null;
    
    if (empty($description)) {
        $message = "La descripción del ticket es obligatoria";
        $messageType = "danger";
    } else {
        // Obtener empresa del usuario actual
        $user_empresa_id = getCurrentUserEmpresaId();
        
        // Incluir helper de contador de tickets
        require_once '../../includes/ticket_counter_helper.php';
        
        // Intentar insertar el ticket con reintentos en caso de duplicado
        $max_retries = 5;
        $ticket_inserted = false;
        $ticket_id = null;
        $ticket_number = null;
        
        for ($attempt = 0; $attempt < $max_retries && !$ticket_inserted; $attempt++) {
            // Obtener el siguiente número de ticket para esta empresa
            $ticket_number = getNextTicketNumber($conn, $user_empresa_id);
            
            $stmt = $conn->prepare("INSERT INTO tickets (
                category_id, status_id, contact_mode_id, level_id, impact_id, 
                urgency_id, priority_id, impact_details, requester_id, 
                classification_id, technician_id, description, resolution, id_empresa, ticket_number
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param(
                "iiiiiiisisissis", 
                $category_id, $status_id, $contact_mode_id, $level_id, $impact_id,
                $urgency_id, $priority_id, $impact_details, $requester_id,
                $classification_id, $technician_id, $description, $resolution, $user_empresa_id, $ticket_number
            );
            
            try {
                if ($stmt->execute()) {
                    $ticket_id = $conn->insert_id;
                    $ticket_inserted = true;
                } else {
                    // Verificar si es un error de duplicado
                    $error_code = $stmt->errno;
                    $error_message = $stmt->error;
                    
                    if ($error_code == 1062 || strpos($error_message, 'Duplicate entry') !== false || strpos($error_message, 'idx_ticket_number_empresa') !== false) {
                        // Es un error de duplicado, intentar con otro número
                        error_log("ADVERTENCIA: Intento " . ($attempt + 1) . " falló por duplicado. Ticket number: {$ticket_number}, Empresa: {$user_empresa_id}. Reintentando...");
                        $stmt->close();
                        // Esperar un poco antes de reintentar (para evitar condiciones de carrera)
                         // 100ms
                        continue;
                    } else {
                        // Es otro tipo de error, lanzar excepción
                        throw new Exception("Error al insertar ticket: " . $error_message);
                    }
                }
            } catch (mysqli_sql_exception $e) {
                // Verificar si es un error de duplicado
                if ($e->getCode() == 1062 || strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'idx_ticket_number_empresa') !== false) {
                    // Es un error de duplicado, intentar con otro número
                    error_log("ADVERTENCIA: Intento " . ($attempt + 1) . " falló por duplicado (excepción). Ticket number: {$ticket_number}, Empresa: {$user_empresa_id}. Reintentando...");
                    if (isset($stmt)) {
                        $stmt->close();
                    }
                    // Esperar un poco antes de reintentar
                     // 100ms
                    continue;
                } else {
                    // Es otro tipo de error, relanzar
                    throw $e;
                }
            }
            
            if (isset($stmt)) {
                $stmt->close();
            }
        }
        
        if ($ticket_inserted && $ticket_id) {
            if (columnExists($conn, 'tickets', 'whatsapp_enabled')) {
                $whatsapp_enabled = (isset($_POST['whatsapp_enabled']) && (int) $_POST['whatsapp_enabled'] === 1) ? 1 : 0;
                $wa_flag_stmt = $conn->prepare('UPDATE tickets SET whatsapp_enabled = ? WHERE id = ?');
                if ($wa_flag_stmt) {
                    $wa_flag_stmt->bind_param('ii', $whatsapp_enabled, $ticket_id);
                    $wa_flag_stmt->execute();
                    $wa_flag_stmt->close();
                }
            }

            // Procesar archivos adjuntos si existen
            if (!empty($_FILES['attachments']['name'][0])) {
                $upload_dir = '../../uploads/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_count = count($_FILES['attachments']['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        $name = basename($_FILES['attachments']['name'][$i]);
                        $tmp_name = $_FILES['attachments']['tmp_name'][$i];
                        $file_type = $_FILES['attachments']['type'][$i];
                        $file_size = $_FILES['attachments']['size'][$i];
                        
                        // Generar nombre único para el archivo
                        $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                        $unique_filename = uniqid() . '_' . $name;
                        
                        // Crear directorio si no existe
                        $year = date('Y');
                        $month = date('m');
                        $upload_path = $upload_dir . 'attachments/' . $year . '/' . $month . '/';
                        if (!file_exists($upload_path)) {
                            mkdir($upload_path, 0777, true);
                        }
                        
                        $file_path = $upload_path . $unique_filename;
                        
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            // Guardar ruta relativa
                            $relative_path = 'uploads/attachments/' . $year . '/' . $month . '/' . $unique_filename;
                            
                            $file_stmt = $conn->prepare("INSERT INTO ticket_attachments (ticket_id, message_id, ticket_message_id, filename, file_name, file_path, mime_type, size, is_inline, created_at) VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, 0, NOW())");
                            if ($file_stmt) {
                                $file_stmt->bind_param("issssi", $ticket_id, $name, $name, $relative_path, $file_type, $file_size);
                                if ($file_stmt->execute()) {
                                    error_log("DEBUG - Archivo guardado en new: $name en $relative_path (ID: " . $file_stmt->insert_id . ")");
                                } else {
                                    error_log("Error al guardar el archivo adjunto: " . $file_stmt->error);
                                }
                                $file_stmt->close();
                            }
                        } else {
                            error_log("Error al mover el archivo subido: $name");
                        }
                    }
                }
            }
            
            // Obtener información del solicitante para el correo
            $requester_query = $conn->prepare("SELECT name, email FROM requesters WHERE id = ?");
            $requester_query->bind_param("i", $requester_id);
            $requester_query->execute();
            $requester_result = $requester_query->get_result();
            $requester_data = $requester_result->fetch_assoc();
            $requester_query->close();
            
            // Obtener información del técnico si está asignado
            $technician_name = null;
            $technician_email = null;
            if (!empty($technician_id)) {
                $tech_query = $conn->prepare("SELECT name, email FROM technicians WHERE id = ?");
                $tech_query->bind_param("i", $technician_id);
                $tech_query->execute();
                $tech_result = $tech_query->get_result();
                $tech_data = $tech_result->fetch_assoc();
                $technician_name = $tech_data ? $tech_data['name'] : null;
                $technician_email = $tech_data ? $tech_data['email'] : null;
                $tech_query->close();
            }
            
            // Registrar en el historial que se creó el ticket
            $user_name = getUserName();
            
            // Si se asignó un técnico, registrar la creación con técnico asignado
            if (!empty($technician_id)) {
                $history_description = 'Ticket creado con técnico asignado: ' . ($technician_name ?: 'Técnico ID ' . $technician_id);
                logTicketHistory($conn, $ticket_id, 'created', $user_name, null, null, null, $history_description);
            } else {
                logTicketCreated($conn, $ticket_id, $user_name);
            }
            
            // Enviar correo de notificación al solicitante
            if ($requester_data && !empty($requester_data['email'])) {
                $email_result = sendTicketCreationNotification(
                    $ticket_id,
                    $ticket_number,
                    $requester_data['email'],
                    $requester_data['name'],
                    $description,
                    $technician_name,
                    $technician_email,
                    $user_empresa_id
                );
                
                if (!$email_result['success']) {
                    error_log("ADVERTENCIA - No se pudo enviar correo de notificación para ticket #$ticket_number: " . $email_result['message']);
                }
            }
            
            // Procesar el costo si se proporcionó
            if (!empty($cost_description) && !empty($cost_amount)) {
                // Verificar/crear la tabla ticket_cost_attachments si no existe
                $table_check = $conn->query("SHOW TABLES LIKE 'ticket_cost_attachments'");
                if ($table_check->num_rows === 0) {
                    $create_table = "CREATE TABLE `ticket_cost_attachments` (
                        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        `cost_id` bigint(20) UNSIGNED NOT NULL,
                        `file_name` varchar(255) NOT NULL,
                        `file_content` longblob NOT NULL,
                        `file_type` varchar(100) DEFAULT NULL,
                        `file_size` int(11) DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT current_timestamp(),
                        PRIMARY KEY (`id`),
                        KEY `cost_id` (`cost_id`),
                        CONSTRAINT `fk_cost_attachments` FOREIGN KEY (`cost_id`) REFERENCES `ticket_costs` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                    
                    if (!$conn->query($create_table)) {
                        error_log('Error al crear la tabla ticket_cost_attachments: ' . $conn->error);
                    }
                }
                
                // Insertar el costo sin archivos primero
                $cost_query = "INSERT INTO ticket_costs (ticket_id, description, amount) VALUES (?, ?, ?)";
                $cost_stmt = $conn->prepare($cost_query);
                $cost_stmt->bind_param("isd", $ticket_id, $cost_description, $cost_amount);
                
                if ($cost_stmt->execute()) {
                    $cost_id = $conn->insert_id;
                    $cost_stmt->close();
                    
                    // Procesar múltiples archivos si existen
                    $files_uploaded = 0;
                    if (isset($_FILES['cost_files']) && is_array($_FILES['cost_files']['name'])) {
                        $file_count = count($_FILES['cost_files']['name']);
                        
                        for ($i = 0; $i < $file_count; $i++) {
                            // Verificar que el archivo se subió correctamente
                            if ($_FILES['cost_files']['error'][$i] === UPLOAD_ERR_OK) {
                                $file_name = $_FILES['cost_files']['name'][$i];
                                $file_type = $_FILES['cost_files']['type'][$i];
                                $file_size = $_FILES['cost_files']['size'][$i];
                                $file_tmp = $_FILES['cost_files']['tmp_name'][$i];
                                $file_content = file_get_contents($file_tmp);
                                
                                // Insertar archivo en ticket_cost_attachments
                                $file_stmt = $conn->prepare("INSERT INTO ticket_cost_attachments (cost_id, file_name, file_content, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                                $file_stmt->bind_param("isssi", $cost_id, $file_name, $file_content, $file_type, $file_size);
                                
                                if ($file_stmt->execute()) {
                                    $files_uploaded++;
                                } else {
                                    error_log("Error al guardar archivo de costo '$file_name': " . $file_stmt->error);
                                }
                                
                                $file_stmt->close();
                            }
                        }
                    }
                    
                    if ($files_uploaded > 0) {
                        error_log("Costo creado con $files_uploaded archivo(s) para ticket #$ticket_id");
                    }
                } else {
                    error_log("Error al crear el costo: " . $cost_stmt->error);
                    $cost_stmt->close();
                }
            }
            
            // Registrar en el error log que el ticket se creó correctamente
            //error_log('[Tickets] Ticket creado correctamente. ID=' . $ticket_id . ', usuario=' . $user_name . ', ip=' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'));
            
            $message = "Ticket #$ticket_number creado correctamente";
            $messageType = "success";
            header("refresh:2;url=tickets.php");
        } else {
            // No se pudo insertar después de todos los reintentos
            $message = "Error al crear el ticket: No se pudo generar un número de ticket único después de varios intentos. Por favor, intente nuevamente.";
            $messageType = "danger";
            error_log("ERROR CRÍTICO: No se pudo crear ticket después de {$max_retries} intentos para empresa {$user_empresa_id}");
        }
    }
}
?>
<?php include '../../includes/modern-header.php'; ?>
<style>
.body-bg {
    background: #f7f8fa;
    min-height: 100vh;
    width: 100%;
    overflow-x: hidden;
}
.main-content.main-content-boxed {
    max-width: 1120px;
    margin-top: 2rem;
    margin-bottom: 2rem;
    margin-right: auto;
    padding: 2.5rem 2rem;
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 24px 0 rgba(32,38,84,0.07);
    min-height: 80vh;
}

/* Estilos para modales - evitar superposición con header */
.modal {
    z-index: 1050 !important;
}
.modal-backdrop {
    z-index: 1040 !important;
}

/* Navbar/header global (no aplicar a paneles del formulario) */
.navbar, .modern-header {
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

/* Estilos para textareas auto-expandibles */
.auto-expand {
    resize: none;
    overflow-y: hidden;
    transition: height 0.2s ease;
    min-height: calc(1.5em * 3 + 0.75rem + 2px); /* Altura mínima para 3 filas */
}

.auto-expand:focus {
    outline: none;
    border-color: #93a4e0;
    box-shadow: 0 0 0 0.2rem rgba(32, 38, 84, 0.12);
}

/* Formulario nuevo ticket — mismos paneles que edit.php */
.ticket-edit-form-card {
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
}
.ticket-edit-form-card > .card-body { padding: 0 !important; }
.ticket-edit-panel {
    height: 100%;
    background: #fff;
    border: 1px solid #e6ebf2;
    border-radius: 14px;
    box-shadow: 0 2px 10px rgba(23, 32, 51, 0.04);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.ticket-edit-panel-header {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1.1rem 1.2rem;
    border-bottom: 1px solid #edf1f6;
    background: linear-gradient(180deg, #fbfcfe 0%, #f7f9fc 100%);
}
.ticket-edit-panel-icon {
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #202654;
    color: #fff;
    flex-shrink: 0;
    font-size: 0.95rem;
}
.ticket-edit-panel-icon--priority { background: #3a4478; }
.ticket-edit-panel-icon--details { background: #475569; }
.ticket-edit-panel-icon--costs { background: #0f766e; }
.ticket-edit-panel-header h4,
.ticket-edit-panel-header h5 {
    margin: 0;
    border: 0;
    padding: 0;
    font-size: 1rem;
    font-weight: 750;
    color: #202654;
    letter-spacing: -0.01em;
}
.ticket-edit-panel-subtitle {
    margin: 0.2rem 0 0;
    font-size: 0.8rem;
    color: #64748b;
}
.ticket-edit-panel-body {
    padding: 1.15rem 1.2rem 1.25rem;
    flex: 1 1 auto;
}
.ticket-edit-panel-body .mb-3 { margin-bottom: 1rem !important; }
.ticket-edit-panel-body .mb-3:last-child { margin-bottom: 0 !important; }
.ticket-edit-panel .form-label {
    font-size: 0.84rem;
    font-weight: 650;
    color: #334155;
    margin-bottom: 0.4rem;
}
.ticket-edit-panel .form-control,
.ticket-edit-panel .form-select {
    border-radius: 10px;
    border-color: #d7dee8;
    min-height: 2.55rem;
    padding: 0.6rem 0.85rem;
}
.ticket-edit-panel .form-control:focus,
.ticket-edit-panel .form-select:focus {
    border-color: #93a4e0;
    box-shadow: 0 0 0 0.2rem rgba(32, 38, 84, 0.12);
}
.ticket-edit-panel textarea.form-control { min-height: 120px; }
.ticket-edit-form .form-actions {
    margin-top: 1.25rem !important;
    padding: 1rem 0 0;
    border-top: 1px solid #e6ebf2;
}
@media (max-width: 767.98px) {
    .ticket-edit-columns > [class*="col-"] { margin-bottom: 0.85rem; }
}
/*  segundos para los selects */
.select-delay-options option {
    visibility: hidden;
}

.select-delay-options.show-options option {
    visibility: visible;
}


</style>
<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/modern-sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="tickets-page-header">
                <div>
                    <h1 class="h2 mb-2">Crear Nuevo Ticket</h1>
                    <p class="text-muted mb-0">Completa el formulario para registrar un nuevo ticket</p>
                </div>
                <div class="tickets-page-actions">
                    <a href="tickets.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                </div>
            </div>
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            <!-- Aquí va el formulario de creación, manteniendo la estructura pero aplicando clases modernas a inputs, selects y botones -->
            <div class="card ticket-edit-form-card">
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" class="ticket-edit-form">
                            <div class="row g-3 ticket-edit-columns">
                                <div class="col-md-6">
                                    <section class="ticket-edit-panel">
                                        <div class="ticket-edit-panel-header">
                                            <span class="ticket-edit-panel-icon" aria-hidden="true"><i class="fas fa-info-circle"></i></span>
                                            <div>
                                                <h4>Información Básica</h4>
                                                <p class="ticket-edit-panel-subtitle">Solicitante, categoría, estado y asignación</p>
                                            </div>
                                        </div>
                                        <div class="ticket-edit-panel-body">
                                    <div class="mb-3">
                                        <label for="requester_id" class="form-label">Solicitante *</label>
                                        <select class="form-select" id="requester_id" name="requester_id" required>
                                            <option value="">Seleccione un solicitante</option>
                                            <?php while ($requester = $requesters->fetch_assoc()): ?>
                                                <option value="<?php echo $requester['id']; ?>">
                                                    <?php echo htmlspecialchars($requester['name']); ?>
                                                    <?php if (!empty($requester['company_name'])): ?>
                                                        - <?php echo htmlspecialchars($requester['company_name']); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Categoría</label>
                                        <select class="form-select" id="category_id" name="category_id">
                                            <option value="">Seleccione una categoría</option>
                                            <?php while ($category = $categories->fetch_assoc()): ?>
                                                <option value="<?php echo $category['id']; ?>">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status_id" class="form-label">Estado</label>
                                        <select class="form-select" id="status_id" name="status_id">
                                            <option value="">Seleccione un estado</option>
                                            <?php while ($status = $statuses->fetch_assoc()): ?>
                                                <option value="<?php echo $status['id']; ?>">
                                                    <?php echo htmlspecialchars($status['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="contact_mode_id" class="form-label">Modo de Contacto</label>
                                        <select class="form-select" id="contact_mode_id" name="contact_mode_id">
                                            <option value="">Seleccione un modo de contacto</option>
                                            <?php while ($contact_mode = $contact_modes->fetch_assoc()): ?>
                                                <option value="<?php echo $contact_mode['id']; ?>">
                                                    <?php echo htmlspecialchars($contact_mode['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <!--<?php if (columnExists($conn, 'tickets', 'whatsapp_enabled')): ?>
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="whatsapp_enabled" value="0">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                role="switch"
                                                id="whatsapp_enabled"
                                                name="whatsapp_enabled"
                                                value="1"
                                            >
                                            <label class="form-check-label" for="whatsapp_enabled">
                                                Habilitar conversación por WhatsApp
                                            </label>
                                        </div>
                                        <div class="form-text">
                                            Si está activo, en la vista del ticket se muestra el chat y se pueden enviar mensajes por WhatsApp.
                                        </div>
                                    </div>
                                    <?php endif; ?> -->
                                    
                                    <div class="mb-3">
                                        <label for="technician_id" class="form-label">Técnico Asignado</label>
                                        <select class="form-select" id="technician_id" name="technician_id">
                                            <option value="">Seleccione un técnico</option>
                                            <?php while ($technician = $technicians->fetch_assoc()): ?>
                                                <option value="<?php echo $technician['id']; ?>">
                                                    <?php echo htmlspecialchars($technician['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                        </div>
                                    </section>
                                </div>

                                <div class="col-md-6">
                                    <section class="ticket-edit-panel">
                                        <div class="ticket-edit-panel-header">
                                            <span class="ticket-edit-panel-icon ticket-edit-panel-icon--priority" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                                            <div>
                                                <h4>Clasificación y Prioridad</h4>
                                                <p class="ticket-edit-panel-subtitle">Nivel, impacto y prioridad del ticket</p>
                                            </div>
                                        </div>
                                        <div class="ticket-edit-panel-body">
                                    <div class="mb-3">
                                        <label for="classification_id" class="form-label">Clasificación</label>
                                        <select class="form-select" id="classification_id" name="classification_id">
                                            <option value="">Seleccione una clasificación</option>
                                            <?php while ($classification = $classifications->fetch_assoc()): ?>
                                                <option value="<?php echo $classification['id']; ?>">
                                                    <?php echo htmlspecialchars($classification['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="level_id" class="form-label">Nivel</label>
                                        <select class="form-select" id="level_id" name="level_id">
                                            <option value="">Seleccione un nivel</option>
                                            <?php while ($level = $levels->fetch_assoc()): ?>
                                                <option value="<?php echo $level['id']; ?>">
                                                    <?php echo htmlspecialchars($level['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="impact_id" class="form-label">Impacto</label>
                                        <select class="form-select" id="impact_id" name="impact_id">
                                            <option value="">Seleccione un impacto</option>
                                            <?php while ($impact = $impacts->fetch_assoc()): ?>
                                                <option value="<?php echo $impact['id']; ?>">
                                                    <?php echo htmlspecialchars($impact['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- <div class="mb-3">
                                        <label for="urgency_id" class="form-label">Urgencia</label>
                                        <select class="form-select" id="urgency_id" name="urgency_id">
                                            <option value="">Seleccione una urgencia</option>
                                            <?php while ($urgency = $urgencies->fetch_assoc()): ?>
                                                <option value="<?php echo $urgency['id']; ?>">
                                                    <?php echo htmlspecialchars($urgency['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div> -->
                                    
                                    <div class="mb-3">
                                        <label for="priority_id" class="form-label">Prioridad</label>
                                        <select class="form-select" id="priority_id" name="priority_id">
                                            <option value="">Seleccione una prioridad</option>
                                            <?php while ($priority = $priorities->fetch_assoc()): ?>
                                                <option value="<?php echo $priority['id']; ?>">
                                                    <?php echo htmlspecialchars($priority['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                        </div>
                                    </section>
                                </div>
                            </div>

                            <section class="ticket-edit-panel mt-3">
                                <div class="ticket-edit-panel-header">
                                    <span class="ticket-edit-panel-icon ticket-edit-panel-icon--details" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                                    <div>
                                        <h4>Detalles del Ticket</h4>
                                        <p class="ticket-edit-panel-subtitle">Descripción y archivos adjuntos</p>
                                    </div>
                                </div>
                                <div class="ticket-edit-panel-body">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Descripción del Problema *</label>
                                        <textarea class="form-control auto-expand" id="description" name="description" rows="5" required></textarea>
                                    </div>
                                    
                                    <!-- <div class="mb-3">
                                        <label for="impact_details" class="form-label">Detalles del Impacto</label>
                                        <textarea class="form-control" id="impact_details" name="impact_details" rows="3"></textarea>
                                    </div> -->
                                    
                                    <!--<div class="mb-3">
                                        <label for="resolution" class="form-label">Resolución</label>
                                        <textarea class="form-control auto-expand" id="resolution" name="resolution" rows="3"></textarea>
                                    </div>-->
                                    
                                    <div class="mb-3">
                                        <label for="attachments" class="form-label">
                                            <i class="fas fa-paperclip"></i> Archivos Adjuntos
                                        </label>
                                        <input class="form-control" type="file" id="attachments" name="attachments[]" multiple>
                                        <div class="form-text">Puede seleccionar múltiples archivos (máximo 40MB en total).</div>
                                    </div>
                                    
                                    <!-- Preview de archivos seleccionados -->
                                    <div id="files_preview" class="mb-3" style="display: none;">
                                        <label class="form-label text-success">
                                            <i class="fas fa-check-circle"></i> Archivos seleccionados:
                                        </label>
                                        <div id="files_list" class="list-group">
                                            <!-- Los archivos se mostrarán aquí -->
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="ticket-edit-panel mt-3 ticket-costs-card">
                                <div class="ticket-edit-panel-header ticket-costs-header">
                                    <span class="ticket-edit-panel-icon ticket-edit-panel-icon--costs" aria-hidden="true"><i class="fas fa-dollar-sign"></i></span>
                                    <div>
                                        <h5 class="mb-0">Costos del Ticket</h5>
                                        <p class="ticket-edit-panel-subtitle">Opcional al crear el ticket</p>
                                    </div>
                                </div>
                                <div class="ticket-edit-panel-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="cost_description" class="form-label">Descripción del costo</label>
                                                <input type="text" class="form-control" id="cost_description" name="cost_description">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="cost_amount" class="form-label">Monto</label>
                                                <input type="number" class="form-control" id="cost_amount" name="cost_amount" step="0.01">
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="mb-3">
                                                <label for="cost_files" class="form-label">
                                                    <i class="fas fa-paperclip"></i> Archivos adjuntos (opcional)
                                                </label>
                                                <input type="file" class="form-control" id="cost_files" name="cost_files[]" multiple>
                                                <small class="text-muted">Puede seleccionar múltiples archivos</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Preview de archivos de costo seleccionados -->
                                    <div id="cost_files_preview" class="mb-3" style="display: none;">
                                        <label class="form-label text-success">
                                            <i class="fas fa-check-circle"></i> Archivos seleccionados para el costo:
                                        </label>
                                        <div id="cost_files_list" class="list-group">
                                            <!-- Los archivos se mostrarán aquí -->
                                        </div>
                                    </div>
                                </div>
                            </section>
                            <div class="mt-4">
                                <div class="mt-4 form-actions d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Crear Ticket</button>
                                <a href="tickets.php" class="btn btn-secondary">Cancelar</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>

<script>
// Formatear tamaño de archivo
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('attachments');
    const previewContainer = document.getElementById('files_preview');
    const filesList = document.getElementById('files_list');
    const costFileInput = document.getElementById('cost_files');
    const costPreviewContainer = document.getElementById('cost_files_preview');
    const costFilesList = document.getElementById('cost_files_list');
    const form = document.querySelector('form');
    
    // Límites de PHP (en bytes)
    const MAX_FILE_SIZE = 40 * 1024 * 1024; // 40MB por archivo
    const MAX_POST_SIZE = 40 * 1024 * 1024; // 40MB total POST
    
    // Preview de archivos seleccionados
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            
            if (files.length > 0) {
                filesList.innerHTML = '';
                let totalSize = 0;
                let hasOversizedFile = false;
                
                Array.from(files).forEach((file, index) => {
                    totalSize += file.size;
                    const isTooBig = file.size > MAX_FILE_SIZE;
                    if (isTooBig) hasOversizedFile = true;
                    
                    const fileItem = document.createElement('div');
                    fileItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                    
                    if (isTooBig) {
                        fileItem.classList.add('border-danger');
                        fileItem.innerHTML = `
                            <div>
                                <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
                                ${file.name}
                                <small class="text-danger ms-2">(${formatFileSize(file.size)} - ¡Demasiado grande!)</small>
                            </div>
                            <span class="badge bg-danger">Excede 40MB</span>
                        `;
                    } else {
                        fileItem.innerHTML = `
                            <div>
                                <i class="fas fa-file me-2 text-success"></i>
                                ${file.name}
                                <small class="text-muted ms-2">(${formatFileSize(file.size)})</small>
                            </div>
                            <span class="badge bg-success">Listo</span>
                        `;
                    }
                    filesList.appendChild(fileItem);
                });
                
                // Mostrar advertencia si el tamaño total excede el límite
                if (totalSize > MAX_POST_SIZE || hasOversizedFile) {
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'alert alert-danger mt-2 mb-0';
                    warningDiv.innerHTML = `
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>¡Advertencia!</strong><br>
                        ${hasOversizedFile ? '• Uno o más archivos exceden el límite de 40MB por archivo.<br>' : ''}
                        ${totalSize > MAX_POST_SIZE ? '• El tamaño total (' + formatFileSize(totalSize) + ') excede el límite de 40MB.<br>' : ''}
                        <small>Por favor, reduce el tamaño o la cantidad de archivos.</small>
                    `;
                    filesList.appendChild(warningDiv);
                }
                
                // Mostrar información del tamaño total
                const totalDiv = document.createElement('div');
                totalDiv.className = 'mt-2 p-2 bg-light rounded';
                totalDiv.innerHTML = `
                    <small>
                        <strong>Total:</strong> ${files.length} archivo(s) - ${formatFileSize(totalSize)}
                        ${totalSize > MAX_POST_SIZE ? '<span class="text-danger ms-2">⚠️ Excede el límite</span>' : '<span class="text-success ms-2">✓ Dentro del límite</span>'}
                    </small>
                `;
                filesList.appendChild(totalDiv);
                
                previewContainer.style.display = 'block';
            } else {
                previewContainer.style.display = 'none';
            }
        });
    }
    
    // Preview de archivos de costos seleccionados
    if (costFileInput) {
        costFileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            
            if (files.length > 0) {
                costFilesList.innerHTML = '';
                let totalSize = 0;
                let hasOversizedFile = false;
                
                Array.from(files).forEach((file, index) => {
                    totalSize += file.size;
                    const isTooBig = file.size > MAX_FILE_SIZE;
                    if (isTooBig) hasOversizedFile = true;
                    
                    const fileItem = document.createElement('div');
                    fileItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                    
                    if (isTooBig) {
                        fileItem.classList.add('border-danger');
                        fileItem.innerHTML = `
                            <div>
                                <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
                                ${file.name}
                                <small class="text-danger ms-2">(${formatFileSize(file.size)} - ¡Demasiado grande!)</small>
                            </div>
                            <span class="badge bg-danger">Excede 40MB</span>
                        `;
                    } else {
                        fileItem.innerHTML = `
                            <div>
                                <i class="fas fa-file me-2 text-primary"></i>
                                ${file.name}
                                <small class="text-muted ms-2">(${formatFileSize(file.size)})</small>
                            </div>
                            <span class="badge bg-primary">Listo</span>
                        `;
                    }
                    costFilesList.appendChild(fileItem);
                });
                
                // Mostrar advertencia si el tamaño total excede el límite
                if (totalSize > MAX_POST_SIZE || hasOversizedFile) {
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'alert alert-warning mt-2 mb-0';
                    warningDiv.innerHTML = `
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>¡Advertencia!</strong><br>
                        ${hasOversizedFile ? '• Uno o más archivos exceden el límite de 40MB por archivo.<br>' : ''}
                        ${totalSize > MAX_POST_SIZE ? '• El tamaño total (' + formatFileSize(totalSize) + ') excede el límite de 40MB.<br>' : ''}
                        <small>Por favor, reduce el tamaño o la cantidad de archivos.</small>
                    `;
                    costFilesList.appendChild(warningDiv);
                }
                
                // Mostrar información del tamaño total
                const totalDiv = document.createElement('div');
                totalDiv.className = 'mt-2 p-2 bg-light rounded';
                totalDiv.innerHTML = `
                    <small>
                        <strong>Total archivos de costo:</strong> ${files.length} archivo(s) - ${formatFileSize(totalSize)}
                        ${totalSize > MAX_POST_SIZE ? '<span class="text-danger ms-2">⚠️ Excede el límite</span>' : '<span class="text-success ms-2">✓ Dentro del límite</span>'}
                    </small>
                `;
                costFilesList.appendChild(totalDiv);
                
                costPreviewContainer.style.display = 'block';
            } else {
                costPreviewContainer.style.display = 'none';
            }
        });
    }
    
    // Validar antes de enviar el formulario
    if (form) {
        form.addEventListener('submit', function(e) {
            let hasError = false;
            let errorMessage = '';
            
            // Validar archivos del ticket
            if (fileInput && fileInput.files.length > 0) {
                let totalSize = 0;
                let hasOversizedFile = false;
                
                Array.from(fileInput.files).forEach(file => {
                    totalSize += file.size;
                    if (file.size > MAX_FILE_SIZE) {
                        hasOversizedFile = true;
                    }
                });
                
                if (hasOversizedFile || totalSize > MAX_POST_SIZE) {
                    hasError = true;
                    errorMessage += 'ARCHIVOS DEL TICKET:\n' +
                          '• Tamaño total: ' + formatFileSize(totalSize) + '\n' +
                          '• Límite permitido: 40MB\n\n';
                }
            }
            
            // Validar archivos de costos
            if (costFileInput && costFileInput.files.length > 0) {
                let totalCostSize = 0;
                let hasOversizedCostFile = false;
                
                Array.from(costFileInput.files).forEach(file => {
                    totalCostSize += file.size;
                    if (file.size > MAX_FILE_SIZE) {
                        hasOversizedCostFile = true;
                    }
                });
                
                if (hasOversizedCostFile || totalCostSize > MAX_POST_SIZE) {
                    hasError = true;
                    errorMessage += 'ARCHIVOS DE COSTOS:\n' +
                          '• Tamaño total: ' + formatFileSize(totalCostSize) + '\n' +
                          '• Límite permitido: 40MB\n\n';
                }
            }
            
            if (hasError) {
                e.preventDefault();
                alert('ERROR: Los archivos seleccionados exceden el límite permitido.\n\n' +
                      errorMessage +
                      'Por favor, reduce el tamaño o cantidad de archivos, o configura límites mayores en PHP.');
                return false;
            }
        });
    }
    
    // Función para auto-expandir textareas
    function autoExpandTextarea(textarea) {
        // Resetear altura para obtener el scrollHeight correcto
        textarea.style.height = 'auto';
        // Ajustar altura al contenido, con un mínimo de rows iniciales
        const minHeight = parseInt(getComputedStyle(textarea).lineHeight) * parseInt(textarea.getAttribute('rows') || 3);
        textarea.style.height = Math.max(minHeight, textarea.scrollHeight) + 'px';
    }
    
    // Aplicar auto-expansión a todos los textareas con clase auto-expand
    const autoExpandTextareas = document.querySelectorAll('.auto-expand');
    
    autoExpandTextareas.forEach(textarea => {
        // Ajustar altura inicial
        autoExpandTextarea(textarea);
        
        // Ajustar altura al escribir
        textarea.addEventListener('input', function() {
            autoExpandTextarea(this);
        });
        
        // Ajustar altura al pegar contenido
        textarea.addEventListener('paste', function() {
            setTimeout(() => autoExpandTextarea(this), 10);
        });
    });
});
//  aparición de las opciones de TODOS los select
document.addEventListener('DOMContentLoaded', function () {
    const selects = document.querySelectorAll('select');
    selects.forEach(function (select) {
        select.classList.add('select-delay-options');
        select.addEventListener('mousedown', function () {

            this.classList.remove('show-options');

            setTimeout(() => {
                this.classList.add('show-options');
            }, 1000);

        });

        select.addEventListener('focus', function () {
            this.classList.remove('show-options');
            setTimeout(() => {
                this.classList.add('show-options');
            }, 1000);
        });
    });
});
</script>
</body>
</html>