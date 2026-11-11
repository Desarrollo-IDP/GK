<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../includes/empresa_helper.php';
require_once __DIR__ . '/../../../log_activity.php';
require_once __DIR__ . '/../../includes/equipment_counter_helper.php';

checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

ini_set('display_errors', 1); 
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            throw new Exception("Error al decodificar los datos JSON");
        }
        
       
        $stmt_empresa = $conn->prepare("SELECT nombre FROM empresas_equipos WHERE id_empresa = ?");
        if (!$stmt_empresa) {
            throw new Exception("Error en la preparación de la consulta de empresa: " . $conn->error);
        }
        
        $stmt_empresa->bind_param("i", $data['id_empresa']);
        $stmt_empresa->execute();
        $result = $stmt_empresa->get_result();
        $empresa = $result->fetch_assoc();
        
        if (!$empresa) {
            throw new Exception("Empresa no encontrada");
        }
        
        // Verificar permisos: si el usuario tiene empresa asignada, verificar que la empresa de inventario pertenezca a su empresa
        if ($user_empresa_id !== null) {
            $check_column_empresas = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
            $has_empresa_usuario_column_empresas = $check_column_empresas && $check_column_empresas->num_rows > 0;
            
            if ($has_empresa_usuario_column_empresas) {
                $verify_query = "SELECT id_empresa FROM empresas_equipos WHERE id_empresa = ? AND id_empresa_usuario = ?";
                $verify_stmt = $conn->prepare($verify_query);
                $verify_stmt->bind_param("ii", $data['id_empresa'], $user_empresa_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result->num_rows === 0) {
                    throw new Exception("No tienes permiso para crear equipos en esta empresa");
                }
                $verify_stmt->close();
            }
        }
        
        // Verificar si existe columna id_empresa_usuario en equipos
        $check_column_equipos = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
        $has_empresa_usuario_column_equipos = $check_column_equipos && $check_column_equipos->num_rows > 0;
      
        if ($has_empresa_usuario_column_equipos) {
            // Obtener el siguiente número de equipo para la empresa
            $equipo_number = getNextEquipmentNumber($conn, $user_empresa_id);
            
            // Verificar si existe columna equipo_number
            $check_column_equipo_number = $conn->query("SHOW COLUMNS FROM equipos LIKE 'equipo_number'");
            $has_equipo_number_column = $check_column_equipo_number && $check_column_equipo_number->num_rows > 0;
            
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
                    id_empresa,
                    id_empresa_usuario,
                    equipo_number
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $conn->error);
                }
                $stmt->bind_param("sssssssssssssiis", 
                    $empresa['nombre'],     
                    $data['nombre_equipo'],
                    $data['responsable'],
                    $data['sistema_operativo'],
                    $data['direccion_mac'],
                    $data['tipo'],
                    $data['fabricante'],
                    $data['modelo'],
                    $data['numero_serie'],
                    $data['ram'],
                    $data['disco_duro'],
                    $data['procesador'],
                    $data['observaciones'],
                    $data['id_empresa'],
                    $user_empresa_id,
                    $equipo_number
                );
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
                    id_empresa,
                    id_empresa_usuario
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $conn->error);
                }
                $stmt->bind_param("sssssssssssssii", 
                    $empresa['nombre'],     
                    $data['nombre_equipo'],
                    $data['responsable'],
                    $data['sistema_operativo'],
                    $data['direccion_mac'],
                    $data['tipo'],
                    $data['fabricante'],
                    $data['modelo'],
                    $data['numero_serie'],
                    $data['ram'],
                    $data['disco_duro'],
                    $data['procesador'],
                    $data['observaciones'],
                    $data['id_empresa'],
                    $user_empresa_id
                );
            }
        } else {
            // Insertar sin id_empresa_usuario
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
            id_empresa
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("Error en la preparación de la consulta: " . $conn->error);
        }
        $stmt->bind_param("sssssssssssssi", 
            $empresa['nombre'],     
            $data['nombre_equipo'],
            $data['responsable'],
            $data['sistema_operativo'],
            $data['direccion_mac'],
            $data['tipo'],
            $data['fabricante'],
            $data['modelo'],
            $data['numero_serie'],
            $data['ram'],
            $data['disco_duro'],
            $data['procesador'],
            $data['observaciones'],
            $data['id_empresa']
        );
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }
 
        $id_equipo = $conn->insert_id;
        
    // Debug: Log detallado
    error_log("DEBUG api/equipos: INSERT ejecutado");
    error_log("DEBUG api/equipos: affected_rows = " . $stmt->affected_rows);
    error_log("DEBUG api/equipos: insert_id = " . ($id_equipo ?? 'NULL'));
    
    // Si insert_id es 0 o null, intentar obtener el último ID de otra forma
    if ($id_equipo == 0 || $id_equipo === null) {
        error_log("WARNING api/equipos: insert_id es 0 o null, intentando obtener último ID manualmente");
        $last_id_query = $conn->query("SELECT LAST_INSERT_ID() as last_id");
        if ($last_id_query) {
            $last_id_row = $last_id_query->fetch_assoc();
            $id_equipo = $last_id_row['last_id'] ?? null;
            error_log("DEBUG api/equipos: LAST_INSERT_ID() = " . ($id_equipo ?? 'NULL'));
        }
    }
    
    // Construir detalles del log con información completa
    $detalles_log = "Se creó un nuevo equipo: " . $data['nombre_equipo'] . 
        "\nResponsable: " . $data['responsable'] .
        "\nTipo: " . ($data['tipo'] ?? 'N/A') .
        "\nModelo: " . ($data['modelo'] ?? 'N/A') .
        "\nID Equipo: " . ($id_equipo ?? 'NO DISPONIBLE');
    
    // Agregar número de equipo si existe
    if (isset($equipo_number) && !empty($equipo_number)) {
        $detalles_log .= "\nNúmero: " . $equipo_number;
    }

        logActivity(
            "Creación de equipo",
            "equipos",
            $id_equipo,
        $detalles_log
        );
        
        echo json_encode(['success' => true, 'id' => $id_equipo]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Este bloque parece ser código duplicado/legacy, se mantiene por compatibilidad
// pero el código principal está arriba en el bloque POST
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if ($data && isset($data['id_empresa'])) {
$conn->begin_transaction();
try {
        // Verificar permisos
        if ($user_empresa_id !== null) {
            $check_column_empresas = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
            $has_empresa_usuario_column_empresas = $check_column_empresas && $check_column_empresas->num_rows > 0;
            
            if ($has_empresa_usuario_column_empresas) {
                $verify_query = "SELECT id_empresa FROM empresas_equipos WHERE id_empresa = ? AND id_empresa_usuario = ?";
                $verify_stmt = $conn->prepare($verify_query);
                $verify_stmt->bind_param("ii", $data['id_empresa'], $user_empresa_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result->num_rows === 0) {
                    throw new Exception("No tienes permiso para crear equipos en esta empresa");
                }
                $verify_stmt->close();
            }
        }
        
        // Verificar si existe columna id_empresa_usuario
        $check_column_equipos = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
        $has_empresa_usuario_column_equipos = $check_column_equipos && $check_column_equipos->num_rows > 0;
        
        if ($has_empresa_usuario_column_equipos) {
            $stmt = $conn->prepare("INSERT INTO equipos (
                id_empresa, 
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
                id_empresa_usuario
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "issssssssssssi", 
                $data['id_empresa'],
                $data['nombre_equipo'],
                $data['responsable'],
                $data['sistema_operativo'],
                $data['direccion_mac'],
                $data['tipo'],
                $data['fabricante'],
                $data['modelo'],
                $data['numero_serie'],
                $data['ram'],
                $data['disco_duro'],
                $data['procesador'],
                $data['observaciones'],
                $user_empresa_id
            );
        } else {
    $stmt = $conn->prepare("INSERT INTO equipos (
        id_empresa, 
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
        observaciones
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "issssssssssss", 
        $data['id_empresa'],
        $data['nombre_equipo'],
        $data['responsable'],
        $data['sistema_operativo'],
        $data['direccion_mac'],
        $data['tipo'],
        $data['fabricante'],
        $data['modelo'],
        $data['numero_serie'],
        $data['ram'],
        $data['disco_duro'],
        $data['procesador'],
        $data['observaciones']
    );
        }
        
    if (!$stmt->execute()) {
        throw new Exception("Error al insertar equipo: " . $stmt->error);
    }
    
    $id_equipo = $conn->insert_id;
    
    // Debug: Log detallado
    error_log("DEBUG api/equipos (legacy): INSERT ejecutado");
    error_log("DEBUG api/equipos (legacy): affected_rows = " . $stmt->affected_rows);
    error_log("DEBUG api/equipos (legacy): insert_id = " . ($id_equipo ?? 'NULL'));
    
    // Si insert_id es 0 o null, intentar obtener el último ID de otra forma
    if ($id_equipo == 0 || $id_equipo === null) {
        error_log("WARNING api/equipos (legacy): insert_id es 0 o null, intentando obtener último ID manualmente");
        $last_id_query = $conn->query("SELECT LAST_INSERT_ID() as last_id");
        if ($last_id_query) {
            $last_id_row = $last_id_query->fetch_assoc();
            $id_equipo = $last_id_row['last_id'] ?? null;
            error_log("DEBUG api/equipos (legacy): LAST_INSERT_ID() = " . ($id_equipo ?? 'NULL'));
        }
    }
    
    // Construir detalles del log con información completa
    $detalles_log = "Se creó un nuevo equipo: " . $data['nombre_equipo'] . 
        "\nResponsable: " . ($data['responsable'] ?? 'N/A') .
        "\nTipo: " . ($data['tipo'] ?? 'N/A') .
        "\nModelo: " . ($data['modelo'] ?? 'N/A') .
        "\nID Equipo: " . ($id_equipo ?? 'NO DISPONIBLE');
    
    logActivity(
        "Creación de equipo",
        "equipos",
        $id_equipo,
        $detalles_log
    );
    $conn->commit();
    echo json_encode(['success' => true, 'id' => $id_equipo]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>