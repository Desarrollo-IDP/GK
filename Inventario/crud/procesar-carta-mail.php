<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Verificar si existe columna id_empresa en requesters
$check_column = $conn->query("SHOW COLUMNS FROM requesters LIKE 'id_empresa'");
$has_empresa_column = $check_column && $check_column->num_rows > 0;

function limpiarDatos($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Función para generar número de carta de cumplimiento legal
function generarNumeroCartaCumplimiento($id_empresa_usuario = null) {
    global $conn;
    
    if ($id_empresa_usuario === null) {
        $id_empresa_usuario = 0;
    }
    
    $id_empresa_usuario = intval($id_empresa_usuario);
    
    // Verificar si existe la tabla de contadores
    $check_table = $conn->query("SHOW TABLES LIKE 'carta_cumplimiento_counters'");
    $table_exists = $check_table && $check_table->num_rows > 0;
    
    if (!$table_exists) {
        // Usar método de archivo como fallback
        $empresa_suffix = $id_empresa_usuario !== 0 ? '_empresa_' . $id_empresa_usuario : '';
        $archivo = __DIR__ . '/contador_cartas_cumplimiento' . $empresa_suffix . '.txt';
        
        if (!file_exists($archivo)) {
            file_put_contents($archivo, '0');
        }
        
        $numero = (int)file_get_contents($archivo);
        $numero++;
        file_put_contents($archivo, $numero);
        
        return 'CCL-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }
    
    // Usar tabla de contadores
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("SELECT current_counter FROM carta_cumplimiento_counters WHERE id_empresa = ? FOR UPDATE");
        if (!$stmt) {
            $conn->rollback();
            throw new Exception("Error al preparar consulta de contador");
        }
        
        $stmt->bind_param("i", $id_empresa_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $current = intval($row['current_counter']);
            $next_counter = $current + 1;
            
            $update_stmt = $conn->prepare("UPDATE carta_cumplimiento_counters SET current_counter = ? WHERE id_empresa = ?");
            $update_stmt->bind_param("ii", $next_counter, $id_empresa_usuario);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            $next_counter = 1;
            $insert_stmt = $conn->prepare("INSERT INTO carta_cumplimiento_counters (id_empresa, current_counter) VALUES (?, ?)");
            $insert_stmt->bind_param("ii", $id_empresa_usuario, $next_counter);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        
        $stmt->close();
        $conn->commit();
        
        return 'CCL-' . str_pad($next_counter, 4, '0', STR_PAD_LEFT);
        
    } catch (Exception $e) {
        $conn->rollback();
        // Fallback a método de archivo
        $empresa_suffix = $id_empresa_usuario !== 0 ? '_empresa_' . $id_empresa_usuario : '';
        $archivo = __DIR__ . '/contador_cartas_cumplimiento' . $empresa_suffix . '.txt';
        if (!file_exists($archivo)) {
            file_put_contents($archivo, '0');
        }
        $numero = (int)file_get_contents($archivo);
        $numero++;
        file_put_contents($archivo, $numero);
        return 'CCL-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }
}

switch ($action) {
    case 'agregar_requester':
        $name = limpiarDatos($_POST['name'] ?? '');
        $email = limpiarDatos($_POST['email'] ?? '');
        $phone = limpiarDatos($_POST['phone'] ?? '');
        $company_id = !empty($_POST['company_id']) ? intval($_POST['company_id']) : null;
        
        if (empty($name) || empty($email)) {
            $_SESSION['error_message'] = 'El nombre y el email son obligatorios';
            header('Location: carta-mail.php');
            exit;
        }
        
        // Generar número de requester si no existe
        $requester_number = null;
        if ($has_empresa_column && $user_empresa_id !== null) {
            // Aquí podrías implementar un sistema de numeración similar al de cartas
            // Por ahora, usaremos el ID
        }
        
        if ($has_empresa_column && $user_empresa_id !== null) {
            $stmt = $conn->prepare("INSERT INTO requesters (name, email, phone, company_id, id_empresa) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $name, $email, $phone, $company_id, $user_empresa_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO requesters (name, email, phone, company_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $email, $phone, $company_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Solicitante agregado correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al agregar solicitante: ' . $conn->error;
        }
        $stmt->close();
        header('Location: carta-mail.php');
        exit;
        
    case 'editar_requester':
        $id = intval($_POST['requester_id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error_message'] = 'ID de solicitante inválido';
            header('Location: carta-mail.php');
            exit;
        }
        
        // Verificar que el requester pertenece a la empresa del usuario
        if ($has_empresa_column && $user_empresa_id !== null) {
            $check = $conn->prepare("SELECT id FROM requesters WHERE id = ? AND id_empresa = ?");
            $check->bind_param("ii", $id, $user_empresa_id);
            $check->execute();
            if ($check->get_result()->num_rows == 0) {
                $_SESSION['error_message'] = 'No tiene permisos para editar este solicitante';
                header('Location: carta-mail.php');
                exit;
            }
            $check->close();
        }
        
        $name = limpiarDatos($_POST['name'] ?? '');
        $email = limpiarDatos($_POST['email'] ?? '');
        $phone = limpiarDatos($_POST['phone'] ?? '');
        $company_id = !empty($_POST['company_id']) ? intval($_POST['company_id']) : null;
        
        if (empty($name) || empty($email)) {
            $_SESSION['error_message'] = 'El nombre y el email son obligatorios';
            header('Location: carta-mail.php');
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE requesters SET name = ?, email = ?, phone = ?, company_id = ? WHERE id = ?");
        $stmt->bind_param("sssii", $name, $email, $phone, $company_id, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Solicitante actualizado correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al actualizar solicitante: ' . $conn->error;
        }
        $stmt->close();
        header('Location: carta-mail.php');
        exit;
        
    case 'eliminar_requester':
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error_message'] = 'ID de solicitante inválido';
            header('Location: carta-mail.php');
            exit;
        }
        
        // Verificar que el requester pertenece a la empresa del usuario
        if ($has_empresa_column && $user_empresa_id !== null) {
            $check = $conn->prepare("SELECT id FROM requesters WHERE id = ? AND id_empresa = ?");
            $check->bind_param("ii", $id, $user_empresa_id);
            $check->execute();
            if ($check->get_result()->num_rows == 0) {
                $_SESSION['error_message'] = 'No tiene permisos para eliminar este solicitante';
                header('Location: carta-mail.php');
                exit;
            }
            $check->close();
        }
        
        $stmt = $conn->prepare("DELETE FROM requesters WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Solicitante eliminado correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al eliminar solicitante: ' . $conn->error;
        }
        $stmt->close();
        header('Location: carta-mail.php');
        exit;
        
    case 'generar_carta':
        $id_requester = intval($_POST['id_requester'] ?? 0);
        
        if ($id_requester <= 0) {
            $_SESSION['error_message'] = 'Solicitante inválido';
            header('Location: carta-mail.php');
            exit;
        }
        
        // Verificar que el requester existe y pertenece a la empresa del usuario
        $check_query = "SELECT id FROM requesters WHERE id = ?";
        $check_params = [$id_requester];
        $check_types = 'i';
        
        if ($has_empresa_column && $user_empresa_id !== null) {
            $check_query .= " AND id_empresa = ?";
            $check_params[] = $user_empresa_id;
            $check_types .= 'i';
        }
        
        $check = $conn->prepare($check_query);
        $check->bind_param($check_types, ...$check_params);
        $check->execute();
        if ($check->get_result()->num_rows == 0) {
            $_SESSION['error_message'] = 'Solicitante no encontrado o sin permisos';
            header('Location: carta-mail.php');
            exit;
        }
        $check->close();
        
        // Verificar si ya existe una carta para este requester
        $carta_existe_query = "SELECT id_carta, numero_carta, estado FROM cartas_cumplimiento_legal WHERE id_requester = ?";
        $carta_existe_params = [$id_requester];
        $carta_existe_types = 'i';
        
        if ($has_empresa_column && $user_empresa_id !== null) {
            $carta_existe_query .= " AND id_empresa = ?";
            $carta_existe_params[] = $user_empresa_id;
            $carta_existe_types .= 'i';
        }
        
        $carta_existe = $conn->prepare($carta_existe_query);
        $carta_existe->bind_param($carta_existe_types, ...$carta_existe_params);
        $carta_existe->execute();
        $carta_result = $carta_existe->get_result();
        
        if ($carta_result->num_rows > 0) {
            $carta_existente = $carta_result->fetch_assoc();
            $carta_existe->close();
            $_SESSION['error_message'] = 'Ya existe una carta responsiva para este usuario de correo. Número de carta: ' . htmlspecialchars($carta_existente['numero_carta']) . ' (Estado: ' . htmlspecialchars($carta_existente['estado']) . ')';
            header('Location: carta-mail.php');
            exit;
        }
        $carta_existe->close();
        
        // Contenido fijo de la carta responsiva
        $tipo_cumplimiento = 'Responsiva sobre el Uso del Correo Electrónico';
        $descripcion = 'Por medio de la presente, manifiesto mi compromiso de hacer uso responsable y adecuado del correo electrónico corporativo asignado, comprometiéndome a:
        
1. Utilizar el correo electrónico exclusivamente para asuntos relacionados con mis funciones laborales y actividades profesionales dentro de la organización.

2. Mantener la confidencialidad de la información corporativa y no compartir datos sensibles, contraseñas o información privilegiada a través del correo electrónico.

3. No utilizar el correo corporativo para fines personales, comerciales, políticos, religiosos o cualquier otro propósito ajeno a las actividades de la empresa.

4. Abstenerme de enviar, recibir o almacenar material ofensivo, discriminatorio, ilegal o que pueda dañar la imagen de la organización.

5. Proteger la seguridad del sistema de correo electrónico, no compartiendo mi contraseña y notificando inmediatamente cualquier actividad sospechosa o acceso no autorizado.

6. Cumplir con las políticas de seguridad informática, normativas de protección de datos y regulaciones aplicables en materia de privacidad y confidencialidad.

7. Entender que el uso del correo electrónico corporativo puede ser monitoreado por la organización para garantizar el cumplimiento de las políticas establecidas.

8. Asumir la responsabilidad por cualquier uso indebido del correo electrónico y las consecuencias legales y disciplinarias que de ello deriven.';
        
        $observaciones = 'Esta carta responsiva forma parte del proceso de asignación de recursos tecnológicos y tiene carácter obligatorio para el uso del correo electrónico corporativo.';
        
        // Generar número de carta
        $numero_carta = generarNumeroCartaCumplimiento($user_empresa_id);
        $fecha_generacion = date('Y-m-d');
        $user = $_SESSION['user']['username'] ?? 'Sistema';
        
        // Insertar carta con manejo de error de duplicado
        if ($has_empresa_column && $user_empresa_id !== null) {
            $stmt = $conn->prepare("INSERT INTO cartas_cumplimiento_legal (id_empresa, id_requester, numero_carta, fecha_generacion, tipo_cumplimiento, descripcion, observaciones, estado, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?)");
            $stmt->bind_param("iissssss", $user_empresa_id, $id_requester, $numero_carta, $fecha_generacion, $tipo_cumplimiento, $descripcion, $observaciones, $user);
        } else {
            $stmt = $conn->prepare("INSERT INTO cartas_cumplimiento_legal (id_requester, numero_carta, fecha_generacion, tipo_cumplimiento, descripcion, observaciones, estado, creado_por) VALUES (?, ?, ?, ?, ?, ?, 'Pendiente', ?)");
            $stmt->bind_param("isssss", $id_requester, $numero_carta, $fecha_generacion, $tipo_cumplimiento, $descripcion, $observaciones, $user);
        }
        
        if ($stmt->execute()) {
            // Guardar datos en sesión para generar PDF
            $_SESSION['carta_cumplimiento_generada'] = [
                'numero_carta' => $numero_carta,
                'id_requester' => $id_requester,
                'tipo_cumplimiento' => $tipo_cumplimiento,
                'descripcion' => $descripcion,
                'observaciones' => $observaciones,
                'fecha_generacion' => $fecha_generacion
            ];
            $_SESSION['success_carta'] = "Carta responsiva de correo electrónico {$numero_carta} generada exitosamente. El PDF se abrirá en una nueva ventana.";
            
            // Redirigir a carta-mail.php para recargar la página
            // El PDF se abrirá automáticamente desde JavaScript
            header('Location: carta-mail.php?generar_pdf=1');
            exit;
        } else {
            // Verificar si el error es por restricción única
            if ($conn->errno == 1062) { // Error 1062 = Duplicate entry
                $_SESSION['error_message'] = 'Ya existe una carta responsiva para este usuario de correo. Solo se permite una carta por usuario.';
            } else {
                $_SESSION['error_message'] = 'Error al generar carta: ' . $conn->error;
            }
            header('Location: carta-mail.php');
            exit;
        }
        $stmt->close();
        
    case 'subir_firma':
        $id_carta = intval($_POST['id_carta'] ?? 0);
        $numero_carta = limpiarDatos($_POST['numero_carta'] ?? '');
        $fecha_firma = !empty($_POST['fecha_firma']) ? limpiarDatos($_POST['fecha_firma']) : date('Y-m-d');
        
        if ($id_carta <= 0 || empty($numero_carta)) {
            $_SESSION['error_message'] = 'Datos de carta inválidos';
            header('Location: carta-mail.php');
            exit;
        }
        
        // Verificar que la carta pertenece a la empresa del usuario
        $check_query = "SELECT id_carta FROM cartas_cumplimiento_legal WHERE id_carta = ?";
        $check_params = [$id_carta];
        $check_types = 'i';
        
        if ($has_empresa_column && $user_empresa_id !== null) {
            $check_query .= " AND id_empresa = ?";
            $check_params[] = $user_empresa_id;
            $check_types .= 'i';
        }
        
        $check = $conn->prepare($check_query);
        $check->bind_param($check_types, ...$check_params);
        $check->execute();
        if ($check->get_result()->num_rows == 0) {
            $_SESSION['error_message'] = 'Carta no encontrada o sin permisos';
            header('Location: carta-mail.php');
            exit;
        }
        $check->close();
        
        // Obtener el archivo anterior si existe (para reemplazo)
        $archivo_anterior = null;
        $get_archivo = $conn->prepare("SELECT archivo_firmado FROM cartas_cumplimiento_legal WHERE id_carta = ?");
        $get_archivo->bind_param("i", $id_carta);
        $get_archivo->execute();
        $archivo_result = $get_archivo->get_result();
        if ($archivo_result->num_rows > 0) {
            $archivo_data = $archivo_result->fetch_assoc();
            $archivo_anterior = $archivo_data['archivo_firmado'];
        }
        $get_archivo->close();
        
        // Procesar archivo
        if (isset($_FILES['archivo_firmado']) && $_FILES['archivo_firmado']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/cartas_firmadas/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = $_FILES['archivo_firmado']['name'];
            $file_tmp = $_FILES['archivo_firmado']['tmp_name'];
            $file_size = $_FILES['archivo_firmado']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validar extensión
            if ($file_ext !== 'pdf') {
                $_SESSION['error_message'] = 'Solo se permiten archivos PDF';
                header('Location: carta-mail.php');
                exit;
            }
            
            // Validar tamaño (10MB)
            if ($file_size > 10485760) {
                $_SESSION['error_message'] = 'El archivo es demasiado grande. Tamaño máximo: 10MB';
                header('Location: carta-mail.php');
                exit;
            }
            
            // Eliminar archivo anterior si existe
            if (!empty($archivo_anterior)) {
                $archivo_anterior_path = $upload_dir . $archivo_anterior;
                if (file_exists($archivo_anterior_path)) {
                    @unlink($archivo_anterior_path);
                }
            }
            
            // Generar nombre único
            $new_file_name = 'carta_' . $numero_carta . '_' . time() . '.pdf';
            $file_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                // Actualizar base de datos
                $stmt = $conn->prepare("UPDATE cartas_cumplimiento_legal SET archivo_firmado = ?, fecha_firma = ?, estado = 'Firmada' WHERE id_carta = ?");
                $stmt->bind_param("ssi", $new_file_name, $fecha_firma, $id_carta);
                
                if ($stmt->execute()) {
                    if (!empty($archivo_anterior)) {
                        $_SESSION['success_message'] = 'Archivo firmado reemplazado correctamente';
                    } else {
                        $_SESSION['success_message'] = 'Archivo firmado subido correctamente';
                    }
                } else {
                    $_SESSION['error_message'] = 'Error al actualizar la carta: ' . $conn->error;
                    // Eliminar archivo si falla la actualización
                    unlink($file_path);
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = 'Error al subir el archivo';
            }
        } else {
            $_SESSION['error_message'] = 'Error al procesar el archivo';
        }
        
        header('Location: carta-mail.php');
        exit;
        
    case 'eliminar_firma':
        $id_carta = intval($_POST['id_carta'] ?? 0);
        $archivo_firmado = limpiarDatos($_POST['archivo_firmado'] ?? '');
        
        if ($id_carta <= 0 || empty($archivo_firmado)) {
            $_SESSION['error_message'] = 'Datos de carta inválidos';
            header('Location: carta-mail.php');
            exit;
        }
        
        // Verificar que la carta pertenece a la empresa del usuario
        $check_query = "SELECT id_carta FROM cartas_cumplimiento_legal WHERE id_carta = ?";
        $check_params = [$id_carta];
        $check_types = 'i';
        
        if ($has_empresa_column && $user_empresa_id !== null) {
            $check_query .= " AND id_empresa = ?";
            $check_params[] = $user_empresa_id;
            $check_types .= 'i';
        }
        
        $check = $conn->prepare($check_query);
        $check->bind_param($check_types, ...$check_params);
        $check->execute();
        if ($check->get_result()->num_rows == 0) {
            $_SESSION['error_message'] = 'Carta no encontrada o sin permisos';
            header('Location: carta-mail.php');
            exit;
        }
        $check->close();
        
        // Eliminar archivo físico
        $upload_dir = __DIR__ . '/uploads/cartas_firmadas/';
        $archivo_path = $upload_dir . $archivo_firmado;
        if (file_exists($archivo_path)) {
            @unlink($archivo_path);
        }
        
        // Actualizar base de datos
        $stmt = $conn->prepare("UPDATE cartas_cumplimiento_legal SET archivo_firmado = NULL, fecha_firma = NULL, estado = 'Pendiente' WHERE id_carta = ?");
        $stmt->bind_param("i", $id_carta);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Archivo firmado eliminado correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al eliminar el archivo: ' . $conn->error;
        }
        $stmt->close();
        
        header('Location: carta-mail.php');
        exit;
        
    default:
        $_SESSION['error_message'] = 'Acción no válida';
        header('Location: carta-mail.php');
        exit;
}
?>

