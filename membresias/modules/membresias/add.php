<?php


// Define current page for active menu highlighting
$current_page = 'membresias_add';

include '../../includes/db.php';
include '../../includes/config.php';
include '../../config/mercadopago_config.php';

// Inicializar variables para el formulario
$errores = [];
$mensaje = '';
$tipoMensaje = '';
initConfig();

// Recuperar mensajes de sesión si existen (para manejo de errores desde modal)
if (isset($_SESSION['temp_errors'])) {
    $errores = $_SESSION['temp_errors'];
    unset($_SESSION['temp_errors']);
}
if (isset($_SESSION['temp_mensaje'])) {
    $mensaje = $_SESSION['temp_mensaje'];
    unset($_SESSION['temp_mensaje']);
}
if (isset($_SESSION['temp_tipo_mensaje'])) {
    $tipoMensaje = $_SESSION['temp_tipo_mensaje'];
    unset($_SESSION['temp_tipo_mensaje']);
}

// Verificar si se está duplicando una membresía
$duplicar_desde = $_POST['duplicar_desde'] ?? null;
$es_duplicacion = !empty($duplicar_desde);

// Verificar si viene desde un tipo de membresía
$crear_desde_tipo = $_POST['crear_desde_tipo'] ?? null;
$es_desde_tipo = !empty($crear_desde_tipo);

// Inicializar variables
$nombre = '';
$apellido_paterno = '';
$apellido_materno = '';
$edad = '';
$fecha_nacimiento = '';
$sexo = '';
$curp = '';
$direccion = '';
$correo = '';
$telefono = '';
$fecha_inicio = date('Y-m-d');
$fecha_fin = '';
$tipo_membresia_id = '';
$metodo_pago = '';
$foto = null;
$firma = null;


// Obtener tipos de membresía con duración
try {
    $empresa_id = getCurrentUserEmpresaIdOrDefault();
    
    // Obtener solo los tipos de membresía de la empresa del usuario logueado
    $empresa_id = getCurrentUserEmpresaIdOrDefault();
    $tipos_stmt = $pdo->prepare("SELECT id, nombre, duracion_dias, empresa_id, mp_plan_id FROM tipos_membresia WHERE empresa_id = ? ORDER BY nombre");
    $tipos_stmt->execute([$empresa_id]);
    $tipos = $tipos_stmt->fetchAll(PDO::FETCH_OBJ);
    

    
} catch (PDOException $e) {
    die("Error al obtener tipos de membresía: " . $e->getMessage());
}

// Si es una duplicación, obtener los datos de la membresía original
if ($es_duplicacion && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("SELECT 
            m.id, m.cliente_id, m.tipo_membresia_id, m.fecha_inicio, m.fecha_fin, m.metodo_pago,
            c.nombre, c.apellido_paterno, c.apellido_materno, c.edad, c.fecha_nacimiento, 
            c.sexo, c.curp, c.direccion, c.correo, c.telefono, c.foto
        FROM membresias m
        LEFT JOIN clientes c ON m.cliente_id = c.id
        WHERE m.id = ? AND m.empresa_id = ?");
        $empresa_id = getCurrentUserEmpresaIdOrDefault();
        $stmt->execute([$duplicar_desde, $empresa_id]);
        $membresia_original = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($membresia_original) {
            // Prellenar los datos del formulario
            $nombre = $membresia_original['nombre'] ?? '';
            $apellido_paterno = $membresia_original['apellido_paterno'] ?? '';
            $apellido_materno = $membresia_original['apellido_materno'] ?? '';
            $edad = $membresia_original['edad'] ?? '';
            $fecha_nacimiento = $membresia_original['fecha_nacimiento'] ?? '';
            $sexo = $membresia_original['sexo'] ?? '';
            $curp = $membresia_original['curp'] ?? '';
            $direccion = $membresia_original['direccion'] ?? '';
            $correo = $membresia_original['correo'] ?? '';
            $telefono = $membresia_original['telefono'] ?? '';
            $metodo_pago = $membresia_original['metodo_pago'] ?? '';
            
            // Fecha actual para la nueva membresía
            $fecha_inicio = date('Y-m-d');
            $fecha_fin = '';
            $tipo_membresia_id = '';
            $foto = null;
            $firma = null;
            
            $mensaje = 'Datos prellenados desde membresía original. Completa los campos faltantes.';
            $tipoMensaje = 'info';
        }
    } catch (PDOException $e) {
        $errores[] = 'Error al obtener datos de la membresía original: ' . $e->getMessage();
    }
} elseif ($es_desde_tipo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si viene desde un tipo de membresía, solo prellenar el tipo seleccionado
    $tipo_membresia_id = $_POST['tipo_membresia_id'] ?? '';
    
    // Mantener todas las demás variables vacías
    $nombre = '';
    $apellido_paterno = '';
    $apellido_materno = '';
    $edad = '';
    $fecha_nacimiento = '';
    $sexo = '';
    $curp = '';
    $direccion = '';
    $correo = '';
    $telefono = '';
    $fecha_inicio = date('Y-m-d');
    $fecha_fin = '';
    $metodo_pago = '';
    $foto = null;
    $firma = null;
    
    $mensaje = 'Tipo de membresía pre-seleccionado. Completa los datos del cliente.';
    $tipoMensaje = 'info';
} else {
    // Inicializar variables vacías para nuevo formulario
    $nombre = '';
    $apellido_paterno = '';
    $apellido_materno = '';
    $edad = '';
    $fecha_nacimiento = '';
    $sexo = '';
    $curp = '';
    $direccion = '';
    $correo = '';
    $telefono = '';
    $fecha_inicio = date('Y-m-d');
    $fecha_fin = '';
    $tipo_membresia_id = '';
    $metodo_pago = '';
    $foto = null;
    $firma = null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Solo procesar datos del POST si no es una duplicación, no es desde tipo, o si es la segunda vez que se envía
    if ((!$es_duplicacion && !$es_desde_tipo) || isset($_POST['submit_form'])) {
        // Datos del cliente
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
        $apellido_materno = trim($_POST['apellido_materno'] ?? '');
        $edad = intval($_POST['edad'] ?? 0);
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
        $sexo = $_POST['sexo'] ?? '';
        $curp = trim($_POST['curp'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $foto = null;

        // Datos de membresía
        $tipo_membresia_id = $_POST['tipo_membresia_id'] ?? '';
        $fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
        $fecha_fin = $_POST['fecha_fin'] ?? '';
        $metodo_pago = $_POST['metodo_pago'] ?? '';
        $firma = null;
    }

    // Solo ejecutar validaciones si no es una duplicación y no viene desde un tipo de membresía
    if (!$es_duplicacion && !$es_desde_tipo) {
        // Validaciones básicas cliente
        if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
        if ($apellido_paterno === '') $errores[] = 'El apellido paterno es obligatorio.';
        if ($edad <= 0) $errores[] = 'La edad debe ser mayor a 0.';
        if ($fecha_nacimiento === '') $errores[] = 'La fecha de nacimiento es obligatoria.';
        if ($sexo === '') $errores[] = 'El sexo es obligatorio.';
        if ($curp === '') $errores[] = 'El CURP es obligatorio.';
        if ($correo === '') $errores[] = 'El correo es obligatorio.';
        if ($telefono === '') $errores[] = 'El número de teléfono es obligatorio.';

        // Manejo de la foto (base64)
        if (isset($_POST['foto_data']) && $_POST['foto_data'] !== '') {
            $fotoData = $_POST['foto_data'];
            $dirFotos = '../../uploads/fotos/';
            if (!is_dir($dirFotos)) {
                mkdir($dirFotos, 0777, true);
            }
            $fotoNombre = uniqid('foto_') . '.png';
            $fotoPath = $dirFotos . $fotoNombre;
            $fotoBase64 = explode(',', $fotoData, 2)[1];
            if (file_put_contents($fotoPath, base64_decode($fotoBase64))) {
                $foto = str_replace('../../', '', $fotoPath);
            } else {
                $errores[] = 'Error al guardar la foto.';
            }
        } else {
            $errores[] = 'La foto es obligatoria.';
        }

        // Validaciones membresía
        if ($tipo_membresia_id === '') $errores[] = 'El tipo de membresía es obligatorio.';
        if ($fecha_inicio === '') $errores[] = 'La fecha de inicio es obligatoria.';
        if ($fecha_fin === '') $errores[] = 'La fecha de fin es obligatoria.';
        if ($metodo_pago === '') $errores[] = 'El método de pago es obligatorio.';

        // Manejo de la firma (canvas)
        if (isset($_POST['firma_data']) && $_POST['firma_data'] !== '') {
            $firmaData = $_POST['firma_data'];
            $dirFirmas = '../../uploads/firmas/';
            if (!is_dir($dirFirmas)) {
                mkdir($dirFirmas, 0777, true);
            }
            $firmaNombre = uniqid('firma_') . '.png';
            $firmaPath = $dirFirmas . $firmaNombre;
            $firmaBase64 = explode(',', $firmaData, 2)[1];
            if (file_put_contents($firmaPath, base64_decode($firmaBase64))) {
                $firma = str_replace('../../', '', $firmaPath);
            } else {
                $errores[] = 'Error al guardar la firma digital.';
            }
        } else {
            $errores[] = 'La firma digital es obligatoria.';
        }
    }

    // Si no hay errores y es un envío real del formulario, guardar cliente y membresía
    if (empty($errores) && isset($_POST['submit_form'])) {
        // Si es MercadoPago, mostrar modal con QR antes de guardar
        if (strtolower($metodo_pago) === 'mercadopago') {
            // Obtener tipo de membresía para generar QR
            $tipoStmt = $pdo->prepare("SELECT id, nombre, precio, mp_plan_id FROM tipos_membresia WHERE id=? AND empresa_id=? LIMIT 1");
            $empresa_id = getCurrentUserEmpresaIdOrDefault();
            $tipoStmt->execute([$tipo_membresia_id, $empresa_id]);
            $tipo = $tipoStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tipo && $tipo['mp_plan_id']) {
                // Generar QR temporal para mostrar en modal - Ahora apunta al login de clientes
                $loginUrl = 'https://membresia.secureitmx.com/membresias/login.php';
                $qrCode = generateQRCodeSVG($loginUrl, 200);
                $_SESSION['temp_qr_code'] = $qrCode;
                $_SESSION['temp_qr_url'] = $loginUrl;
                $_SESSION['temp_plan_info'] = ['reason' => 'Portal de Cliente'];
                $_SESSION['temp_form_data'] = $_POST;
                
                // Redirigir a la misma página para mostrar el modal
                header('Location: ' . $_SERVER['PHP_SELF'] . '?show_qr_modal=1');
                exit;
            }
        }
        
        try {
            $pdo->beginTransaction();
            
            // Obtener la empresa del usuario logueado
            $empresa_id = getCurrentUserEmpresaIdOrDefault();
            error_log("DEBUG: Membresias add.php - empresa_id obtenido: " . $empresa_id);
            // error_log("DEBUG: Membresias add.php - Sesión completa: " . print_r($_SESSION['user'], true));
            
            // Generar identificador único para el cliente
            $identificador_unico = generateClienteIdentificador($empresa_id);
            
            // Log para depuración de la foto
            error_log("DEBUG: Membresias add.php - Foto a guardar: " . $foto);
            error_log("DEBUG: Membresias add.php - Foto data presente: " . (isset($_POST['foto_data']) ? 'Sí' : 'No'));
            
            // Insertar cliente
            $sql = "INSERT INTO clientes (nombre, apellido_paterno, apellido_materno, edad, fecha_nacimiento, sexo, curp, foto, direccion, correo, telefono, empresa_id, identificador_unico, password_hash, must_set_password) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nombre, 
                $apellido_paterno, 
                $apellido_materno,
                $edad, 
                $fecha_nacimiento, 
                $sexo, 
                $curp, 
                $foto, 
                $direccion, 
                $correo, 
                $telefono,
                $empresa_id,
                $identificador_unico,
                hashPassword('12345678'),
                0
            ]);
            
            $cliente_id = $pdo->lastInsertId();

            // Asegurar columnas extra para Mercado Pago
            $cols = $pdo->query("SHOW COLUMNS FROM membresias")->fetchAll(PDO::FETCH_COLUMN);
            $hasMpSub = in_array('mp_preapproval_id', $cols);
            $hasMpPlan = in_array('mp_plan_id', $cols);
            $hasMpStatus = in_array('mp_status', $cols);

            $sqlMembresia = "INSERT INTO membresias (cliente_id, empresa_id, tipo_membresia_id, fecha_inicio, fecha_fin, metodo_pago, firma_digital" .
                            ($hasMpSub ? ", mp_preapproval_id" : "") .
                            ($hasMpPlan ? ", mp_plan_id" : "") .
                            ($hasMpStatus ? ", mp_status" : "") .
                            ") VALUES (?, ?, ?, ?, ?, ?, ?" .
                            ($hasMpSub ? ", ?" : "") .
                            ($hasMpPlan ? ", ?" : "") .
                            ($hasMpStatus ? ", ?" : "") .
                            ")";
            $stmtMembresia = $pdo->prepare($sqlMembresia);
            $paramsM = [
                $cliente_id,
                $empresa_id,
                $tipo_membresia_id, 
                $fecha_inicio, 
                $fecha_fin, 
                $metodo_pago, 
                $firma
            ];
            // placeholders para MP (se llenan luego de crear la suscripción)
            if ($hasMpSub) $paramsM[] = null;
            if ($hasMpPlan) $paramsM[] = null;
            if ($hasMpStatus) $paramsM[] = 'pending';
            $stmtMembresia->execute($paramsM);
            
            $membresia_id = $pdo->lastInsertId();

            // Si el método de pago es Mercado Pago, solo guardar el plan ID para generar QR
            if (strtolower($metodo_pago) === 'mercadopago') {
                // Obtener tipo de membresía
                $tipoStmt = $pdo->prepare("SELECT id, nombre, precio, `interval`, interval_count, mp_plan_id FROM tipos_membresia WHERE id=? AND empresa_id=? LIMIT 1");
                $tipoStmt->execute([$tipo_membresia_id, $empresa_id]);
                $tipo = $tipoStmt->fetch(PDO::FETCH_ASSOC);

                // Asegurar plan en MP (solo si no existe)
                $planId = $tipo['mp_plan_id'] ?? null;
                if (!$planId) {
                    $mpPlanRes = mpCreatePlan($tipo['nombre'], $tipo['precio'], $tipo['descripcion'] ?? '', $tipo['interval_count'], $tipo['interval']);
                    if ($mpPlanRes['success']) {
                        $planId = $mpPlanRes['plan_id'];
                        // Guardar plan en tipo
                        $pdo->prepare("UPDATE tipos_membresia SET mp_plan_id=? WHERE id=?")->execute([$planId, $tipo_membresia_id]);
                    } else {
                        throw new Exception('No se pudo crear el plan en Mercado Pago: ' . ($mpPlanRes['error'] ?? 'error'));
                    }
                }

                // Solo guardar el plan ID en la membresía (NO crear suscripción)
                $upSql = "UPDATE membresias SET ";
                $upParams = [];
                
                if ($hasMpPlan) {
                    $upSql .= "mp_plan_id = ?";
                    $upParams[] = $planId;
                }
                
                if ($hasMpStatus) {
                    if ($hasMpPlan) $upSql .= ", ";
                    $upSql .= "mp_status = ?";
                    $upParams[] = 'pending';
                }
                
                $upSql .= " WHERE id = ?";
                $upParams[] = $membresia_id;
                
                $pdo->prepare($upSql)->execute($upParams);
                
                // Guardar información en sesión para mostrar el QR
                $_SESSION['membresia_id_for_qr'] = $membresia_id;
                $_SESSION['plan_id_for_qr'] = $planId;
            }

            // Si todo salió bien, confirmar la transacción
            $pdo->commit();
            
            // Redirigir al listado con mensaje de éxito
            $redirectUrl = '../../modules/membresias/cards.php?success=' . urlencode('Membresía agregada exitosamente');
            
            // Si es MercadoPago, agregar información para mostrar QR
            if (strtolower($metodo_pago) === 'mercadopago') {
                $redirectUrl .= '&show_qr=1&membresia_id=' . $membresia_id;
            }
            
            header('Location: ' . $redirectUrl);
            exit;
            
        } catch (Exception $e) {
            // Si hay algún error, revertir la transacción
            $pdo->rollBack();
            $errores[] = "Error al guardar los datos: " . $e->getMessage();
        }
    }
}

// Manejar confirmación desde modal QR
if (isset($_POST['confirm_save']) && isset($_SESSION['temp_form_data'])) {
    // Resetear el array de errores para el flujo modal
    $errores = [];
    
    // Verificar que los datos de sesión contengan la información necesaria
    $tempData = $_SESSION['temp_form_data'];
    if (empty($tempData) || !is_array($tempData)) {
        $errores[] = 'Error: No se encontraron los datos del formulario en la sesión.';
        $_SESSION['temp_errors'] = $errores;
        $_SESSION['temp_mensaje'] = 'Error: Datos de sesión no válidos.';
        $_SESSION['temp_tipo_mensaje'] = 'danger';
        $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
        header('Location: ' . $redirect_url);
        exit;
    }
    
    // Debug: verificar qué datos se están recuperando
    error_log("DEBUG: Modal confirmation - tempData keys: " . implode(', ', array_keys($tempData)));
    error_log("DEBUG: Modal confirmation - nombre: " . ($tempData['nombre'] ?? 'NO ENCONTRADO'));
    error_log("DEBUG: Modal confirmation - apellido_paterno: " . ($tempData['apellido_paterno'] ?? 'NO ENCONTRADO'));
    
    // Validar que los datos obligatorios estén presentes
    if (empty($tempData['nombre'])) $errores[] = 'El nombre es obligatorio.';
    if (empty($tempData['apellido_paterno'])) $errores[] = 'El apellido paterno es obligatorio.';
    if (empty($tempData['edad']) || intval($tempData['edad']) <= 0) $errores[] = 'La edad debe ser mayor a 0.';
    if (empty($tempData['fecha_nacimiento'])) $errores[] = 'La fecha de nacimiento es obligatoria.';
    if (empty($tempData['sexo'])) $errores[] = 'El sexo es obligatorio.';
    if (empty($tempData['curp'])) $errores[] = 'El CURP es obligatorio.';
    if (empty($tempData['correo'])) $errores[] = 'El correo es obligatorio.';
    if (empty($tempData['telefono'])) $errores[] = 'El número de teléfono es obligatorio.';
    if (empty($tempData['tipo_membresia_id'])) $errores[] = 'El tipo de membresía es obligatorio.';
    if (empty($tempData['fecha_inicio'])) $errores[] = 'La fecha de inicio es obligatoria.';
    if (empty($tempData['fecha_fin'])) $errores[] = 'La fecha de fin es obligatoria.';
    if (empty($tempData['metodo_pago'])) $errores[] = 'El método de pago es obligatorio.';
    
    $nombre = $tempData['nombre'] ?? '';
    $apellido_paterno = $tempData['apellido_paterno'] ?? '';
    $apellido_materno = $tempData['apellido_materno'] ?? '';
    $edad = $tempData['edad'] ?? '';
    $fecha_nacimiento = $tempData['fecha_nacimiento'] ?? '';
    $sexo = $tempData['sexo'] ?? '';
    $curp = $tempData['curp'] ?? '';
    $direccion = $tempData['direccion'] ?? '';
    $correo = $tempData['correo'] ?? '';
    $telefono = $tempData['telefono'] ?? '';
    $fecha_inicio = $tempData['fecha_inicio'] ?? '';
    $fecha_fin = $tempData['fecha_fin'] ?? '';
    $tipo_membresia_id = $tempData['tipo_membresia_id'] ?? '';
    $metodo_pago = $tempData['metodo_pago'] ?? '';
    
    // Procesar foto si existe
    error_log("DEBUG: Modal confirmation - foto_data presente: " . (isset($tempData['foto_data']) ? 'Sí' : 'No'));
    if (isset($tempData['foto_data']) && $tempData['foto_data'] !== '') {
        $fotoData = $tempData['foto_data'];
        $dirFotos = '../../uploads/fotos/';
        if (!is_dir($dirFotos)) {
            mkdir($dirFotos, 0777, true);
        }
        $fotoNombre = uniqid('foto_') . '.png';
        $fotoPath = $dirFotos . $fotoNombre;
        $fotoBase64 = explode(',', $fotoData, 2)[1];
        if (file_put_contents($fotoPath, base64_decode($fotoBase64))) {
            $foto = str_replace('../../', '', $fotoPath);
            error_log("DEBUG: Modal confirmation - Foto guardada exitosamente: " . $foto);
        } else {
            $errores[] = 'Error al guardar la foto.';
            error_log("DEBUG: Modal confirmation - Error al guardar foto en: " . $fotoPath);
        }
    } else {
        $errores[] = 'La foto es obligatoria.';
        error_log("DEBUG: Modal confirmation - foto_data vacío o no presente");
    }
    
    // Procesar firma si existe
    error_log("DEBUG: Modal confirmation - firma_data presente: " . (isset($tempData['firma_data']) ? 'Sí' : 'No'));
    if (isset($tempData['firma_data']) && $tempData['firma_data'] !== '') {
        $firmaData = $tempData['firma_data'];
        $dirFirmas = '../../uploads/firmas/';
        if (!is_dir($dirFirmas)) {
            mkdir($dirFirmas, 0777, true);
        }
        $firmaNombre = uniqid('firma_') . '.png';
        $firmaPath = $dirFirmas . $firmaNombre;
        $firmaBase64 = explode(',', $firmaData, 2)[1];
        if (file_put_contents($firmaPath, base64_decode($firmaBase64))) {
            $firma = str_replace('../../', '', $firmaPath);
            error_log("DEBUG: Modal confirmation - Firma guardada exitosamente: " . $firma);
        } else {
            $errores[] = 'Error al guardar la firma digital.';
            error_log("DEBUG: Modal confirmation - Error al guardar firma en: " . $firmaPath);
        }
    } else {
        $errores[] = 'La firma digital es obligatoria.';
        error_log("DEBUG: Modal confirmation - firma_data vacío o no presente");
    }
    
    // Verificar que no haya errores antes de proceder
    if (!empty($errores)) {
        $mensaje = 'Por favor corrija los errores antes de continuar.';
        $tipoMensaje = 'danger';
        
        // Guardar errores en sesión y redirigir sin el parámetro show_qr_modal
        $_SESSION['temp_errors'] = $errores;
        $_SESSION['temp_mensaje'] = $mensaje;
        $_SESSION['temp_tipo_mensaje'] = $tipoMensaje;
        
        // Redirigir a la misma página pero sin el parámetro show_qr_modal
        $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
        header('Location: ' . $redirect_url);
        exit;
    } else {
        // Solo proceder con el guardado si no hay errores
        try {
            $pdo->beginTransaction();
            
            // Obtener la empresa del usuario logueado
            $empresa_id = getCurrentUserEmpresaIdOrDefault();
            
            // Generar identificador único para el cliente
            $identificador_unico = generateClienteIdentificador($empresa_id);
            
            // Log para depuración de la foto en flujo modal
            error_log("DEBUG: Membresias add.php (modal) - Foto a guardar: " . $foto);
            error_log("DEBUG: Membresias add.php (modal) - Foto data en tempData: " . (isset($tempData['foto_data']) ? 'Sí' : 'No'));
            
            // Insertar cliente
            $sql = "INSERT INTO clientes (nombre, apellido_paterno, apellido_materno, edad, fecha_nacimiento, sexo, curp, foto, direccion, correo, telefono, empresa_id, identificador_unico, password_hash, must_set_password) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nombre, $apellido_paterno, $apellido_materno, $edad, $fecha_nacimiento, 
                $sexo, $curp, $foto, $direccion, $correo, $telefono, $empresa_id,
                $identificador_unico, hashPassword('12345678'), 0
            ]);
            
            $cliente_id = $pdo->lastInsertId();

            // Asegurar columnas extra para Mercado Pago
            $cols = $pdo->query("SHOW COLUMNS FROM membresias")->fetchAll(PDO::FETCH_COLUMN);
            $hasMpSub = in_array('mp_preapproval_id', $cols);
            $hasMpPlan = in_array('mp_plan_id', $cols);
            $hasMpStatus = in_array('mp_status', $cols);

            $sqlMembresia = "INSERT INTO membresias (cliente_id, empresa_id, tipo_membresia_id, fecha_inicio, fecha_fin, metodo_pago, firma_digital" .
                            ($hasMpSub ? ", mp_preapproval_id" : "") .
                            ($hasMpPlan ? ", mp_plan_id" : "") .
                            ($hasMpStatus ? ", mp_status" : "") .
                            ") VALUES (?, ?, ?, ?, ?, ?, ?" .
                            ($hasMpSub ? ", ?" : "") .
                            ($hasMpPlan ? ", ?" : "") .
                            ($hasMpStatus ? ", ?" : "") .
                            ")";
            $stmtMembresia = $pdo->prepare($sqlMembresia);
            $paramsM = [
                $cliente_id, $empresa_id, $tipo_membresia_id, $fecha_inicio, $fecha_fin, 
                $metodo_pago, $firma
            ];
            
            if ($hasMpSub) $paramsM[] = null;
            if ($hasMpPlan) $paramsM[] = null;
            if ($hasMpStatus) $paramsM[] = 'pending';
            $stmtMembresia->execute($paramsM);
            
            $membresia_id = $pdo->lastInsertId();

            // Si el método de pago es Mercado Pago, guardar el plan ID
            if (strtolower($metodo_pago) === 'mercadopago') {
                $tipoStmt = $pdo->prepare("SELECT id, nombre, precio, mp_plan_id FROM tipos_membresia WHERE id=? AND empresa_id=? LIMIT 1");
                $tipoStmt->execute([$tipo_membresia_id, $empresa_id]);
                $tipo = $tipoStmt->fetch(PDO::FETCH_ASSOC);

                $planId = $tipo['mp_plan_id'] ?? null;
                
                // Solo guardar el plan ID en la membresía
                $upSql = "UPDATE membresias SET ";
                $upParams = [];
                
                if ($hasMpPlan) {
                    $upSql .= "mp_plan_id = ?";
                    $upParams[] = $planId;
                }
                
                if ($hasMpStatus) {
                    if ($hasMpPlan) $upSql .= ", ";
                    $upSql .= "mp_status = ?";
                    $upParams[] = 'pending';
                }
                
                $upSql .= " WHERE id = ?";
                $upParams[] = $membresia_id;
                
                $pdo->prepare($upSql)->execute($upParams);
            }

            $pdo->commit();
            
            // Limpiar datos temporales de la sesión
            unset($_SESSION['temp_qr_code']);
            unset($_SESSION['temp_qr_url']);
            unset($_SESSION['temp_plan_info']);
            unset($_SESSION['temp_form_data']);
            
            // Redirigir al listado con mensaje de éxito
            header('Location: ../../modules/membresias/cards.php?success=' . urlencode('Membresía agregada exitosamente'));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = "Error al guardar los datos: " . $e->getMessage();
            
            // Guardar errores en sesión y redirigir sin el parámetro show_qr_modal
            $_SESSION['temp_errors'] = $errores;
            $_SESSION['temp_mensaje'] = 'Error al guardar los datos.';
            $_SESSION['temp_tipo_mensaje'] = 'danger';
            
            // Redirigir a la misma página pero sin el parámetro show_qr_modal
            $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
            header('Location: ' . $redirect_url);
            exit;
        }
    }
}

// Manejar solicitudes AJAX para obtener el QR (redirige al nuevo login de clientes)
if (isset($_POST['ajax_get_subscription_url'])) {
    header('Content-Type: application/json');
    $membresiaId = $_POST['membresia_id'] ?? null;
    // Construir URL del nuevo portal de clientes (login)
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $loginPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    // Subir dos niveles desde modules/membresias/add.php → raíz del proyecto
    $loginUrl = $baseUrl . str_replace('/modules/membresias', '', $loginPath) . '/membresias/login.php';
    if ($membresiaId) {
        $loginUrl .= '?mid=' . urlencode($membresiaId);
    }

    $qrCode = generateQRCodeSVG($loginUrl, 200);
    echo json_encode([
        'success' => true,
        'url' => $loginUrl,
        'qr_code' => $qrCode,
        'plan_info' => [ 'reason' => 'Portal de Cliente' ],
        'message' => 'Escanea este código QR para acceder al portal de clientes'
    ]);
    exit;
}

// Incluir header después de procesar todo
include '../../includes/header.php';
?>



<style>
/* Signature Pad Styles */
.signature-pad {
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
    margin: 1rem 0;
    background: white;
    padding: 1rem;
}

.signature-pad--body {
    border: 1px dashed var(--gray-300);
    border-radius: 0.375rem;
    background: var(--gray-50);
    margin-bottom: 1rem;
    position: relative;
    width: 100%;
    height: 200px;
}

.signature-pad--body canvas {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: white;
}

.signature-pad--footer {
    margin-top: 1rem;
    color: var(--gray-500);
    font-size: 0.875rem;
}

.signature-pad--footer .description {
    margin-bottom: 0.5rem;
    color: var(--gray-600);
}

.body-bg {
    background: #f8f9fc;
    min-height: 100vh;
    width: 100%;
    overflow-x: hidden;
    padding: 1rem 0;
}

.main-content-boxed {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1.5rem;
    background: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.05);
    margin-top: 1rem;
    margin-bottom: 2rem;
}

.form-section {
    background: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.04);
    padding: 1.75rem;
    margin-bottom: 1.5rem;
    border: 1px solid #e9ecef;
}

.form-section h5 {
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
}

.form-section h5 i {
    margin-right: 0.5rem;
    color: #5e72e4;
}

.form-label {
    font-weight: 500;
    color: #4a5568;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.form-control, .form-select {
    border-radius: 0.5rem;
    padding: 0.6rem 1rem;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
    font-size: 0.9375rem;
}

.form-control:focus, .form-select:focus {
    border-color: #5e72e4;
    box-shadow: 0 0 0 0.25rem rgba(94, 114, 228, 0.15);
}

.btn {
    border-radius: 0.5rem;
    padding: 0.6rem 1.25rem;
    font-weight: 500;
    font-size: 0.9375rem;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn i {
    margin-right: 0.5rem;
}

.btn-primary {
    background-color: #5e72e4;
    border-color: #5e72e4;
}

.btn-primary:hover {
    background-color: #4a5bd9;
    border-color: #4a5bd9;
}

.btn-outline-secondary {
    color: #4a5568;
    border-color: #e2e8f0;
}

.btn-outline-secondary:hover {
    background-color: #f8f9fa;
    border-color: #cbd5e0;
}

/* Estilos para la cámara */
#camara-container {
    margin: 1rem 0;
}

#video, #canvas_foto, #preview_foto {
    border-radius: 0.5rem;
    border: 1px solid #e2e8f0;
    max-width: 100%;
    height: auto;
}

#btnCamara, #btnTomarFoto, button[onclick="limpiarFirma()"] {
    margin: 0.5rem 0.5rem 0.5rem 0;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    background-color: #f8f9fa;
    border: 1px solid #e2e8f0;
    color: #4a5568;
    cursor: pointer;
    transition: all 0.2s ease;
}

#btnCamara:hover, #btnTomarFoto:hover, button[onclick="limpiarFirma()"]:hover {
    background-color: #edf2f7;
    border-color: #cbd5e0;
}

/* Estilos para la firma */
#firma {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    background-color: #fff;
    margin: 0.5rem 0;
    cursor: crosshair;
    touch-action: none; /* Mejora el comportamiento táctil */
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
}

.signature-pad--body canvas {
    position: absolute;
    left: 0;
    top: 0;
    width: 100% !important;
    height: 100% !important;
    background: white;
    border-radius: 0.375rem;
}

/* Estilos para el código QR */
#qr-code-container {
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    background: #fff;
}

#qr-code-container:hover {
    border-color: #5e72e4;
    box-shadow: 0 0.125rem 0.5rem rgba(94, 114, 228, 0.15);
}

#qr-code-svg {
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 1rem 0;
}

#qr-code-svg svg {
    max-width: 100%;
    height: auto;
    border-radius: 0.375rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.1);
}

#qr-code-info {
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 0.375rem;
    border: 1px solid #e9ecef;
}

#qr-code-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 150px;
}

/* Mejoras responsivas */
@media (max-width: 768px) {
    .main-content-boxed {
        padding: 1.5rem 1rem;
        margin: 0.5rem;
        width: auto;
    }
    
    .form-section {
        padding: 1.25rem;
    }
    
    #qr-code-container {
        margin-top: 1rem;
    }
}
</style>

<div class="body-bg">
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/sidebar.php'; ?>
        <main class="main-content main-content-boxed">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="h2 mb-2">Agregar Nueva Membresía</h1>
                    <p class="text-muted mb-0">Complete el formulario para registrar una nueva membresía</p>
                </div>
                <div class="d-flex gap-3 flex-wrap flex-md-nowrap">
                    <a href="list.php" class="btn btn-outline-secondary w-100 w-md-auto">
                        <i class="fas fa-arrow-left me-2"></i>Volver al Listado
                    </a>
                </div>
            </div>

            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> Por favor corrija los siguientes errores:
                <ul class="mb-0 mt-2">
                    <?php foreach ($errores as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" onsubmit="return guardarFirmaYFoto()" class="needs-validation" novalidate>
                <input type="hidden" name="submit_form" value="1">
                <div class="form-section">
                    <h5><i class="fas fa-user me-2"></i>Datos del Cliente</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="nombre" class="form-label">Nombre(s) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="apellido_paterno" class="form-label">Apellido Paterno <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="apellido_materno" class="form-label">Apellido Materno</label>
                    <input type="text" class="form-control" id="apellido_materno" name="apellido_materno">
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="form-group">
                    <label for="edad" class="form-label">Edad <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="edad" name="edad" min="1" required>
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="form-group">
                    <label for="fecha_nacimiento" class="form-label">Fecha de nacimiento <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="form-group">
                    <label for="sexo" class="form-label">Sexo <span class="text-danger">*</span></label>
                    <select name="sexo" id="sexo" class="form-select" required>
                        <option value="">Seleccione</option>
                        <option value="M">Masculino</option>
                        <option value="F">Femenino</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="curp" class="form-label">CURP <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="curp" name="curp" maxlength="18" required>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="correo" class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="correo" name="correo" required>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="telefono" class="form-label">Teléfono <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="telefono" name="telefono" required>
                </div>
            </div>
            
            <div class="col-12">
                <div class="form-group">
                    <label for="direccion" class="form-label">Dirección</label>
                    <input type="text" class="form-control" id="direccion" name="direccion">
                </div>
            </div>
            
            <div class="col-12">
                <div class="form-group">
                    <label class="form-label">Fotografía <span class="text-danger">*</span></label>
                    <div id="camara-container" class="border rounded p-3 bg-light">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <button type="button" id="btnCamara" class="btn btn-sm btn-outline-primary" onclick="toggleCamara()">
                                <i class="fas fa-camera me-1"></i>Activar cámara
                            </button>
                            <button type="button" onclick="tomarFoto()" id="btnTomarFoto" class="btn btn-sm btn-success" style="display:none;">
                                <i class="fas fa-camera me-1"></i>Tomar foto
                            </button>
                        </div>
                        <div class="text-center">
                            <video id="video" width="320" height="240" autoplay style="display:none; max-width:100%;"></video>
                            <canvas id="canvas_foto" width="320" height="240" style="display:none;"></canvas>
                            <img id="preview_foto" src="" alt="Vista previa de la foto" style="display:none; max-width:100%; max-height:300px; border-radius:0.5rem;"/>
                        </div>
                        <input type="hidden" name="foto_data" id="foto_data">
                        <div class="form-text mt-2">Permita el acceso a la cámara y tome la foto del cliente.</div>
                    </div>
                </div>
            </div>
        </div>
                </div>

                <div class="form-section">
                    <h5><i class="fas fa-id-card me-2"></i>Datos de la Membresía</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="tipo_membresia_id" class="form-label">Tipo de Membresía <span class="text-danger">*</span></label>
                    <select name="tipo_membresia_id" id="tipo_membresia_id" class="form-select" required onchange="calcularFechaFin(); mostrarQRCode()">
                        <option value="">Seleccione un tipo</option>
                        <?php if (!empty($tipos)): ?>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?= $t->id ?>" data-duracion="<?= $t->duracion_dias ?>" data-mp-plan-id="<?= $t->mp_plan_id ?? '' ?>" <?= ($tipo_membresia_id == $t->id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t->nombre) ?> (<?= $t->duracion_dias ?> días) - Empresa <?= $t->empresa_id ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No hay tipos de membresía disponibles</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($tipos)): ?>
                        <div class="alert alert-warning mt-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No hay tipos de membresía configurados. 
                            <a href="../tipos_membresia/add.php" class="alert-link">Crear un tipo de membresía</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Campo oculto para el ID de membresía -->
                <?php if (isset($_GET['membresia_id'])): ?>
                <input type="hidden" id="membresia_id" value="<?= htmlspecialchars($_GET['membresia_id']) ?>">
                <?php endif; ?>
            </div>
            
            <!-- Contenedor para el código QR -->
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Código QR de Suscripción</label>
                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="mostrarQRCode()">
                            <i class="fas fa-qrcode me-1"></i>Generar QR Manualmente
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="console.log('Debug info:', {tipoSelect: document.getElementById('tipo_membresia_id')?.value, qrContainer: !!document.getElementById('qr-code-container')})">
                            <i class="fas fa-bug me-1"></i>Debug
                        </button>
                    </div>
                    <div id="qr-code-container" class="border rounded p-3 bg-light text-center" style="min-height: 200px; display: none;">
                        <div id="qr-code-loading" style="display: none;">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                            <p class="text-muted">Generando código QR...</p>
                        </div>
                        <div id="qr-code-content">
                            <div id="qr-code-svg"></div>
                            <div id="qr-code-info" class="mt-2">
                                <small class="text-muted">Escanea el código QR para suscribirte al plan</small>
                            </div>
                        </div>
                        <div id="qr-code-error" class="alert alert-warning" style="display: none;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <span id="qr-error-message">Error al generar el código QR</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="metodo_pago" class="form-label">Método de pago <span class="text-danger">*</span></label>
                    <select name="metodo_pago" id="metodo_pago" class="form-select" required onchange="toggleTarjetaFields()">
                        <option value="">Seleccione un método</option>
                        <option value="mercadopago">Mercado Pago (Suscripción)</option>
                        <option value="cash">Efectivo</option>
                        <option value="transfer">Transferencia Bancaria</option>
                    </select>
                </div>
            </div>
            

                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="fecha_inicio" class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required onchange="calcularFechaFin()">
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="fecha_fin" class="form-label">Fecha de fin <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                    <small class="form-text text-muted" id="fecha_fin_info">Se calculará automáticamente al seleccionar el tipo de membresía</small>
                </div>
            </div>
            
            <div class="col-12">
                <div class="form-group">
                    <label class="form-label">Firma digital <span class="text-danger">*</span></label>
                    <div id="signature-pad" class="signature-pad">
                        <div class="signature-pad--body">
                            <canvas id="firma" width="100%" height="200"></canvas>
                        </div>
                        <div class="signature-pad--footer">
                            <div class="description">Firme arriba en el área designada</div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="limpiarFirma()">
                                    <i class="fas fa-eraser me-1"></i> Limpiar
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="guardarFirma()">
                                    <i class="fas fa-save me-1"></i> Guardar Firma
                                </button>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="firma_data" id="firma_data">
                </div>
            </div>
        </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-5 pt-3 border-top">
                    <a href="list.php" class="btn btn-lg btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-lg btn-primary px-4">
                        <i class="fas fa-save me-2"></i>Guardar Membresía
                    </button>
                </div>
</div>
</div>

<?php include '../../includes/footer.php'; ?>
<script>
// ... (rest of the code remains the same)
let video = document.getElementById('video');
let canvasFoto = document.getElementById('canvas_foto');
let previewFoto = document.getElementById('preview_foto');
let fotoData = document.getElementById('foto_data');
let btnCamara = document.getElementById('btnCamara');
let btnTomarFoto = document.getElementById('btnTomarFoto');
let stream = null;
let camaraActiva = false;

function toggleCamara() {
    if (!camaraActiva) {
        // Activar cámara
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function(s) {
                    stream = s;
                    video.srcObject = stream;
                    video.style.display = 'block';
                    btnTomarFoto.style.display = 'inline-block';
                    btnCamara.textContent = 'Desactivar cámara';
                    camaraActiva = true;
                })
                .catch(function(err) {
                    alert('No se pudo acceder a la cámara: ' + err);
                });
        }
    } else {
        // Desactivar cámara
        if (stream) {
            let tracks = stream.getTracks();
            tracks.forEach(track => track.stop());
        }
        video.srcObject = null;
        video.style.display = 'none';
        btnTomarFoto.style.display = 'none';
        btnCamara.textContent = 'Activar cámara';
        camaraActiva = false;
    }
}

function tomarFoto() {
    canvasFoto.style.display = 'block';
    previewFoto.style.display = 'block';
    canvasFoto.getContext('2d').drawImage(video, 0, 0, canvasFoto.width, canvasFoto.height);
    let dataURL = canvasFoto.toDataURL('image/png');
    previewFoto.src = dataURL;
    fotoData.value = dataURL;
    // Opcional: desactivar cámara después de tomar la foto
    toggleCamara();
}

// --- FIRMA DIGITAL ---
let canvas = document.getElementById('firma');
let ctx = canvas.getContext('2d');
let dibujando = false;
let lastX = 0;
let lastY = 0;

// Configurar el canvas para alta resolución
function setupCanvas() {
    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    
    // Establecer el tamaño real del canvas
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    
    // Establecer el tamaño CSS
    canvas.style.width = rect.width + 'px';
    canvas.style.height = rect.height + 'px';
    
    // Escalar el contexto
    ctx.scale(dpr, dpr);
    
    // Configurar el estilo del trazo
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#000';
}

// Inicializar el canvas cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    setupCanvas();
});

// Reconfigurar el canvas cuando cambie el tamaño de la ventana
window.addEventListener('resize', function() {
    setupCanvas();
});

canvas.addEventListener('mousedown', startDrawing);
canvas.addEventListener('mouseup', stopDrawing);
canvas.addEventListener('mouseout', stopDrawing);
canvas.addEventListener('mousemove', draw);

canvas.addEventListener('touchstart', startDrawing);
canvas.addEventListener('touchend', stopDrawing);
canvas.addEventListener('touchcancel', stopDrawing);
canvas.addEventListener('touchmove', drawTouch);

function startDrawing(e) {
    dibujando = true;
    const pos = getPosition(e);
    lastX = pos.x;
    lastY = pos.y;
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
}

function stopDrawing() {
    dibujando = false;
}

function getPosition(e) {
    const rect = canvas.getBoundingClientRect();
    let x, y;
    
    if (e.type.includes('touch')) {
        x = e.touches[0].clientX - rect.left;
        y = e.touches[0].clientY - rect.top;
    } else {
        x = e.clientX - rect.left;
        y = e.clientY - rect.top;
    }
    
    return { x, y };
}

function draw(e) {
    if (!dibujando) return;
    
    const pos = getPosition(e);
    const currentX = pos.x;
    const currentY = pos.y;
    
    ctx.lineTo(currentX, currentY);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(currentX, currentY);
    
    lastX = currentX;
    lastY = currentY;
}

function drawTouch(e) {
    e.preventDefault();
    draw(e);
}
function limpiarFirma() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    // Resetear el estado de dibujo
    dibujando = false;
    lastX = 0;
    lastY = 0;
}

// Función para verificar si el canvas está en blanco
function isCanvasBlank(canvas) {
    const context = canvas.getContext('2d');
    const pixelBuffer = new Uint32Array(
        context.getImageData(0, 0, canvas.width, canvas.height).data.buffer
    );
    return !pixelBuffer.some(color => color !== 0);
}
function guardarFirma() {
    // Verificar si el canvas está en blanco
    if (isCanvasBlank(canvas)) {
        // Mostrar error si el canvas está vacío
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-warning alert-dismissible fade show';
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i> 
            <strong>Atención:</strong> Por favor, realice una firma antes de guardar.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Remover alertas existentes
        const existingAlert = document.querySelector('.alert-warning');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        // Agregar la nueva alerta
        const form = document.querySelector('form');
        form.insertBefore(alertDiv, form.firstChild);
        
        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
        
        return false;
    }
    
    // Guardar la firma con alta calidad
    const dataURL = canvas.toDataURL('image/png', 1.0);
    document.getElementById('firma_data').value = dataURL;
    
    // Mostrar mensaje de éxito
    const successDiv = document.createElement('div');
    successDiv.className = 'alert alert-success alert-dismissible fade show';
    successDiv.role = 'alert';
    successDiv.innerHTML = `
        <i class="fas fa-check-circle me-2"></i> 
        <strong>Éxito:</strong> Firma guardada correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Remover alertas existentes de éxito
    const existingSuccess = document.querySelector('.alert-success');
    if (existingSuccess) {
        existingSuccess.remove();
    }
    
    // Agregar la nueva alerta
    const form = document.querySelector('form');
    form.insertBefore(successDiv, form.firstChild);
    
    // Auto-remover después de 3 segundos
    setTimeout(() => {
        if (successDiv.parentNode) {
            successDiv.remove();
        }
    }, 3000);
    
    return true;
}

function guardarFirmaYFoto() {
    // Validar firma
    if (!guardarFirma()) {
        return false;
    }
    // Validar foto
    if (!fotoData.value) {
        alert('Por favor, tome la foto del cliente.');
        return false;
    }
    
    // Validar campos de tarjeta si el método de pago es tarjeta
    let metodoPago = document.getElementById('metodo_pago').value;
    if (metodoPago === 'Tarjeta') {
        if (!validarTarjeta()) {
            return false;
        }
    }
    
    return true;
}

// --- PAGO CON TARJETA ---
function toggleTarjetaFields() {
    const metodoPago = document.getElementById('metodo_pago').value;
    const tarjetaFields = document.getElementById('tarjetaFields');
    
    if (metodoPago === 'Tarjeta') {
        tarjetaFields.style.display = 'block';
        // Hacer obligatorios los campos de tarjeta
        document.querySelectorAll('#tarjetaFields [required]').forEach(input => {
            input.required = true;
        });
    } else {
        tarjetaFields.style.display = 'none';
        // Quitar el atributo required de los campos de tarjeta
        document.querySelectorAll('#tarjetaFields [required]').forEach(input => {
            input.required = false;
        });
    }
}



// Inicializar la visibilidad de los campos de tarjeta al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    toggleTarjetaFields();
    

    
    // Agregar indicador visual cuando se está dibujando
    canvas.addEventListener('mousedown', function() {
        canvas.style.cursor = 'crosshair';
    });
    
    canvas.addEventListener('mouseup', function() {
        canvas.style.cursor = 'crosshair';
    });
    
    canvas.addEventListener('mouseenter', function() {
        canvas.style.cursor = 'crosshair';
    });
    
    canvas.addEventListener('mouseleave', function() {
        canvas.style.cursor = 'default';
    });
});

// Función para calcular automáticamente la fecha de fin basada en el tipo de membresía
function calcularFechaFin() {
    const tipoMembresiaSelect = document.getElementById('tipo_membresia_id');
    const fechaInicioInput = document.getElementById('fecha_inicio');
    const fechaFinInput = document.getElementById('fecha_fin');
    
    // Obtener el tipo de membresía seleccionado
    const selectedOption = tipoMembresiaSelect.options[tipoMembresiaSelect.selectedIndex];
    const fechaInicio = fechaInicioInput.value;
    
    // Verificar que se haya seleccionado un tipo y una fecha de inicio
    if (selectedOption && selectedOption.value && fechaInicio) {
        const duracionDias = parseInt(selectedOption.getAttribute('data-duracion'));
        
        if (duracionDias && duracionDias > 0) {
            // Calcular la fecha de fin
            const fechaInicioObj = new Date(fechaInicio);
            const fechaFinObj = new Date(fechaInicioObj);
            
            // Sumar los días de duración (sin restar 1)
            // Si una membresía dura 30 días, va desde el día 1 hasta el día 30
            fechaFinObj.setDate(fechaFinObj.getDate() + duracionDias);
            
            // Formatear la fecha de fin como YYYY-MM-DD
            const year = fechaFinObj.getFullYear();
            const month = String(fechaFinObj.getMonth() + 1).padStart(2, '0');
            const day = String(fechaFinObj.getDate()).padStart(2, '0');
            const fechaFinFormateada = `${year}-${month}-${day}`;
            
            // Establecer la fecha de fin calculada
            fechaFinInput.value = fechaFinFormateada;
            
            // Actualizar el texto informativo
            const fechaFinInfo = document.getElementById('fecha_fin_info');
            if (fechaFinInfo) {
                fechaFinInfo.textContent = `Calculado automáticamente: ${duracionDias} días desde ${fechaInicio} hasta ${fechaFinFormateada}`;
                fechaFinInfo.className = 'form-text text-success';
            }
            
            // Mostrar información al usuario
            console.log(`Membresía: ${selectedOption.textContent}`);
            console.log(`Duración: ${duracionDias} días`);
            console.log(`Fecha de inicio: ${fechaInicio}`);
            console.log(`Fecha de fin calculada: ${fechaFinFormateada}`);
            
            // Ejemplos de verificación
            console.log('=== Ejemplos de cálculo ===');
            console.log('Membresía de 30 días desde 2025-01-01: debe terminar en 2025-01-31');
            console.log('Membresía de 7 días desde 2025-01-01: debe terminar en 2025-01-08');
            console.log('Membresía de 365 días desde 2025-01-01: debe terminar en 2026-01-01');
        }
    } else {
        // Limpiar el texto informativo si no hay selección válida
        const fechaFinInfo = document.getElementById('fecha_fin_info');
        if (fechaFinInfo) {
            fechaFinInfo.textContent = 'Se calculará automáticamente al seleccionar el tipo de membresía';
            fechaFinInfo.className = 'form-text text-muted';
        }
    }
}

// Calcular fecha de fin al cargar la página si ya hay valores
document.addEventListener('DOMContentLoaded', function() {
    // Si ya hay un tipo de membresía seleccionado y fecha de inicio, calcular la fecha de fin
    setTimeout(function() {
        calcularFechaFin();
        mostrarQRCode(); // Mostrar QR si hay un tipo seleccionado
    }, 100);
});

// Función para mostrar el código QR de suscripción
function mostrarQRCode() {
    console.log('mostrarQRCode() iniciado');
    
    try {
        const tipoSelect = document.getElementById('tipo_membresia_id');
        const qrContainer = document.getElementById('qr-code-container');
        const qrLoading = document.getElementById('qr-code-loading');
        const qrContent = document.getElementById('qr-code-content');
        const qrError = document.getElementById('qr-code-error');
        const qrSvg = document.getElementById('qr-code-svg');
        
        console.log('Elementos encontrados:', {
            tipoSelect: !!tipoSelect,
            qrContainer: !!qrContainer,
            qrLoading: !!qrLoading,
            qrContent: !!qrContent,
            qrError: !!qrError,
            qrSvg: !!qrSvg
        });
        
        // Ocultar contenedor si no hay tipo seleccionado
        if (!tipoSelect || !tipoSelect.value) {
            console.log('No hay tipo seleccionado');
            if (qrContainer) qrContainer.style.display = 'none';
            return;
        }
        
        console.log('Tipo seleccionado:', tipoSelect.value);
        
        // Obtener el plan ID del tipo seleccionado
        const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
        const planId = selectedOption.getAttribute('data-mp-plan-id');
        
        console.log('Plan ID:', planId);
        
        if (!planId) {
            // Mostrar mensaje de que no hay plan de MercadoPago configurado
            console.log('No hay plan MP configurado');
            if (qrContainer) qrContainer.style.display = 'block';
            if (qrLoading) qrLoading.style.display = 'none';
            if (qrContent) qrContent.style.display = 'none';
            if (qrError) qrError.style.display = 'block';
            const errorMessage = document.getElementById('qr-error-message');
            if (errorMessage) errorMessage.textContent = 'Este tipo de membresía no tiene un plan de MercadoPago configurado';
            return;
        }
        
        // Mostrar contenedor y loading
        console.log('Mostrando loading...');
        if (qrContainer) qrContainer.style.display = 'block';
        if (qrLoading) qrLoading.style.display = 'block';
        if (qrContent) qrContent.style.display = 'none';
        if (qrError) qrError.style.display = 'none';
        
        // Realizar solicitud AJAX para obtener la URL de suscripción
        const formData = new FormData();
        formData.append('ajax_get_subscription_url', '1');
        formData.append('plan_id', planId);
        
        // Obtener el ID de membresía de la URL o de un campo oculto
        const urlParams = new URLSearchParams(window.location.search);
        let membresiaId = urlParams.get('membresia_id');
        
        // Si no está en la URL, buscar en el campo oculto
        if (!membresiaId) {
            const membresiaIdField = document.getElementById('membresia_id');
            if (membresiaIdField) {
                membresiaId = membresiaIdField.value;
            }
        }
        
        if (membresiaId) {
            formData.append('membresia_id', membresiaId);
        }
        
        console.log('Enviando request AJAX...');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (qrLoading) qrLoading.style.display = 'none';
            
            if (data.success) {
                // Mostrar el código QR
                console.log('Mostrando QR exitoso');
                if (qrContent) qrContent.style.display = 'block';
                if (qrError) qrError.style.display = 'none';
                if (qrSvg) qrSvg.innerHTML = data.qr_code;
                
                // Actualizar información del plan
                const planInfo = document.getElementById('qr-code-info');
                if (planInfo && data.plan_info && data.plan_info.reason) {
                    planInfo.innerHTML = `
                        <small class="text-muted">Escanea el código QR para acceder a la suscripción</small><br>
                        <small class="text-primary">${data.plan_info.reason}</small>
                    `;
                }
            } else {
                // Mostrar error
                console.log('Error en respuesta:', data.error);
                if (qrContent) qrContent.style.display = 'none';
                if (qrError) qrError.style.display = 'block';
                const errorMessage = document.getElementById('qr-error-message');
                if (errorMessage) errorMessage.textContent = data.error || 'Error al generar el código QR';
            }
        })
        .catch(error => {
            console.error('Error en fetch:', error);
            if (qrLoading) qrLoading.style.display = 'none';
            if (qrContent) qrContent.style.display = 'none';
            if (qrError) qrError.style.display = 'block';
            const errorMessage = document.getElementById('qr-error-message');
            if (errorMessage) errorMessage.textContent = 'Error de conexión al generar el código QR';
        });
    } catch (error) {
        console.error('Error en mostrarQRCode:', error);
    }
}

// Función para mostrar el modal QR
function mostrarModalQR() {
    const modal = document.getElementById('qrModal');
    if (modal) {
        modal.style.display = 'block';
        modal.classList.add('show');
    }
}

// Función para cerrar el modal
function cerrarModalQR() {
    const modal = document.getElementById('qrModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
    }
}

// Función para confirmar y guardar
function confirmarYGuardar() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'confirm_save';
    input.value = '1';
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// Mostrar modal automáticamente si viene con parámetro
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('show_qr_modal') === '1') {
        mostrarModalQR();
    }
});
</script>

<!-- Modal QR -->
<div id="qrModal" class="modal fade" tabindex="-1" role="dialog" style="display: none;">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-qrcode"></i> 
                    Portal de Clientes - Código QR
                </h5>
                <button type="button" class="btn-close btn-close-white" onclick="cerrarModalQR()"></button>
            </div>
            <div class="modal-body text-center">
                                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Importante:</strong> Este código QR lleva al portal de clientes donde podrán iniciar sesión y gestionar su suscripción.
                    </div>
                
                <?php if (isset($_SESSION['temp_qr_code'])): ?>
                    <div class="qr-container mb-4">
                        <div id="modal-qr-code">
                            <?php echo $_SESSION['temp_qr_code']; ?>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <?php if (isset($_SESSION['temp_plan_info']['reason'])): ?>
                                    <strong><?php echo htmlspecialchars($_SESSION['temp_plan_info']['reason']); ?></strong><br>
                                <?php endif; ?>
                                Escanea este código QR para acceder al portal de clientes
                            </small>
                        </div>
                    </div>
                    
                    <?php if (isset($_SESSION['temp_qr_url'])): ?>
                        <div class="mb-3">
                            <a href="<?php echo htmlspecialchars($_SESSION['temp_qr_url']); ?>" 
                               target="_blank" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-external-link-alt"></i> 
                                Abrir en Nueva Pestaña
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Recuerda:</strong> Una vez que confirmes, los datos se guardarán en la base de datos y el cliente quedará registrado como pendiente de pago.
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        Error: No se pudo generar el código QR. Intenta nuevamente.
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalQR()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <?php if (isset($_SESSION['temp_qr_code'])): ?>
                    <button type="button" class="btn btn-success" onclick="confirmarYGuardar()">
                        <i class="fas fa-check"></i> Confirmar y Guardar
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos para el modal */
.modal {
    background-color: rgba(0, 0, 0, 0.5);
}

.modal.show {
    display: block !important;
}

.modal-dialog {
    max-width: 600px;
}

.qr-container {
    padding: 20px;
    border: 2px dashed #dee2e6;
    border-radius: 10px;
    background-color: #f8f9fa;
}

#modal-qr-code {
    display: flex;
    justify-content: center;
    align-items: center;
}

#modal-qr-code svg {
    max-width: 200px;
    height: auto;
}

.modal-header {
    border-bottom: none;
}

.modal-footer {
    border-top: none;
}
</style>

<?php include '../../includes/footer.php'; ?>
