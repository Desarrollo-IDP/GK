<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../includes/empresa_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $id_equipo = isset($_POST['id_equipo']) ? (int)$_POST['id_equipo'] : 0;
    if ($id_equipo === 0) {
        throw new Exception('ID de equipo no válido');
    }
    
    // Verificar si existe columna id_empresa_usuario
    $check_column = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
    $has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;
    
    // Verificar que el equipo pertenezca a la empresa del usuario
    if ($has_empresa_usuario_column && $user_empresa_id !== null) {
        $verify_stmt = $conn->prepare("SELECT id_equipo FROM equipos WHERE id_equipo = ? AND id_empresa_usuario = ?");
        $verify_stmt->bind_param("ii", $id_equipo, $user_empresa_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            throw new Exception('No tienes permiso para editar este equipo');
        }
        $verify_stmt->close();
    }
    // Verificar si existe columna id_empresa_usuario
    $check_column_equipos = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
    $has_empresa_usuario_column_equipos = $check_column_equipos && $check_column_equipos->num_rows > 0;
    
    $nombre_cliente = '';
    $id_empresa = null;
    
    if (isset($_POST['id_empresa']) && $_POST['id_empresa'] !== '') {
        $id_empresa = (int)$_POST['id_empresa'];
        
        // Verificar permisos: si el usuario tiene empresa asignada, verificar que la empresa de inventario pertenezca a su empresa
        if ($user_empresa_id !== null) {
            $check_column_empresas = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
            $has_empresa_usuario_column_empresas = $check_column_empresas && $check_column_empresas->num_rows > 0;
            
            if ($has_empresa_usuario_column_empresas) {
                $verify_query = "SELECT id_empresa FROM empresas_equipos WHERE id_empresa = ? AND id_empresa_usuario = ?";
                $verify_stmt = $conn->prepare($verify_query);
                $verify_stmt->bind_param("ii", $id_empresa, $user_empresa_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result->num_rows === 0) {
                    throw new Exception("No tienes permiso para asignar equipos a esta empresa");
                }
                $verify_stmt->close();
            }
        }
        
        $stmt_empresa = $conn->prepare("SELECT nombre FROM empresas_equipos WHERE id_empresa = ?");
        if (!$stmt_empresa) {
            throw new Exception("Error en la preparación de la consulta de empresa: " . $conn->error);
        }
        
        $stmt_empresa->bind_param("i", $id_empresa);
        $stmt_empresa->execute();
        $result = $stmt_empresa->get_result();
        $empresa = $result->fetch_assoc();
        
        if (!$empresa) {
            throw new Exception("Empresa no encontrada");
        }
        
        $nombre_cliente = $empresa['nombre'];
        $stmt_empresa->close();
    }

    $nombre_equipo = $_POST['nombre_equipo'] ?? '';
    $responsable = $_POST['responsable'] ?? '';
    $sistema_operativo = $_POST['sistema_operativo'] ?? '';
    $direccion_mac = $_POST['direccion_mac'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $fabricante = $_POST['fabricante'] ?? '';
    $modelo = $_POST['modelo'] ?? '';
    $numero_serie = $_POST['numero_serie'] ?? '';
    $ram = $_POST['ram'] ?? '';
    $disco_duro = $_POST['disco_duro'] ?? '';
    $procesador = $_POST['procesador'] ?? '';
    $observaciones = $_POST['observaciones'] ?? '';

    // Construir UPDATE según si existe el campo id_empresa_usuario
    if ($has_empresa_usuario_column_equipos && $user_empresa_id !== null) {
        // UPDATE manteniendo id_empresa_usuario y verificando permisos
        $stmt = $conn->prepare("UPDATE equipos SET
            id_empresa = ?,
            nombre_cliente = ?,
            nombre_equipo = ?,
            responsable = ?,
            sistema_operativo = ?,
            direccion_mac = ?,
            tipo = ?,
            fabricante = ?,
            modelo = ?,
            numero_serie = ?,
            ram = ?,
            disco_duro = ?,
            procesador = ?,
            observaciones = ?
            WHERE id_equipo = ? AND id_empresa_usuario = ?");

        if (!$stmt) {
            throw new Exception('Error en la consulta: ' . $conn->error);
        }

        $stmt->bind_param("isssssssssssssii",
            $id_empresa,
            $nombre_cliente,  
            $nombre_equipo,
            $responsable,
            $sistema_operativo,
            $direccion_mac,
            $tipo,
            $fabricante,
            $modelo,
            $numero_serie,
            $ram,
            $disco_duro,
            $procesador,
            $observaciones,
            $id_equipo,
            $user_empresa_id
        );
    } else {
        // UPDATE sin id_empresa_usuario (campo no existe aún)
    $stmt = $conn->prepare("UPDATE equipos SET
        id_empresa = ?,
        nombre_cliente = ?,
        nombre_equipo = ?,
        responsable = ?,
        sistema_operativo = ?,
        direccion_mac = ?,
        tipo = ?,
        fabricante = ?,
        modelo = ?,
        numero_serie = ?,
        ram = ?,
        disco_duro = ?,
        procesador = ?,
        observaciones = ?
        WHERE id_equipo = ?");

    if (!$stmt) {
        throw new Exception('Error en la consulta: ' . $conn->error);
    }

    $stmt->bind_param("issssssssssssssi",
        $id_empresa,
        $nombre_cliente,  
        $nombre_equipo,
        $responsable,
        $sistema_operativo,
        $direccion_mac,
        $tipo,
        $fabricante,
        $modelo,
        $numero_serie,
        $ram,
        $disco_duro,
        $procesador,
        $observaciones,
        $id_equipo
    );
    }

    if (!$stmt->execute()) {
        throw new Exception('Error al actualizar el equipo: ' . $stmt->error);
    }
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}