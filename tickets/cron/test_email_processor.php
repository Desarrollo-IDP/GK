<?php
/**
 * Archivo de Prueba Visual para Procesamiento de Emails por Empresa
 * Permite probar conexiones IMAP y procesamiento de correos para cada empresa
 */

// Permitir ejecución desde navegador
if (!isset($_GET['allow_browser']) && php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde navegador con ?allow_browser=1 o desde línea de comandos");
}

require_once(__DIR__ . '/../includes/email_processing_helper.php');
require_once(__DIR__ . '/../conexion/get_empresa_email_config.php');
require_once(__DIR__ . '/config_email_processor.php');

// Definir modo procesador para evitar ejecución del código principal de outlook_emails.php
if (!defined('EMAIL_PROCESSOR_MODE')) {
    define('EMAIL_PROCESSOR_MODE', true);
}
if (!defined('EMAIL_PROCESSOR_RUNNING')) {
    define('EMAIL_PROCESSOR_RUNNING', true);
}

// Incluir funciones necesarias
require_once(__DIR__ . '/email_processor_functions.php');
require_once(__DIR__ . '/../modules/outlook_emails.php');

// Obtener conexión a la base de datos
ensureDbConnection();
$conn = $GLOBALS['conn'];

$action = $_GET['action'] ?? 'list';
$empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : null;
$test_result = null;
$emails_found = [];
$processing_result = null;

// Obtener todas las empresas con configuración de email
$empresas_query = "SELECT eec.*, eu.nombre as empresa_nombre 
                   FROM empresa_email_config eec
                   INNER JOIN empresas_usuarios eu ON eec.id_empresa = eu.id_empresa
                   ORDER BY eu.nombre";
$empresas_result = $conn->query($empresas_query);
$empresas = [];
if ($empresas_result) {
    while ($row = $empresas_result->fetch_assoc()) {
        $empresas[] = $row;
    }
}

// Procesar acciones
if ($action === 'test_connection' && $empresa_id) {
    // Probar conexión IMAP
    try {
        $imap_config = getImapConfigForEmpresa($empresa_id, $conn);
        
        if (!$imap_config) {
            $test_result = [
                'success' => false,
                'message' => 'No se pudo obtener la configuración IMAP para esta empresa'
            ];
        } else {
            // Construir cadena de conexión
            $encryption = $imap_config['encryption'] ?? '/imap/ssl';
            $options = $imap_config['options'] ?? '';
            
            if (!empty($options) && strpos($options, $encryption) === 0) {
                $imap_path = $options;
            } else {
                $imap_path = $encryption . $options;
            }
            
            $mailbox_string = "{" . $imap_config['host'] . ":" . $imap_config['port'] . 
                             $imap_path . "}" . 
                             $imap_config['folder'];
            
            // Intentar conectar
            @imap_errors();
            @imap_alerts();
            
            $inbox = @imap_open($mailbox_string, $imap_config['username'], $imap_config['password'], 
                               0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
            
            if ($inbox) {
                // Obtener información del buzón
                $mailbox_info = @imap_status($inbox, $mailbox_string, SA_ALL);
                
                $test_result = [
                    'success' => true,
                    'message' => 'Conexión exitosa',
                    'mailbox_string' => $mailbox_string,
                    'mailbox_info' => $mailbox_info
                ];
                
                @imap_close($inbox);
            } else {
                $error = @imap_last_error();
                @imap_errors();
                @imap_alerts();
                
                $test_result = [
                    'success' => false,
                    'message' => 'Error al conectar: ' . ($error ?: 'Error desconocido'),
                    'mailbox_string' => $mailbox_string
                ];
            }
        }
    } catch (Exception $e) {
        $test_result = [
            'success' => false,
            'message' => 'Excepción: ' . $e->getMessage()
        ];
    }
} elseif ($action === 'list_emails' && $empresa_id) {
    // Listar correos sin procesarlos
    try {
        $imap_config = getImapConfigForEmpresa($empresa_id, $conn);
        
        if (!$imap_config) {
            $test_result = [
                'success' => false,
                'message' => 'No se pudo obtener la configuración IMAP'
            ];
        } else {
            // Construir cadena de conexión
            $encryption = $imap_config['encryption'] ?? '/imap/ssl';
            $options = $imap_config['options'] ?? '';
            
            if (!empty($options) && strpos($options, $encryption) === 0) {
                $imap_path = $options;
            } else {
                $imap_path = $encryption . $options;
            }
            
            $mailbox_string = "{" . $imap_config['host'] . ":" . $imap_config['port'] . 
                             $imap_path . "}" . 
                             $imap_config['folder'];
            
            @imap_errors();
            @imap_alerts();
            
            $inbox = @imap_open($mailbox_string, $imap_config['username'], $imap_config['password'], 
                               0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
            
            if ($inbox) {
                // Primero intentar obtener correos no leídos
                $email_uids = @imap_search($inbox, 'UNSEEN', FT_UID);
                $unseen_count = $email_uids ? count($email_uids) : 0;
                
                error_log("DEBUG list_emails: imap_search UNSEEN retornó " . ($email_uids ? count($email_uids) : 'false'));
                
                // Si no hay correos no leídos, obtener todos los correos
                if (!$email_uids || count($email_uids) === 0) {
                    $email_uids = @imap_search($inbox, 'ALL', FT_UID);
                    error_log("DEBUG list_emails: imap_search ALL retornó " . ($email_uids ? count($email_uids) : 'false'));
                    error_log("No se encontraron correos no leídos, buscando todos los correos...");
                }
                
                $emails_found = [];
                if ($email_uids) {
                    foreach ($email_uids as $uid) {
                        $header = @imap_headerinfo($inbox, imap_msgno($inbox, $uid));
                        if ($header) {
                            // Verificar si está marcado como leído
                            $flags = @imap_fetch_overview($inbox, $uid, FT_UID);
                            $is_seen = false;
                            if ($flags && isset($flags[0])) {
                                $is_seen = isset($flags[0]->seen) || strpos($flags[0]->flags ?? '', '\\Seen') !== false;
                            }
                            
                            $from_email = 'Desconocido';
                            if (isset($header->from[0])) {
                                $from_email = ($header->from[0]->mailbox ?? '') . '@' . ($header->from[0]->host ?? '');
                            }
                            
                            $emails_found[] = [
                                'uid' => $uid,
                                'subject' => $header->subject ?? '(Sin asunto)',
                                'from' => $from_email,
                                'date' => $header->date ?? 'Desconocida',
                                'size' => $header->Size ?? 0,
                                'is_seen' => $is_seen
                            ];
                        }
                    }
                }
                
                $message = 'Correos encontrados: ' . count($emails_found);
                if ($unseen_count > 0) {
                    $message .= ' (' . $unseen_count . ' no leídos)';
                } else {
                    $message .= ' (todos están leídos)';
                }
                
                $test_result = [
                    'success' => true,
                    'message' => $message,
                    'mailbox_string' => $mailbox_string,
                    'emails_count' => count($emails_found),
                    'unseen_count' => $unseen_count
                ];
                
                @imap_close($inbox);
            } else {
                $error = @imap_last_error();
                @imap_errors();
                @imap_alerts();
                
                $test_result = [
                    'success' => false,
                    'message' => 'Error al conectar: ' . ($error ?: 'Error desconocido')
                ];
            }
        }
    } catch (Exception $e) {
        $test_result = [
            'success' => false,
            'message' => 'Excepción: ' . $e->getMessage()
        ];
    }
} elseif ($action === 'process_emails' && $empresa_id) {
    // Procesar correos realmente
    try {
        $result = processEmpresaEmails($empresa_id, $conn, 50);
        $processing_result = $result;
    } catch (Exception $e) {
        $processing_result = [
            'success' => false,
            'error_message' => $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Procesamiento de Emails por Empresa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .empresa-card {
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: box-shadow 0.3s;
        }
        .empresa-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        .status-active {
            background-color: #28a745;
            color: white;
        }
        .status-inactive {
            background-color: #dc3545;
            color: white;
        }
        .status-processing-enabled {
            background-color: #17a2b8;
            color: white;
        }
        .test-result {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 0.5rem;
        }
        .test-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .test-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .email-item {
            padding: 0.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        .email-item:last-child {
            border-bottom: none;
        }
        .config-details {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-envelope"></i> Prueba de Procesamiento de Emails</h1>
            <a href="test_email_processor.php?allow_browser=1" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Actualizar
            </a>
        </div>

        <?php if (empty($empresas)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No hay empresas con configuración de email activa.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($empresas as $empresa): ?>
                    <div class="col-md-6 mb-3">
                        <div class="empresa-card">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5><?php echo htmlspecialchars($empresa['empresa_nombre']); ?></h5>
                                <div>
                                    <?php if ($empresa['activo']): ?>
                                        <span class="status-badge status-active">Activo</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">Inactivo</span>
                                    <?php endif; ?>
                                    <?php if ($empresa['processing_enabled']): ?>
                                        <span class="status-badge status-processing-enabled">Procesamiento Habilitado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="config-details mb-3">
                                <div><strong>SMTP:</strong> <?php echo htmlspecialchars($empresa['smtp_host']); ?>:<?php echo $empresa['smtp_port']; ?></div>
                                <div><strong>IMAP:</strong> <?php echo htmlspecialchars($empresa['imap_host']); ?>:<?php echo $empresa['imap_port']; ?></div>
                                <div><strong>Usuario:</strong> <?php echo htmlspecialchars($empresa['imap_username']); ?></div>
                                <div><strong>Carpeta:</strong> <?php echo htmlspecialchars($empresa['imap_folder']); ?></div>
                                <div><strong>Máx. correos:</strong> <?php echo $empresa['max_emails_per_run']; ?></div>
                                <div><strong>Intervalo:</strong> <?php echo $empresa['processing_interval_minutes']; ?> minutos</div>
                            </div>
                            
                            <div class="btn-group-vertical w-100" role="group">
                                <a href="?allow_browser=1&action=test_connection&empresa_id=<?php echo $empresa['id_empresa']; ?>" 
                                   class="btn btn-sm btn-info mb-1">
                                    <i class="fas fa-plug"></i> Probar Conexión IMAP
                                </a>
                                <a href="?allow_browser=1&action=list_emails&empresa_id=<?php echo $empresa['id_empresa']; ?>" 
                                   class="btn btn-sm btn-primary mb-1">
                                    <i class="fas fa-list"></i> Listar Correos
                                </a>
                                <a href="?allow_browser=1&action=process_emails&empresa_id=<?php echo $empresa['id_empresa']; ?>" 
                                   class="btn btn-sm btn-success mb-1"
                                   onclick="return confirm('¿Está seguro de procesar los correos? Esto creará tickets en el sistema.');">
                                    <i class="fas fa-cog"></i> Procesar Correos
                                </a>
                            </div>
                            
                            <?php if ($test_result && $empresa['id_empresa'] == $empresa_id): ?>
                                <div class="test-result <?php echo $test_result['success'] ? 'test-success' : 'test-error'; ?> mt-3">
                                    <strong><?php echo $test_result['success'] ? '✓ Éxito' : '✗ Error'; ?></strong><br>
                                    <?php echo htmlspecialchars($test_result['message']); ?><br>
                                    <?php if (isset($test_result['mailbox_string'])): ?>
                                        <small><strong>Cadena de conexión:</strong> <?php echo htmlspecialchars($test_result['mailbox_string']); ?></small><br>
                                    <?php endif; ?>
                                    <?php if (isset($test_result['emails_count'])): ?>
                                        <small><strong>Correos encontrados:</strong> <?php echo $test_result['emails_count']; ?></small>
                                    <?php endif; ?>
                                    <?php if (isset($test_result['mailbox_info'])): ?>
                                        <div class="mt-2">
                                            <small>
                                                <strong>Información del buzón:</strong><br>
                                                Mensajes: <?php echo $test_result['mailbox_info']->messages ?? 'N/A'; ?><br>
                                                Recientes: <?php echo $test_result['mailbox_info']->recent ?? 'N/A'; ?><br>
                                                No leídos: <?php echo $test_result['mailbox_info']->unseen ?? 'N/A'; ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($emails_found) && $empresa['id_empresa'] == $empresa_id): ?>
                                <div class="mt-3">
                                    <h6>Correos Encontrados:</h6>
                                    <div class="list-group">
                                        <?php foreach ($emails_found as $email): ?>
                                            <div class="email-item">
                                                <strong><?php echo htmlspecialchars($email['subject']); ?></strong>
                                                <?php if ($email['is_seen']): ?>
                                                    <span class="badge bg-secondary ms-2">Leído</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary ms-2">No Leído</span>
                                                <?php endif; ?>
                                                <br>
                                                <small>De: <?php echo htmlspecialchars($email['from']); ?></small><br>
                                                <small>Fecha: <?php echo htmlspecialchars($email['date']); ?></small><br>
                                                <small>UID: <?php echo $email['uid']; ?> | Tamaño: <?php echo number_format($email['size']); ?> bytes</small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($processing_result && $empresa['id_empresa'] == $empresa_id): ?>
                                <div class="test-result <?php echo $processing_result['success'] ? 'test-success' : 'test-error'; ?> mt-3">
                                    <strong><?php echo $processing_result['success'] ? '✓ Procesamiento Exitoso' : '✗ Error en Procesamiento'; ?></strong><br>
                                    <?php if ($processing_result['success']): ?>
                                        <div>
                                            <strong>Correos encontrados:</strong> <?php echo $processing_result['emails_found']; ?><br>
                                            <strong>Correos procesados:</strong> <?php echo $processing_result['emails_processed']; ?><br>
                                            <strong>Tiempo de ejecución:</strong> <?php echo $processing_result['execution_time']; ?> segundos
                                        </div>
                                    <?php else: ?>
                                        <div>
                                            <strong>Error:</strong> <?php echo htmlspecialchars($processing_result['error_message'] ?? 'Error desconocido'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle"></i> Instrucciones:</h5>
                <ul>
                    <li><strong>Probar Conexión IMAP:</strong> Verifica que la conexión al servidor IMAP funcione correctamente</li>
                    <li><strong>Listar Correos:</strong> Muestra los correos no leídos sin procesarlos</li>
                    <li><strong>Procesar Correos:</strong> Procesa los correos y crea tickets en el sistema</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

