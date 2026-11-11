<?php
/**
 * Configuración de Email por Empresa
 * Permite configurar SMTP e IMAP para cada empresa
 */

include '../../includes/common.php';
include '../../conexion/db.php';
require_once '../../conexion/get_empresa_email_config.php';
require_once '../../../auth.php';
checkAdmin();

$message = '';
$messageType = '';

// Obtener todas las empresas de empresas_usuarios
$empresas_query = "SELECT id_empresa, nombre FROM empresas_usuarios ORDER BY nombre";
$empresas_result = $conn->query($empresas_query);
$empresas = [];
while ($row = $empresas_result->fetch_assoc()) {
    $empresas[] = $row;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $id_empresa = intval($_POST['id_empresa'] ?? 0);
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_port = !empty($_POST['smtp_port']) ? intval($_POST['smtp_port']) : 587;
        $smtp_username = trim($_POST['smtp_username'] ?? '');
        $smtp_password = trim($_POST['smtp_password'] ?? '');
        $smtp_from_email = trim($_POST['smtp_from_email'] ?? '');
        $smtp_from_name = trim($_POST['smtp_from_name'] ?? 'Sistema de Tickets');
        $smtp_from_email = resolveSmtpFromEmail([
            'smtp_username' => $smtp_username,
            'smtp_from_email' => $smtp_from_email,
        ]);
        
        $imap_host = trim($_POST['imap_host'] ?? '');
        $imap_port = !empty($_POST['imap_port']) ? intval($_POST['imap_port']) : 993;
        $imap_username = trim($_POST['imap_username'] ?? '');
        $imap_password = trim($_POST['imap_password'] ?? '');
        $imap_folder = trim($_POST['imap_folder'] ?? 'INBOX');
        $imap_encryption = trim($_POST['imap_encryption'] ?? '/imap/ssl');
        $imap_options = trim($_POST['imap_options'] ?? '/imap/ssl/validate-cert');
        
        // Debug: Verificar valores recibidos
        // error_log("DEBUG empresa_email_config - smtp_from_email recibido: '" . $smtp_from_email . "'");
        // error_log("DEBUG empresa_email_config - imap_username recibido: '" . $imap_username . "'");
        // error_log("DEBUG empresa_email_config - imap_encryption recibido: '" . $imap_encryption . "'");
        // error_log("DEBUG empresa_email_config - POST smtp_from_email: " . var_export($_POST['smtp_from_email'] ?? 'NO EXISTE', true));
        // error_log("DEBUG empresa_email_config - POST imap_username: " . var_export($_POST['imap_username'] ?? 'NO EXISTE', true));
        // error_log("DEBUG empresa_email_config - POST imap_encryption: " . var_export($_POST['imap_encryption'] ?? 'NO EXISTE', true));
        
        $activo = isset($_POST['activo']) && $_POST['activo'] == 'on' ? 1 : 0;
        
        // Validar campos requeridos
        if (empty($id_empresa) || empty($smtp_host) || empty($smtp_username) || empty($imap_host) || empty($imap_username)) {
            $message = 'Por favor complete todos los campos requeridos';
            $messageType = 'danger';
        } else {
            // Obtener campos adicionales de procesamiento
            $processing_enabled = isset($_POST['processing_enabled']) && $_POST['processing_enabled'] == 'on' ? 1 : 0;
            $max_emails_per_run = !empty($_POST['max_emails_per_run']) ? intval($_POST['max_emails_per_run']) : 50;
            $processing_interval_minutes = !empty($_POST['processing_interval_minutes']) ? intval($_POST['processing_interval_minutes']) : 5;
            
            // Verificar si ya existe configuración para esta empresa
            $check_stmt = $conn->prepare("SELECT id, smtp_password, imap_password FROM empresa_email_config WHERE id_empresa = ?");
            $check_stmt->bind_param("i", $id_empresa);
            $check_stmt->execute();
            $existing = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            if ($existing) {
                // Si las contraseñas están vacías al editar, mantener las existentes
                if (empty($smtp_password)) {
                    $smtp_password = $existing['smtp_password'];
                }
                if (empty($imap_password)) {
                    $imap_password = $existing['imap_password'];
                }
                
                // Actualizar configuración existente
                // Asegurar que los valores string no estén vacíos (usar cadena vacía en lugar de null)
                $smtp_from_email = (string)($smtp_from_email !== null && $smtp_from_email !== '' ? $smtp_from_email : '');
                $imap_username = (string)($imap_username !== null && $imap_username !== '' ? $imap_username : '');
                $imap_encryption = (string)($imap_encryption !== null && $imap_encryption !== '' ? $imap_encryption : '/imap/ssl');
                
                // Debug: Verificar valores antes de bind_param
                // error_log("DEBUG antes de bind_param UPDATE:");
                // error_log("  smtp_from_email: '$smtp_from_email' (tipo: " . gettype($smtp_from_email) . ", longitud: " . strlen($smtp_from_email) . ")");
                // error_log("  imap_username: '$imap_username' (tipo: " . gettype($imap_username) . ", longitud: " . strlen($imap_username) . ")");
                // error_log("  imap_encryption: '$imap_encryption' (tipo: " . gettype($imap_encryption) . ", longitud: " . strlen($imap_encryption) . ")");
                
                // Crear copias de las variables para evitar problemas de referencia
                $bind_smtp_from_email = $smtp_from_email;
                $bind_imap_username = $imap_username;
                $bind_imap_encryption = $imap_encryption;
                
                // Escapar valores para SQL (aunque usaremos prepared statements, esto es para debug)
                $smtp_from_email_escaped = $conn->real_escape_string($bind_smtp_from_email);
                $imap_username_escaped = $conn->real_escape_string($bind_imap_username);
                $imap_encryption_escaped = $conn->real_escape_string($bind_imap_encryption);
                
                // error_log("DEBUG valores escapados:");
                // error_log("  smtp_from_email_escaped: '$smtp_from_email_escaped'");
                // error_log("  imap_username_escaped: '$imap_username_escaped'");
                // error_log("  imap_encryption_escaped: '$imap_encryption_escaped'");
                
                $update_stmt = $conn->prepare("UPDATE empresa_email_config SET 
                    smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?,
                    smtp_from_email = ?, smtp_from_name = ?,
                    imap_host = ?, imap_port = ?, imap_username = ?, imap_password = ?,
                    imap_folder = ?, imap_encryption = ?, imap_options = ?,
                    activo = ?, processing_enabled = ?, max_emails_per_run = ?, processing_interval_minutes = ?
                    WHERE id_empresa = ?");
                
                // Verificar que la preparación fue exitosa
                if (!$update_stmt) {
                    // error_log("ERROR al preparar UPDATE: " . $conn->error);
                    $message = 'Error al preparar la consulta: ' . $conn->error;
                    $messageType = 'danger';
                } else {
                    // Debug: Listar todos los valores en orden antes de bind_param
                    $bind_values = [
                        'smtp_host' => $smtp_host,
                        'smtp_port' => $smtp_port,
                        'smtp_username' => $smtp_username,
                        'smtp_password' => substr($smtp_password, 0, 5) . '...', // Solo primeros 5 chars por seguridad
                        'smtp_from_email' => $bind_smtp_from_email,
                        'smtp_from_name' => $smtp_from_name,
                        'imap_host' => $imap_host,
                        'imap_port' => $imap_port,
                        'imap_username' => $bind_imap_username,
                        'imap_password' => substr($imap_password, 0, 5) . '...',
                        'imap_folder' => $imap_folder,
                        'imap_encryption' => $bind_imap_encryption,
                        'imap_options' => $imap_options,
                        'activo' => $activo,
                        'processing_enabled' => $processing_enabled,
                        'max_emails_per_run' => $max_emails_per_run,
                        'processing_interval_minutes' => $processing_interval_minutes,
                        'id_empresa' => $id_empresa
                    ];
                    // error_log("DEBUG valores en orden para bind_param: " . json_encode($bind_values, JSON_UNESCAPED_UNICODE));
                    
                    // Usar call_user_func_array para pasar parámetros de forma más explícita
                    // IMPORTANTE: el orden debe coincidir exactamente con el SQL
                    // String de tipos correcto: sisssssisssssiiiii (18 caracteres)
                    $bind_params = [
                        "sisssssisssssiiiii",  // String de tipos CORREGIDO
                        &$smtp_host,           // 1: smtp_host (s)
                        &$smtp_port,          // 2: smtp_port (i)
                        &$smtp_username,      // 3: smtp_username (s)
                        &$smtp_password,      // 4: smtp_password (s)
                        &$bind_smtp_from_email, // 5: smtp_from_email (s)
                        &$smtp_from_name,     // 6: smtp_from_name (s)
                        &$imap_host,          // 7: imap_host (s)
                        &$imap_port,          // 8: imap_port (i)
                        &$bind_imap_username, // 9: imap_username (s)
                        &$imap_password,      // 10: imap_password (s)
                        &$imap_folder,        // 11: imap_folder (s)
                        &$bind_imap_encryption, // 12: imap_encryption (s)
                        &$imap_options,       // 13: imap_options (s)
                        &$activo,             // 14: activo (i)
                        &$processing_enabled, // 15: processing_enabled (i)
                        &$max_emails_per_run, // 16: max_emails_per_run (i)
                        &$processing_interval_minutes, // 17: processing_interval_minutes (i)
                        &$id_empresa          // 18: id_empresa WHERE (i)
                    ];
                    
                    // Debug: Verificar valores antes de bind_param con call_user_func_array
                    // error_log("DEBUG antes de call_user_func_array bind_param:");
                    // error_log("  bind_smtp_from_email: '" . $bind_smtp_from_email . "'");
                    // error_log("  bind_imap_username: '" . $bind_imap_username . "'");
                    // error_log("  bind_imap_encryption: '" . $bind_imap_encryption . "'");
                    // error_log("  Número de parámetros: " . (count($bind_params) - 1)); // -1 porque el primer elemento es el string de tipos
                    
                    $bind_result = call_user_func_array([$update_stmt, 'bind_param'], $bind_params);
                    
                    // Debug: Verificar valores después de bind_param
                    // error_log("DEBUG después de bind_param:");
                    // error_log("  bind_smtp_from_email: '$bind_smtp_from_email' (longitud: " . strlen($bind_smtp_from_email) . ")");
                    // error_log("  bind_imap_username: '$bind_imap_username' (longitud: " . strlen($bind_imap_username) . ")");
                    // error_log("  bind_imap_encryption: '$bind_imap_encryption' (longitud: " . strlen($bind_imap_encryption) . ")");
                    // error_log("  bind_result: " . ($bind_result ? 'true' : 'false'));
                    
                    if (!$bind_result) {
                        error_log("ERROR al hacer bind_param UPDATE: " . $update_stmt->error);
                        $message = 'Error al vincular parámetros: ' . $update_stmt->error;
                        $messageType = 'danger';
                    }
                }
                
                if (isset($update_stmt) && $update_stmt) {
                    // Verificar que bind_param fue exitoso antes de ejecutar
                    if (!isset($bind_result) || !$bind_result) {
                        error_log("ERROR: bind_param falló antes de execute");
                        $message = 'Error al vincular parámetros';
                        $messageType = 'danger';
                    } else {
                        if ($update_stmt->execute()) {
                            // Verificar qué se guardó realmente
                            $verify_stmt = $conn->prepare("SELECT smtp_from_email, imap_username, imap_encryption FROM empresa_email_config WHERE id_empresa = ?");
                            $verify_stmt->bind_param("i", $id_empresa);
                            $verify_stmt->execute();
                            $verify_result = $verify_stmt->get_result();
                            if ($verify_row = $verify_result->fetch_assoc()) {
                                // error_log("DEBUG valores guardados en BD:");
                                // error_log("  smtp_from_email: '" . $verify_row['smtp_from_email'] . "'");
                                // error_log("  imap_username: '" . $verify_row['imap_username'] . "'");
                                // error_log("  imap_encryption: '" . $verify_row['imap_encryption'] . "'");
                            }
                            $verify_stmt->close();
                            
                            $message = 'Configuración actualizada correctamente';
                            $messageType = 'success';
                            // Redirigir para recargar los datos desde la base de datos
                            header("Location: empresa_email_config.php?edit=" . $id_empresa . "&saved=1");
                            exit();
                        } else {
                            $message = 'Error al actualizar: ' . $update_stmt->error;
                            $messageType = 'danger';
                            // error_log("ERROR al ejecutar UPDATE: " . $update_stmt->error);
                            // error_log("MySQL Error: " . $conn->error);
                            // error_log("Valores intentados - bind_smtp_from_email: '$bind_smtp_from_email', bind_imap_username: '$bind_imap_username', bind_imap_encryption: '$bind_imap_encryption'");
                        }
                    }
                    $update_stmt->close();
                }
            } else {
                // Validar que las contraseñas no estén vacías al crear nueva configuración
                if (empty($smtp_password) || empty($imap_password)) {
                    $message = 'Las contraseñas son requeridas al crear una nueva configuración';
                    $messageType = 'danger';
                } else {
                    // Insertar nueva configuración
                    // Asegurar que los valores string no estén vacíos (usar cadena vacía en lugar de null)
                    $smtp_from_email = $smtp_from_email !== null ? $smtp_from_email : '';
                    $imap_username = $imap_username !== null ? $imap_username : '';
                    $imap_encryption = $imap_encryption !== null ? $imap_encryption : '/imap/ssl';
                    
                    $insert_stmt = $conn->prepare("INSERT INTO empresa_email_config 
                        (id_empresa, smtp_host, smtp_port, smtp_username, smtp_password,
                        smtp_from_email, smtp_from_name,
                        imap_host, imap_port, imap_username, imap_password,
                        imap_folder, imap_encryption, imap_options, activo,
                        processing_enabled, max_emails_per_run, processing_interval_minutes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    // String de tipos correcto: isisssssisssssiiii (18 caracteres)
                    $insert_stmt->bind_param("isisssssisssssiiii",
                        $id_empresa,
                        $smtp_host, $smtp_port, $smtp_username, $smtp_password,
                        $smtp_from_email, $smtp_from_name,
                        $imap_host, $imap_port, $imap_username, $imap_password,
                        $imap_folder, $imap_encryption, $imap_options,
                        $activo, $processing_enabled, $max_emails_per_run, $processing_interval_minutes
                    );
                    
                    if ($insert_stmt->execute()) {
                        $message = 'Configuración guardada correctamente';
                        $messageType = 'success';
                        // Recargar configuración desde la base de datos
                        $reload_stmt = $conn->prepare("SELECT eec.*, eu.nombre as empresa_nombre 
                                                     FROM empresa_email_config eec
                                                     LEFT JOIN empresas_usuarios eu ON eec.id_empresa = eu.id_empresa
                                                     WHERE eec.id_empresa = ?");
                        $reload_stmt->bind_param("i", $id_empresa);
                        $reload_stmt->execute();
                        $reload_result = $reload_stmt->get_result();
                        if ($reload_row = $reload_result->fetch_assoc()) {
                            $configs[$id_empresa] = $reload_row;
                            // Redirigir a modo edición después de crear
                            header("Location: empresa_email_config.php?edit=" . $id_empresa . "&saved=1");
                            exit();
                        }
                        $reload_stmt->close();
                    } else {
                        $message = 'Error al guardar: ' . $conn->error;
                        $messageType = 'danger';
                    }
                    $insert_stmt->close();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id_empresa = intval($_POST['id_empresa']);
        $delete_stmt = $conn->prepare("DELETE FROM empresa_email_config WHERE id_empresa = ?");
        $delete_stmt->bind_param("i", $id_empresa);
        
        if ($delete_stmt->execute()) {
            $message = 'Configuración eliminada correctamente';
            $messageType = 'success';
        } else {
            $message = 'Error al eliminar: ' . $conn->error;
            $messageType = 'danger';
        }
        $delete_stmt->close();
    }
}

// Obtener configuraciones existentes
$configs_query = "SELECT eec.*, eu.nombre as empresa_nombre 
                 FROM empresa_email_config eec
                 LEFT JOIN empresas_usuarios eu ON eec.id_empresa = eu.id_empresa
                 ORDER BY eu.nombre";
$configs_result = $conn->query($configs_query);
$configs = [];
while ($row = $configs_result->fetch_assoc()) {
    $configs[$row['id_empresa']] = $row;
}

// Obtener configuración específica si se está editando
$edit_config = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    if (isset($configs[$edit_id])) {
        $edit_config = $configs[$edit_id];
    } else {
        // Si no está en el array, cargar directamente desde la base de datos
        $load_stmt = $conn->prepare("SELECT eec.*, eu.nombre as empresa_nombre 
                                    FROM empresa_email_config eec
                                    LEFT JOIN empresas_usuarios eu ON eec.id_empresa = eu.id_empresa
                                    WHERE eec.id_empresa = ?");
        $load_stmt->bind_param("i", $edit_id);
        $load_stmt->execute();
        $load_result = $load_stmt->get_result();
        if ($load_row = $load_result->fetch_assoc()) {
            $edit_config = $load_row;
            $configs[$edit_id] = $load_row;
        }
        $load_stmt->close();
    }
}

// Mostrar mensaje de éxito si viene de redirección
if (isset($_GET['saved']) && $_GET['saved'] == '1') {
    $message = 'Configuración guardada correctamente';
    $messageType = 'success';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Email por Empresa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #202654;
            --secondary-color: #3a4478;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            overflow-x: hidden;
            background:
                radial-gradient(circle at top right, rgba(47, 91, 234, 0.06), transparent 28%),
                linear-gradient(180deg, #eef2f7 0%, #f7f8fa 42%, #f7f8fa 100%) !important;
        }
        
        .custom-bg {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 8px 24px rgba(32, 38, 84, 0.18);
        }
        
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 0.375rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .config-section {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            border: 1px solid #e6ebf2;
            box-shadow: 0 2px 10px rgba(23, 32, 51, 0.04);
        }
        
        .config-section h4 {
            color: #202654;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #202654;
        }
        
        .badge-activo {
            background: #28a745;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        
        .badge-inactivo {
            background: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            width: 100%;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #202654;
            box-shadow: 0 0 0 3px rgba(32, 38, 84, 0.12);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #202654;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a4478;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .text-danger {
            color: #dc3545;
        }
        
        .text-muted {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .form-check-input {
            width: 1rem;
            height: 1rem;
            margin-right: 0.5rem;
            cursor: pointer;
        }
        
        .form-check-label {
            cursor: pointer;
            margin-bottom: 0;
        }
        
        .form-text {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.875rem;
        }
        
        /* Responsive para desktop grande */
        @media (min-width: 1441px) {
            .container {
                max-width: 1400px;
            }
        }
        
        /* Responsive para tablet */
        @media (max-width: 1024px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .custom-bg {
                padding: 0.75rem 0;
            }
            
            .custom-bg h1 {
                font-size: 1.5rem;
            }
        }
        
        /* Responsive para móvil */
        @media (max-width: 768px) {
            .container {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            
            .custom-bg {
                padding: 0.5rem 0;
            }
            
            .custom-bg h1 {
                font-size: 1.25rem;
            }
            
            .custom-bg .container {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .custom-bg a {
                font-size: 0.875rem;
                padding: 0.5rem 1rem;
            }
            
            .bg-white {
                padding: 1rem !important;
            }
            
            .flex.justify-between {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start !important;
            }
            
            .flex.space-x-2 {
                width: 100%;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .flex.space-x-2 a,
            .flex.space-x-2 button {
                width: 100%;
                text-align: center;
            }
            
            table {
                font-size: 0.875rem;
            }
            
            th, td {
                padding: 0.5rem !important;
            }
        }
        
        /* Responsive para móvil pequeño */
        @media (max-width: 480px) {
            .custom-bg h1 {
                font-size: 1.1rem;
            }
            
            .bg-white {
                padding: 0.75rem !important;
            }
            
            table {
                font-size: 0.75rem;
            }
            
            th, td {
                padding: 0.25rem !important;
            }
            
            .btn,
            button,
            a.bg-blue-600,
            a.bg-gray-600,
            a.bg-gray-700 {
                font-size: 0.875rem !important;
                padding: 0.5rem 1rem !important;
            }
        }
        
        /* Tabla responsive */
        @media (max-width: 768px) {
            .overflow-x-auto {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="custom-bg text-white py-4 mb-6">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold">
                <i class="fas fa-envelope mr-2"></i>
                Configuración de Email por Empresa
            </h1>
            <a href="<?php echo htmlspecialchars(appUrl('/gestion-empresas.php'), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white text-gray-800 px-4 py-2 rounded hover:bg-gray-100 transition">
                <i class="fas fa-arrow-left mr-2"></i>
                Volver
            </a>
        </div>
    </div>

    <div class="container mx-auto px-4">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>" id="messageAlert">
                <div class="flex justify-between items-center">
                    <span><?php echo htmlspecialchars($message); ?></span>
                    <button onclick="closeMessage()" class="ml-4 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Lista de configuraciones existentes -->
        <?php if (!empty($configs)): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Configuraciones Existentes</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empresa</th>
                                <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SMTP Host</th>
                                <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IMAP Host</th>
                                <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($configs as $config): ?>
                            <tr>
                                <td class="py-4 px-6 whitespace-nowrap">
                                    <strong><?php echo htmlspecialchars($config['empresa_nombre']); ?></strong>
                                </td>
                                <td class="py-4 px-6 whitespace-nowrap">
                                    <?php echo htmlspecialchars($config['smtp_host']); ?>
                                </td>
                                <td class="py-4 px-6 whitespace-nowrap">
                                    <?php echo htmlspecialchars($config['imap_host']); ?>
                                </td>
                                <td class="py-4 px-6 whitespace-nowrap">
                                    <?php if ($config['activo']): ?>
                                        <span class="badge-activo">Activo</span>
                                    <?php else: ?>
                                        <span class="badge-inactivo">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 whitespace-nowrap text-sm font-medium">
                                    <a href="?edit=<?php echo $config['id_empresa']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de eliminar esta configuración?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id_empresa" value="<?php echo $config['id_empresa']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Formulario de configuración -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">
                    <?php echo $edit_config ? 'Editar' : 'Nueva'; ?> Configuración de Email
                </h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="save">
                
                <?php if ($edit_config): ?>
                    <!-- Campo hidden para asegurar que id_empresa se envíe cuando está en modo edición -->
                    <input type="hidden" name="id_empresa" value="<?php echo htmlspecialchars($edit_config['id_empresa']); ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="id_empresa">Empresa <span class="text-danger">*</span></label>
                    <?php if ($edit_config): ?>
                        <!-- Mostrar como campo deshabilitado cuando se edita -->
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_config['empresa_nombre'] ?? ('Empresa ID ' . ($edit_config['id_empresa'] ?? ''))); ?>" 
                               disabled>
                    <?php else: ?>
                        <select class="form-control" id="id_empresa" name="id_empresa" required>
                            <option value="">Seleccione una empresa</option>
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?php echo $empresa['id_empresa']; ?>">
                                    <?php echo htmlspecialchars($empresa['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <?php if ($edit_config): ?>
                        <small class="form-text text-muted">No se puede cambiar la empresa al editar</small>
                    <?php endif; ?>
                </div>

                <!-- Configuración SMTP -->
                <div class="config-section">
                    <h4><i class="fas fa-paper-plane"></i> Configuración SMTP (Envío de correos)</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="smtp_host">SMTP Host <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                   value="<?php echo htmlspecialchars(isset($edit_config) ? ($edit_config['smtp_host'] ?? '') : ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="smtp_port">SMTP Puerto</label>
                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                   value="<?php echo isset($edit_config) ? ($edit_config['smtp_port'] ?? 587) : 587; ?>" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="smtp_username">SMTP Usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                   value="<?php echo htmlspecialchars(isset($edit_config) ? ($edit_config['smtp_username'] ?? '') : ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="smtp_password">SMTP Contraseña</label>
                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                   value="" 
                                   placeholder="<?php echo isset($edit_config) ? 'Dejar en blanco para mantener' : ''; ?>">
                            <?php if ($edit_config): ?>
                                <small class="form-text text-muted">Dejar en blanco para mantener la contraseña actual</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="smtp_from_email">Email Remitente <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email" 
                                   value="<?php echo htmlspecialchars(isset($edit_config) ? ($edit_config['smtp_from_email'] ?? '') : ''); ?>" required>
                            <small class="form-text text-muted">Debe ser un correo del mismo dominio que el usuario SMTP (p. ej. la misma cuenta).</small>
                        </div>
                        <div class="form-group">
                            <label for="smtp_from_name">Nombre Remitente</label>
                            <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name" 
                                   value="<?php echo htmlspecialchars(isset($edit_config) ? ($edit_config['smtp_from_name'] ?? 'Sistema de Tickets') : 'Sistema de Tickets'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Configuración IMAP -->
                <div class="config-section">
                    <h4><i class="fas fa-inbox"></i> Configuración IMAP (Recepción de correos)</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="imap_host">IMAP Host <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="imap_host" name="imap_host" 
                                   value="<?php echo htmlspecialchars(isset($edit_config) ? ($edit_config['imap_host'] ?? '') : ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="imap_port">IMAP Puerto</label>
                            <input type="number" class="form-control" id="imap_port" name="imap_port" 
                                   value="<?php echo isset($edit_config) ? ($edit_config['imap_port'] ?? 993) : 993; ?>" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="imap_username">IMAP Usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="imap_username" name="imap_username" 
                                   value="<?php echo htmlspecialchars(isset($edit_config) ? ($edit_config['imap_username'] ?? '') : ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="imap_password">IMAP Contraseña</label>
                            <input type="password" class="form-control" id="imap_password" name="imap_password" 
                                   value="" 
                                   placeholder="<?php echo isset($edit_config) ? 'Dejar en blanco para mantener' : ''; ?>">
                            <?php if ($edit_config): ?>
                                <small class="form-text text-muted">Dejar en blanco para mantener la contraseña actual</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="form-group">
                            <label for="imap_folder">Carpeta IMAP</label>
                            <input type="text" class="form-control" id="imap_folder" name="imap_folder" 
                                   value="<?php echo htmlspecialchars(isset($edit_config) ? ($edit_config['imap_folder'] ?? 'INBOX') : 'INBOX'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="imap_encryption">Cifrado</label>
                            <select class="form-control" id="imap_encryption" name="imap_encryption">
                                <?php 
                                $current_encryption = isset($edit_config) ? ($edit_config['imap_encryption'] ?? '/imap/ssl') : '/imap/ssl';
                                ?>
                                <option value="/imap/ssl" <?php echo $current_encryption == '/imap/ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="/imap/tls" <?php echo $current_encryption == '/imap/tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="/imap/notls" <?php echo $current_encryption == '/imap/notls' ? 'selected' : ''; ?>>Sin cifrado</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="imap_options">Opciones IMAP</label>
                            <input type="text" class="form-control" id="imap_options" name="imap_options" 
                                   value="<?php echo htmlspecialchars(isset($edit_config) ? ($edit_config['imap_options'] ?? '/imap/ssl/validate-cert') : '/imap/ssl/validate-cert'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Configuración de Procesamiento -->
                <div class="config-section">
                    <h4><i class="fas fa-cog"></i> Configuración de Procesamiento Automático</h4>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="activo" name="activo" 
                                   <?php echo (!isset($edit_config) || (isset($edit_config['activo']) && $edit_config['activo'] == 1)) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="activo">
                                Configuración activa
                            </label>
                        </div>
                    </div>

                    <!-- <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="processing_enabled" name="processing_enabled" 
                                   <?php echo (!isset($edit_config) || (isset($edit_config['processing_enabled']) && $edit_config['processing_enabled'] == 1)) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="processing_enabled">
                                Habilitar procesamiento automático de correos
                            </label>
                        </div>
                        <small class="form-text text-muted">Si está deshabilitado, esta configuración no será procesada por el cron job</small>
                    </div> -->

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="max_emails_per_run">Máximo de correos por ejecución</label>
                                <input type="number" class="form-control" id="max_emails_per_run" name="max_emails_per_run" 
                                       value="<?php echo isset($edit_config) ? ($edit_config['max_emails_per_run'] ?? 50) : 50; ?>" 
                                       min="1" max="500" required>
                                <small class="form-text text-muted">Número máximo de correos a procesar en cada ejecución del cron</small>
                            </div>
                            <div class="form-group">
                                <label for="processing_interval_minutes">Intervalo de procesamiento (minutos)</label>
                                <input type="number" class="form-control" id="processing_interval_minutes" name="processing_interval_minutes" 
                                       value="<?php echo isset($edit_config) ? ($edit_config['processing_interval_minutes'] ?? 5) : 5; ?>" 
                                       min="1" max="60" required>
                                <small class="form-text text-muted">Tiempo mínimo entre procesamientos (el cron se ejecuta cada 5 minutos)</small>
                            </div>
                        </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-save mr-2"></i> Guardar Configuración
                    </button>
                    <?php if ($edit_config): ?>
                        <a href="empresa_email_config.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded ml-2">
                            <i class="fas fa-times mr-2"></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
                </form>
        </div>
    </div>
</body>
</html>

<script>
function closeMessage() {
    const messageAlert = document.getElementById('messageAlert');
    if (messageAlert) {
        messageAlert.style.transition = 'opacity 0.5s ease';
        messageAlert.style.opacity = '0';
        setTimeout(function() {
            messageAlert.remove();
        }, 500);
    }
}

// Auto-ocultar alertas después de 4 segundos
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(function(alert) {
        // Esperar 4 segundos antes de ocultar
        setTimeout(function() {
            // Agregar clase fade-out para transición suave
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            
            // Eliminar el elemento después de la animación
            setTimeout(function() {
                alert.remove();
            }, 500); // 500ms para la animación
        }, 4000); // 4 segundos
    });
});
</script>
