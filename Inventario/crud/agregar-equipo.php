<?php
ob_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../includes/empresa_helper.php';
require_once __DIR__ . '/../../log_activity.php';
require_once __DIR__ . '/../includes/equipment_counter_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $direccion_mac = $_POST['direccion_mac'];
    
    if ($direccion_mac !== 'N/A') {
        $check_stmt = $conn->prepare("SELECT id_equipo FROM equipos WHERE direccion_mac = ?");
        if (!$check_stmt) {
            $error = "Error en la preparación de la consulta: " . $conn->error;
        } else {
            $check_stmt->bind_param("s", $direccion_mac);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $error = "La dirección MAC '" . htmlspecialchars($direccion_mac) . "' ya existe en la base de datos.";
            }
            $check_stmt->close();
        }
    }
    
    if (!isset($error)) {
        $documentos = null;
        if(isset($_FILES['documentos']) && !empty($_FILES['documentos']['name'][0])) {
            $documentos_data = [];
            $total_files = count($_FILES['documentos']['name']);
            
            for($i = 0; $i < $total_files; $i++) {
                if($_FILES['documentos']['error'][$i] == 0) {
                    $file_name = $_FILES['documentos']['name'][$i];
                    $file_type = $_FILES['documentos']['type'][$i];
                    $file_content = file_get_contents($_FILES['documentos']['tmp_name'][$i]);
                    
                    $documentos_data[] = [
                        'nombre' => $file_name,
                        'tipo' => $file_type,
                        'contenido' => base64_encode($file_content)
                    ];
                }
            }
            
            if(!empty($documentos_data)) {
                $documentos = json_encode($documentos_data);
            }
        }
        
        $empresa_id = $_POST['id_empresa'];
        
        // Verificar que el usuario tenga permiso para crear equipos en esta empresa de inventario
        if ($user_empresa_id !== null) {
            // Verificar si existe columna id_empresa_usuario en empresas_equipos
            $check_column = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
            $has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;
            
            if ($has_empresa_usuario_column) {
                // Verificar que la empresa de inventario pertenezca a la empresa del usuario
                $verify_query = "SELECT id_empresa FROM empresas_equipos WHERE id_empresa = ? AND id_empresa_usuario = ?";
                $verify_stmt = $conn->prepare($verify_query);
                $verify_stmt->bind_param("ii", $empresa_id, $user_empresa_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result->num_rows === 0) {
                    $error = "No tienes permiso para crear equipos en esta empresa.";
                    $verify_stmt->close();
                } else {
                    $verify_stmt->close();
                }
            }
        }
        
        if (!isset($error)) {
        $empresa_query = "SELECT nombre FROM empresas_equipos WHERE id_empresa = ?";
        $empresa_stmt = $conn->prepare($empresa_query);
        $empresa_stmt->bind_param("i", $empresa_id);
        $empresa_stmt->execute();
        $empresa_result = $empresa_stmt->get_result();
        $empresa = $empresa_result->fetch_assoc();
        $nombre_cliente = $empresa['nombre'];
            $empresa_stmt->close();

            // Normalizar campos opcionales para pasarlos por referencia en bind_param
            $ubicacion = isset($_POST['ubicacion']) ? $_POST['ubicacion'] : '';
            $activo_equipo = isset($_POST['activo_equipo']) ? $_POST['activo_equipo'] : '';
            
            // Verificar si existe columna id_empresa_usuario en equipos
            $check_column_equipos = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
            $has_empresa_usuario_column_equipos = $check_column_equipos && $check_column_equipos->num_rows > 0;
            
            // Verificar si existe columna equipo_number (verificar antes de usarla)
            $check_column_equipo_number = $conn->query("SHOW COLUMNS FROM equipos LIKE 'equipo_number'");
            $has_equipo_number_column = $check_column_equipo_number && $check_column_equipo_number->num_rows > 0;
            
            // Inicializar equipo_number
            $equipo_number = null;
            
            if ($has_empresa_usuario_column_equipos) {
                // Obtener el siguiente número de equipo para la empresa
                $equipo_number = getNextEquipmentNumber($conn, $user_empresa_id);
                
                if ($has_equipo_number_column) {
                    // Insertar con id_empresa_usuario y equipo_number
                    $stmt = $conn->prepare("INSERT INTO equipos (
                        nombre_cliente, 
                        nombre_equipo, 
                        responsable, 
                        sistema_operativo, 
                        direccion_mac, 
                        tipo, 
                        fabricante, 
                        modelo, 
                        numero_serie, 
                        ram, 
                        disco_duro, 
                        procesador, 
                        observaciones, 
                        ubicacion,
                        activo_equipo,
                        id_empresa,
                        id_empresa_usuario,
                        equipo_number,
                        documentos
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt) {
                        $stmt->bind_param("ssssssssssssssssiss", 
                            $nombre_cliente,
                            $_POST['nombre_equipo'],
                            $_POST['responsable'],
                            $_POST['sistema_operativo'],
                            $direccion_mac,
                            $_POST['tipo'],
                            $_POST['fabricante'],
                            $_POST['modelo'],
                            $_POST['numero_serie'],
                            $_POST['ram'],
                            $_POST['disco_duro'],
                            $_POST['procesador'],
                            $_POST['observaciones'],
                            $ubicacion,
                            $activo_equipo,
                            $empresa_id,
                            $user_empresa_id,
                            $equipo_number,
                            $documentos
                        );
                    }
                } else {
                    // Insertar con id_empresa_usuario sin equipo_number (campo no existe aún)
                    $stmt = $conn->prepare("INSERT INTO equipos (
                        nombre_cliente, 
                        nombre_equipo, 
                        responsable, 
                        sistema_operativo, 
                        direccion_mac, 
                        tipo, 
                        fabricante, 
                        modelo, 
                        numero_serie, 
                        ram, 
                        disco_duro, 
                        procesador, 
                        observaciones, 
                        ubicacion,
                        activo_equipo,
                        id_empresa,
                        id_empresa_usuario,
                        documentos
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt) {
                        $stmt->bind_param("sssssssssssssssis", 
                            $nombre_cliente,
                            $_POST['nombre_equipo'],
                            $_POST['responsable'],
                            $_POST['sistema_operativo'],
                            $direccion_mac,
                            $_POST['tipo'],
                            $_POST['fabricante'],
                            $_POST['modelo'],
                            $_POST['numero_serie'],
                            $_POST['ram'],
                            $_POST['disco_duro'],
                            $_POST['procesador'],
                            $_POST['observaciones'],
                            $ubicacion,
                            $activo_equipo,
                            $empresa_id,
                            $user_empresa_id,
                            $documentos
                        );
                    }
                }
            } else {
                // Insertar sin id_empresa_usuario (campo no existe aún)
        $stmt = $conn->prepare("INSERT INTO equipos (
            nombre_cliente, 
            nombre_equipo, 
            responsable, 
            sistema_operativo, 
            direccion_mac, 
            tipo, 
            fabricante, 
            modelo, 
            numero_serie, 
            ram, 
            disco_duro, 
            procesador, 
            observaciones, 
            ubicacion,
            activo_equipo,
            id_empresa,
            documentos
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("sssssssssssssssss", 
                $nombre_cliente,
                $_POST['nombre_equipo'],
                $_POST['responsable'],
                $_POST['sistema_operativo'],
                $direccion_mac,
                $_POST['tipo'],
                $_POST['fabricante'],
                $_POST['modelo'],
                $_POST['numero_serie'],
                $_POST['ram'],
                $_POST['disco_duro'],
                $_POST['procesador'],
                $_POST['observaciones'],
                $ubicacion,
                $activo_equipo,
                $empresa_id,
                $documentos
            );
                }
            }
            
            if ($stmt && $stmt->execute()) {
                // Obtener el ID insertado inmediatamente después de ejecutar
                $id_equipo = $conn->insert_id;
                
                // Debug: Log detallado
                error_log("DEBUG agregar-equipo: INSERT ejecutado");
                error_log("DEBUG agregar-equipo: affected_rows = " . $stmt->affected_rows);
                error_log("DEBUG agregar-equipo: insert_id = " . ($id_equipo ?? 'NULL'));
                error_log("DEBUG agregar-equipo: insert_id es 0 = " . ($id_equipo == 0 ? 'SÍ' : 'NO'));
                
                // Si insert_id es 0 o null, intentar obtener el último ID de otra forma
                if ($id_equipo == 0 || $id_equipo === null) {
                    error_log("WARNING: insert_id es 0 o null, intentando obtener último ID manualmente");
                    $last_id_query = $conn->query("SELECT LAST_INSERT_ID() as last_id");
                    if ($last_id_query) {
                        $last_id_row = $last_id_query->fetch_assoc();
                        $id_equipo = $last_id_row['last_id'] ?? null;
                        error_log("DEBUG agregar-equipo: LAST_INSERT_ID() = " . ($id_equipo ?? 'NULL'));
                    }
                    
                    // Si aún es 0, obtener el máximo ID de la tabla
                    if ($id_equipo == 0 || $id_equipo === null) {
                        $max_id_query = $conn->query("SELECT MAX(id_equipo) as max_id FROM equipos");
                        if ($max_id_query) {
                            $max_id_row = $max_id_query->fetch_assoc();
                            $id_equipo = $max_id_row['max_id'] ?? null;
                            error_log("DEBUG agregar-equipo: MAX(id_equipo) = " . ($id_equipo ?? 'NULL'));
                        }
                    }
                }
                
                // Verificar que el registro se insertó correctamente
                if ($id_equipo > 0) {
                    $verify_stmt = $conn->prepare("SELECT id_equipo, equipo_number, nombre_equipo FROM equipos WHERE id_equipo = ?");
                    $verify_stmt->bind_param("i", $id_equipo);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->get_result();
                    $equipo_verificado = $verify_result->fetch_assoc();
                    
                    if ($equipo_verificado) {
                        error_log("DEBUG agregar-equipo: Registro verificado - id_equipo: " . $equipo_verificado['id_equipo'] . ", equipo_number: " . ($equipo_verificado['equipo_number'] ?? 'NULL'));
                    } else {
                        error_log("ERROR agregar-equipo: No se encontró el registro con id_equipo = $id_equipo después de insertar");
                    }
                    $verify_stmt->close();
                } else {
                    error_log("ERROR agregar-equipo: id_equipo es 0 o null después de todas las verificaciones");
                    $error = "Error: No se pudo obtener el ID del equipo insertado. Verifique la configuración de AUTO_INCREMENT en la tabla equipos.";
                }
                
                if ($id_equipo > 0) {
                    logActivity(
                        "Creación de equipo",
                        "equipos",
                        $id_equipo,
                        "Se creó un nuevo equipo: " . $_POST['nombre_equipo'] . 
                        "\nCliente: " . $nombre_cliente .
                        "\nResponsable: " . $_POST['responsable'] .
                        "\nID Equipo: " . $id_equipo .
                        ($has_equipo_number_column && isset($equipo_number) ? "\nNúmero: " . $equipo_number : "")
                    );
                    
                    header('Location: visualizar-equipos.php');
                    exit;
                }
            } else {
                $error = "Error al guardar el equipo: " . ($stmt ? $stmt->error : $conn->error);
                error_log("ERROR agregar-equipo: Error al ejecutar INSERT - " . ($stmt ? $stmt->error : $conn->error));
            }
        } else {
            $error = "Error en la preparación de la consulta: " . $conn->error;
        }
    }
}


$pageTitle = 'Agregar Equipo';
require_once 'includes/header.php';

// Verificar si existe columna id_empresa_usuario en empresas_equipos
$check_column_empresas = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column_empresas = $check_column_empresas && $check_column_empresas->num_rows > 0;

// Filtrar empresas por empresa del usuario
$empresas_query = "SELECT id_empresa, nombre FROM empresas_equipos";
$empresas_params = [];
$empresas_types = '';

if ($has_empresa_usuario_column_empresas && $user_empresa_id !== null) {
    $empresas_query .= " WHERE id_empresa_usuario = ?";
    $empresas_params[] = $user_empresa_id;
    $empresas_types = 'i';
}

$empresas_query .= " ORDER BY nombre";

if (!empty($empresas_params)) {
    $empresas_stmt = $conn->prepare($empresas_query);
    $empresas_stmt->bind_param($empresas_types, ...$empresas_params);
    $empresas_stmt->execute();
    $empresas_result = $empresas_stmt->get_result();
} else {
$empresas_result = $conn->query($empresas_query);
}

// Opciones de Ubicación y Activo (según empresa del usuario)
$opciones_ubicacion = [];
$opciones_activo = [];
$tabla_ubicaciones = $conn->query("SHOW TABLES LIKE 'inventario_config_ubicaciones'");
$tabla_activos = $conn->query("SHOW TABLES LIKE 'inventario_config_activos'");
if ($tabla_ubicaciones && $tabla_ubicaciones->num_rows > 0 && $tabla_activos && $tabla_activos->num_rows > 0 && $user_empresa_id !== null) {
    $st = $conn->prepare("SELECT valor FROM inventario_config_ubicaciones WHERE id_empresa_usuario = ? ORDER BY valor");
    $st->bind_param("i", $user_empresa_id);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $opciones_ubicacion[] = $r['valor'];
    }
    $res->free();
    $st->close();
    $st = $conn->prepare("SELECT valor FROM inventario_config_activos WHERE id_empresa_usuario = ? ORDER BY valor");
    $st->bind_param("i", $user_empresa_id);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $opciones_activo[] = $r['valor'];
    }
    $res->free();
    $st->close();
}
?>

<div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
    <div class="inicio-header mb-8">
        <div class="flex items-center gap-3 mb-1">
            <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg">
                <i class="fas fa-plus text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Agregar equipo</h1>
                <p class="inicio-header-subtitle">Registra un nuevo equipo en el inventario.</p>
            </div>
        </div>
       
    </div>

    <?php if (isset($error)): ?>
        <div class="inv-alert-error mb-6 flex items-start gap-3 px-4 py-3 rounded-xl border border-red-200 bg-red-50 text-red-800">
            <i class="fas fa-exclamation-circle mt-0.5 text-red-500"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" class="inv-form-card bg-white rounded-xl shadow-md border border-gray-100 p-5 lg:p-7" enctype="multipart/form-data">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-building w-4 mr-1 text-gray-400"></i> Empresa</label>
                <select name="id_empresa" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none" required>
                    <option value="">Seleccione una empresa</option>
                    <?php while ($empresa = $empresas_result->fetch_assoc()): ?>
                        <option value="<?php echo $empresa['id_empresa']; ?>"><?php echo htmlspecialchars($empresa['nombre']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-desktop w-4 mr-1 text-gray-400"></i> Nombre del equipo</label>
                <input type="text" name="nombre_equipo" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-user w-4 mr-1 text-gray-400"></i> Responsable</label>
                <input type="text" name="responsable" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-laptop w-4 mr-1 text-gray-400"></i> Sistema operativo</label>
                <input type="text" name="sistema_operativo" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-network-wired w-4 mr-1 text-gray-400"></i> Dirección MAC</label>
                <input type="text" name="direccion_mac" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none font-mono text-sm" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-tag w-4 mr-1 text-gray-400"></i> Tipo</label>
                <input type="text" name="tipo" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-industry w-4 mr-1 text-gray-400"></i> Fabricante</label>
                <input type="text" name="fabricante" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-cube w-4 mr-1 text-gray-400"></i> Modelo</label>
                <input type="text" name="modelo" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-barcode w-4 mr-1 text-gray-400"></i> Número de serie</label>
                <input type="text" name="numero_serie" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none font-mono text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-memory w-4 mr-1 text-gray-400"></i> RAM</label>
                <input type="text" name="ram" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-hdd w-4 mr-1 text-gray-400"></i> Disco duro</label>
                <input type="text" name="disco_duro" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-microchip w-4 mr-1 text-gray-400"></i> Procesador</label>
                <input type="text" name="procesador" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="select_ubicacion"><i class="fas fa-map-marker-alt w-4 mr-1 text-gray-400"></i> Ubicación</label>
                <select name="ubicacion" id="select_ubicacion" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                    <option value="">Seleccione ubicación</option>
                    <?php foreach ($opciones_ubicacion as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="select_activo_equipo"><i class="fas fa-check-circle w-4 mr-1 text-gray-400"></i> Activo del equipo</label>
                <select name="activo_equipo" id="select_activo_equipo" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                    <option value="">Seleccione activo</option>
                    <?php foreach ($opciones_activo as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-600 mb-1.5"><i class="fas fa-comment-alt w-4 mr-1 text-gray-400"></i> Observaciones</label>
            <textarea name="observaciones" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none resize-y" rows="3"></textarea>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-600 mb-2"><i class="fas fa-file-alt w-4 mr-1 text-gray-400"></i> Documentos (PDF, JPG, PNG, Excel, Word)</label>
            <input type="file" name="documentos[]" multiple accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:font-medium file:inv-brand-bg file:text-white file:cursor-pointer">
            <p class="mt-1 text-sm text-gray-500">Puedes seleccionar múltiples archivos.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100">
            <button type="submit" class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all">
                <i class="fas fa-save"></i> Guardar equipo
            </button>
            <a href="visualizar-equipos.php" class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<style>
.inv-page-wrap { max-width: 1200px; }
.inv-brand-bg { background-color: #1E2A3B; }
.inv-form-card .inv-input { min-height: 42px; }
.inv-form-card .inv-input:hover { border-color: #cbd5e1; }
.inv-btn-primary:hover { filter: brightness(1.08); }
.inicio-header {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
    padding: 1.25rem 1.75rem;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.inicio-header-subtitle {
    margin: 0.25rem 0 0;
    font-size: 0.9rem;
    color: #6b7280;
}
.inicio-header-date {
    color: #6b7280;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.inicio-header-date i {
    color: #1E2A3B;
}
@media (max-width: 768px) {
    .inicio-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
}
</style>

<?php if ($user_empresa_id === null): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var selEmpresa = document.querySelector('select[name="id_empresa"]');
    var selUbicacion = document.getElementById('select_ubicacion');
    var selActivo = document.getElementById('select_activo_equipo');
    if (!selEmpresa || !selUbicacion || !selActivo) return;
    function llenarOpciones(empresaId) {
        if (!empresaId) {
            selUbicacion.innerHTML = '<option value="">Seleccione ubicación</option>';
            selActivo.innerHTML = '<option value="">Seleccione activo</option>';
            return;
        }
        fetch('api/opciones-config.php?empresa=' + encodeURIComponent(empresaId))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                selUbicacion.innerHTML = '<option value="">Seleccione ubicación</option>';
                (data.ubicaciones || []).forEach(function(v) {
                    var o = document.createElement('option');
                    o.value = v;
                    o.textContent = v;
                    selUbicacion.appendChild(o);
                });
                selActivo.innerHTML = '<option value="">Seleccione activo</option>';
                (data.activos || []).forEach(function(v) {
                    var o = document.createElement('option');
                    o.value = v;
                    o.textContent = v;
                    selActivo.appendChild(o);
                });
            });
    }
    selEmpresa.addEventListener('change', function() {
        llenarOpciones(this.value);
    });
    if (selEmpresa.value) llenarOpciones(selEmpresa.value);
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>