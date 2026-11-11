<?php
require_once '../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
require_once '../../../log_activity.php';

checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

$response = ['success' => false, 'error' => null];

try {
    // Verificar si existe columna id_empresa_usuario
    $check_column = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
    $has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;
    
    // Verificar que la empresa pertenezca a la empresa del usuario
    if ($has_empresa_usuario_column && $user_empresa_id !== null) {
        $stmt = $conn->prepare("SELECT * FROM empresas_equipos WHERE id_empresa = ? AND id_empresa_usuario = ?");
        $stmt->bind_param("ii", $_POST['id_empresa'], $user_empresa_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM empresas_equipos WHERE id_empresa = ?");
        $stmt->bind_param("i", $_POST['id_empresa']);
    }
    
    $stmt->execute();
    $empresa_original = $stmt->get_result()->fetch_assoc();
    
    if (!$empresa_original) {
        $response['error'] = 'Empresa no encontrada o no tienes permiso para editarla';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Verificación adicional de permisos
    if ($user_empresa_id !== null && $has_empresa_usuario_column) {
        if ($empresa_original['id_empresa_usuario'] != $user_empresa_id) {
            $response['error'] = 'No tienes permiso para editar esta empresa';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }

    $stmt = $conn->prepare("UPDATE empresas_equipos SET nombre = ?, direccion = ? WHERE id_empresa = ?");
    $stmt->bind_param("ssi", $_POST['nombre'], $_POST['direccion'], $_POST['id_empresa']);
    
    if ($stmt->execute()) {
        $cambios = [];
        if ($empresa_original['nombre'] != $_POST['nombre']) {
            $cambios[] = "Nombre: " . $empresa_original['nombre'] . " → " . $_POST['nombre'];
        }
  
        $contacto_original = getContactoEmpresa($conn, $_POST['id_empresa'], CONTACTO_ORIGEN_EMPRESAS_EQUIPOS);

        if ($contacto_original || !empty($_POST['contacto']) || !empty($_POST['telefono']) || !empty($_POST['correo'])) {
            upsertContactoEmpresa(
                $conn,
                $_POST['id_empresa'],
                $_POST['contacto'] ?? '',
                $_POST['telefono'] ?? '',
                $_POST['correo'] ?? '',
                CONTACTO_ORIGEN_EMPRESAS_EQUIPOS
            );

            if ($contacto_original) {
                if ($contacto_original['contacto'] != ($_POST['contacto'] ?? '')) {
                    $cambios[] = "Contacto: " . $contacto_original['contacto'] . " → " . ($_POST['contacto'] ?? '');
                }
                if ($contacto_original['telefono'] != ($_POST['telefono'] ?? '')) {
                    $cambios[] = "Teléfono: " . $contacto_original['telefono'] . " → " . ($_POST['telefono'] ?? '');
                }
                if ($contacto_original['correo'] != ($_POST['correo'] ?? '')) {
                    $cambios[] = "Correo: " . $contacto_original['correo'] . " → " . ($_POST['correo'] ?? '');
                }
            } else {
                $cambios[] = "Se agregó información de contacto";
            }
        }

        logActivity(
            "Actualización de empresa (equipos)",
            "empresas_equipos",
            $_POST['id_empresa'],
            "Se actualizó la empresa: " . $_POST['nombre'] . "\n" . implode("\n", $cambios)
        );
        
        $response['success'] = true;
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);