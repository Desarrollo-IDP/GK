<?php
include '../../includes/common.php';
include '../../conexion/db.php';
include '../../../auth.php';
require_once '../../../includes/empresa_helper.php';

$message = '';
$messageType = '';
$user_empresa_id = getCurrentUserEmpresaId();

// Mostrar mensaje de éxito si viene de redirección
if (isset($_GET['msg']) && $_GET['msg'] === 'success') {
    $message = "Registro agregado correctamente";
    $messageType = "success";
}

// Función para verificar si una columna existe en una tabla
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

if (!isset($_GET['table']) || empty($_GET['table'])) {
    header('Location: configuration.php');
    exit;
}

$table = $_GET['table'];

$allowed_tables = [
    'categories' => 'Categorías',
    'statuses' => 'Estados',
    'contact_modes' => 'Modos de Contacto',
    'levels' => 'Niveles',
    'impacts' => 'Impactos',
   // 'urgencies' => 'Urgencias',
    'priorities' => 'Prioridades',
    'classifications' => 'Clasificaciones',
    'empresas' => 'Empresas',
    'level' => 'Nivel del técnico',
    // Nuevo catálogo para correos en copia
    'ticket_cc_emails' => 'Correos en copia',
];

if (!array_key_exists($table, $allowed_tables)) {
    header('Location: configuration.php');
    exit;
}

$table_label = $allowed_tables[$table];

$catalog_singular = [
    'categories' => ['article' => 'nueva', 'label' => 'Categoría'],
    'statuses' => ['article' => 'nuevo', 'label' => 'Estado'],
    'contact_modes' => ['article' => 'nuevo', 'label' => 'Modo de contacto'],
    'levels' => ['article' => 'nuevo', 'label' => 'Nivel'],
    'impacts' => ['article' => 'nuevo', 'label' => 'Impacto'],
    'priorities' => ['article' => 'nueva', 'label' => 'Prioridad'],
    'classifications' => ['article' => 'nueva', 'label' => 'Clasificación'],
    'empresas' => ['article' => 'nueva', 'label' => 'Empresa'],
    'level' => ['article' => 'nuevo', 'label' => 'Nivel del técnico'],
    'ticket_cc_emails' => ['article' => 'nuevo', 'label' => 'Correo en copia'],
];
$singular_meta = $catalog_singular[$table] ?? ['article' => 'nuevo', 'label' => rtrim($table_label, 's')];
$add_modal_title = 'Agregar ' . $singular_meta['article'] . ' ' . $singular_meta['label'];
$edit_modal_title = 'Editar ' . $singular_meta['label'];

// Manejo de creación (ADD) simplificado y robusto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {

    if ($table === 'empresas') {
        // Lógica específica para empresas
        $name = trim($_POST['name'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if ($name === '') {
            $message = "El nombre es obligatorio";
            $messageType = "danger";
        } else {
            // Verificar duplicado por nombre
            $check_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM empresas WHERE nombre = ?");
            if (!$check_stmt) {
                $message = "Error al preparar la verificación de nombre: " . $conn->error;
                $messageType = "danger";
            } else {
                $check_stmt->bind_param('s', $name);
                if (!$check_stmt->execute()) {
                    $message = "Error al ejecutar la verificación de nombre: " . $check_stmt->error;
                    $messageType = "danger";
                } else {
                    $res = $check_stmt->get_result()->fetch_assoc();
                    $exists = $res['c'] > 0;
                    if ($exists) {
                        $message = "Ya existe un registro con este nombre";
                        $messageType = "danger";
                    } else {
                        // Insert simple (sin complicar con id_empresa por ahora)
                        $stmt = $conn->prepare("INSERT INTO empresas (nombre, direccion) VALUES (?, ?)");
                        if (!$stmt) {
                            $message = "Error al preparar INSERT: " . $conn->error;
                            $messageType = "danger";
                        } else {
                            $stmt->bind_param('ss', $name, $direccion);
                            if ($stmt->execute()) {
                                header("Location: manage.php?table=" . urlencode($table) . "&msg=success");
                                exit;
                            } else {
                                $message = "Error al agregar el registro: " . $stmt->error;
                                $messageType = "danger";
                            }
                        }
                    }
                }
                $check_stmt->close();
            }
        }
    } else {
        // Lógica genérica para el resto de tablas (incluyendo statuses)
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        // Para tabla de correos en copia, manejar campo de activo
        $is_cc_table = ($table === 'ticket_cc_emails');
        $is_active = ($is_cc_table && isset($_POST['is_active'])) ? 1 : 0;

        if ($name === '') {
            $message = "El nombre es obligatorio";
            $messageType = "danger";
        } else {
            // Verificar si la tabla tiene columna id_empresa
            $has_empresa_column = columnExists($conn, $table, 'id_empresa');

            // Verificar duplicado por nombre (y empresa si aplica)
            if ($has_empresa_column && $user_empresa_id !== null) {
                $check_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM {$table} WHERE name = ? AND id_empresa = ?");
                if (!$check_stmt) {
                    $message = "Error al preparar la verificación de nombre: " . $conn->error;
                    $messageType = "danger";
                } else {
                    $check_stmt->bind_param('si', $name, $user_empresa_id);
                }
            } else {
                $check_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM {$table} WHERE name = ?");
                if (!$check_stmt) {
                    $message = "Error al preparar la verificación de nombre: " . $conn->error;
                    $messageType = "danger";
                } else {
                    $check_stmt->bind_param('s', $name);
                }
            }

            if ($check_stmt) {
                if (!$check_stmt->execute()) {
                    $message = "Error al ejecutar la verificación de nombre: " . $check_stmt->error;
                    $messageType = "danger";
                } else {
                    $res = $check_stmt->get_result()->fetch_assoc();
                    $exists = $res['c'] > 0;

                    if ($exists) {
                        $message = "Ya existe un registro con este nombre";
                        $messageType = "danger";
                    } else {
                        // Preparar INSERT
                        if ($has_empresa_column && $user_empresa_id !== null) {
                            if ($is_cc_table) {
                                $stmt = $conn->prepare("INSERT INTO {$table} (name, description, id_empresa, is_active) VALUES (?, ?, ?, ?)");
                                if (!$stmt) {
                                    $message = "Error al preparar INSERT: " . $conn->error;
                                    $messageType = "danger";
                                } else {
                                    $stmt->bind_param('ssii', $name, $description, $user_empresa_id, $is_active);
                                }
                            } else {
                                $stmt = $conn->prepare("INSERT INTO {$table} (name, description, id_empresa) VALUES (?, ?, ?)");
                                if (!$stmt) {
                                    $message = "Error al preparar INSERT: " . $conn->error;
                                    $messageType = "danger";
                                } else {
                                    $stmt->bind_param('ssi', $name, $description, $user_empresa_id);
                                }
                            }
                        } else {
                            if ($is_cc_table) {
                                $stmt = $conn->prepare("INSERT INTO {$table} (name, description, is_active) VALUES (?, ?, ?)");
                                if (!$stmt) {
                                    $message = "Error al preparar INSERT: " . $conn->error;
                                    $messageType = "danger";
                                } else {
                                    $stmt->bind_param('ssi', $name, $description, $is_active);
                                }
                            } else {
                                $stmt = $conn->prepare("INSERT INTO {$table} (name, description) VALUES (?, ?)");
                                if (!$stmt) {
                                    $message = "Error al preparar INSERT: " . $conn->error;
                                    $messageType = "danger";
                                } else {
                                    $stmt->bind_param('ss', $name, $description);
                                }
                            }
                        }

                        if (isset($stmt) && $stmt) {
                            if ($stmt->execute()) {
                                header("Location: manage.php?table=" . urlencode($table) . "&msg=success");
                                exit;
                            } else {
                                $message = "Error al agregar el registro: " . $stmt->error;
                                $messageType = "danger";
                            }
                        }
                    }
                }
                $check_stmt->close();
            }
        }
    }
}

// Función auxiliar para verificar si un ID está protegido (solo para statuses)
function isProtectedStatusId($table, $id) {
    if ($table === 'statuses' && in_array((int)$id, [1, 2, 3, 10])) {
        return true;
    }
    return false;
}

// Función auxiliar para obtener los campos de empresas
function getEmpresaFields($row = null) {
    return [
        'id' => $row['id_empresa'] ?? '',
        'name' => $row['nombre'] ?? '',
        'description' => $row['direccion'] ?? ''
    ];
}

// Cambiar el nombre de columna para eliminar
// Para empresas, usar id_empresa como identificador (es la PK)
if ($table === 'empresas') {
    $id_column = 'id_empresa';
} else {
    $id_column = 'id';
}

// Manejo de eliminación de registros
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Verificar si el registro está protegido (solo para statuses)
    if (isProtectedStatusId($table, $id)) {
        $message = "No se puede eliminar este registro porque está protegido del sistema.";
        $messageType = "danger";
    } else {
        // Verificar si la tabla tiene columna id_empresa
        $has_empresa_column = columnExists($conn, $table, 'id_empresa');

        if ($table !== 'empresas' && $has_empresa_column && $user_empresa_id !== null) {
            // Verificar que el registro pertenece a la empresa del usuario
            $check_empresa_query = "SELECT id FROM {$table} WHERE id = ? AND id_empresa = ?";
            $check_empresa_stmt = $conn->prepare($check_empresa_query);
            $check_empresa_stmt->bind_param("ii", $id, $user_empresa_id);
            $check_empresa_stmt->execute();
            $check_empresa_result = $check_empresa_stmt->get_result();

            if ($check_empresa_result->num_rows === 0) {
                $message = "No tienes permiso para eliminar este registro.";
                $messageType = "danger";
                $check_empresa_stmt->close();
            } else {
                $check_empresa_stmt->close();

                // Verificar si el registro está en uso en tickets
                $check_query = "SELECT COUNT(*) as count FROM tickets WHERE {$id_column} = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("i", $id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $is_in_use = $check_result->fetch_assoc()['count'] > 0;
                $check_stmt->close();

                if ($is_in_use) {
                    $message = "No se puede eliminar este registro porque está siendo utilizado en tickets existentes.";
                    $messageType = "danger";
                } else {
                    $stmt = $conn->prepare("DELETE FROM {$table} WHERE {$id_column} = ? AND id_empresa = ?");
                    $stmt->bind_param("ii", $id, $user_empresa_id);
                    if ($stmt->execute()) {
                        $message = "Registro eliminado correctamente";
                        $messageType = "success";
                    } else {
                        $message = "Error al eliminar el registro: " . $conn->error;
                        $messageType = "danger";
                    }
                    $stmt->close();
                }
            }
        } else {
            // Admin, tabla empresas o tabla sin campo id_empresa - verificar uso sin filtro de empresa
            $check_query = "SELECT COUNT(*) as count FROM tickets WHERE {$id_column} = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $is_in_use = $check_result->fetch_assoc()['count'] > 0;
            $check_stmt->close();

            if ($is_in_use) {
                $message = "No se puede eliminar este registro porque está siendo utilizado en tickets existentes.";
                $messageType = "danger";
            } else {
                $stmt = $conn->prepare("DELETE FROM {$table} WHERE {$id_column} = ?");
                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    $message = "Registro eliminado correctamente";
                    $messageType = "success";
                } else {
                    $message = "Error al eliminar el registro: " . $conn->error;
                    $messageType = "danger";
                }
                $stmt->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = $_POST['id'];
    if ($table === 'empresas') {
        $name = $_POST['name'];
        $direccion = $_POST['direccion'] ?? '';
        if (empty($name)) {
            $message = "El nombre es obligatorio";
            $messageType = "danger";
        } else {
            // Verificar si la tabla tiene columna id_empresa
            $has_empresa_column = columnExists($conn, $table, 'id_empresa');
            
            // Verificar nombre único dentro de la empresa del usuario
            $check_query = "SELECT COUNT(*) as count FROM empresas WHERE nombre = ? AND id_empresa != ?";
            $check_params = [$name, $id];
            $check_types = 'si';
            
            if ($has_empresa_column && $user_empresa_id !== null) {
                $check_query .= " AND id_empresa = ?";
                $check_params[] = $user_empresa_id;
                $check_types .= 'i';
            }
            
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param($check_types, ...$check_params);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $name_exists = $check_result->fetch_assoc()['count'] > 0;
            $check_stmt->close();
            
            if ($name_exists) {
                $message = "Ya existe otro registro con este nombre" . ($has_empresa_column && $user_empresa_id !== null ? " en esta empresa" : "");
                $messageType = "danger";
            } else {
                // Verificar que el registro pertenece a la empresa del usuario antes de editar
                // Para empresas, id_empresa es la PK, así que verificamos directamente
                if ($has_empresa_column && $user_empresa_id !== null) {
                    // Si id_empresa es la PK, entonces el id debe coincidir con la empresa del usuario
                    if ($id != $user_empresa_id) {
                        $message = "No tienes permiso para editar este registro.";
                        $messageType = "danger";
                    } else {
                        $stmt = $conn->prepare("UPDATE empresas SET nombre = ?, direccion = ? WHERE id_empresa = ?");
                        $stmt->bind_param("ssi", $name, $direccion, $id);
                        if ($stmt->execute()) {
                            $message = "Registro actualizado correctamente";
                            $messageType = "success";
                        } else {
                            $message = "Error al actualizar el registro: " . $conn->error;
                            $messageType = "danger";
                        }
                        $stmt->close();
                    }
            } else {
                $stmt = $conn->prepare("UPDATE empresas SET nombre = ?, direccion = ? WHERE id_empresa = ?");
                $stmt->bind_param("ssi", $name, $direccion, $id);
                if ($stmt->execute()) {
                    $message = "Registro actualizado correctamente";
                    $messageType = "success";
                } else {
                    $message = "Error al actualizar el registro: " . $conn->error;
                    $messageType = "danger";
                }
                $stmt->close();
                }
            }
        }
    } else {
        $field_name = 'description';
        $description = $_POST[$field_name] ?? '';
        // Para tabla de correos en copia, manejar campo de activo
        $is_cc_table = ($table === 'ticket_cc_emails');
        $is_active = ($is_cc_table && isset($_POST['is_active'])) ? 1 : 0;
        
        // Verificar si el registro está protegido (solo para statuses)
        $is_protected = isProtectedStatusId($table, $id);
        
        // Si es un registro protegido, no esperamos el campo name en POST (está deshabilitado)
        // Si no es protegido, el campo name debe estar presente
        if ($is_protected) {
            // Para registros protegidos, el campo name no se envía porque está deshabilitado
            // No necesitamos obtenerlo del POST
            // Para registros protegidos, solo actualizar la descripción
            // Verificar que el registro pertenece a la empresa del usuario antes de editar
            $has_empresa_column = columnExists($conn, $table, 'id_empresa');
            
            if ($has_empresa_column && $user_empresa_id !== null) {
                $verify_query = "SELECT id FROM {$table} WHERE id = ? AND id_empresa = ?";
                $verify_stmt = $conn->prepare($verify_query);
                $verify_stmt->bind_param("ii", $id, $user_empresa_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                if ($verify_result->num_rows === 0) {
                    $message = "No tienes permiso para editar este registro.";
                    $messageType = "danger";
                    $verify_stmt->close();
                } else {
                    $verify_stmt->close();
                    // Solo actualizar description, no name
                    $stmt = $conn->prepare("UPDATE {$table} SET description = ? WHERE id = ? AND id_empresa = ?");
                    $stmt->bind_param("sii", $description, $id, $user_empresa_id);
                    if ($stmt->execute()) {
                        $message = "Descripción actualizada correctamente";
                        $messageType = "success";
                    } else {
                        $message = "Error al actualizar el registro: " . $conn->error;
                        $messageType = "danger";
                    }
                    $stmt->close();
                }
            } else {
                // Solo actualizar description, no name
                $stmt = $conn->prepare("UPDATE {$table} SET description = ? WHERE id = ?");
                $stmt->bind_param("si", $description, $id);
                if ($stmt->execute()) {
                    $message = "Descripción actualizada correctamente";
                    $messageType = "success";
                } else {
                    $message = "Error al actualizar el registro: " . $conn->error;
                    $messageType = "danger";
                }
                $stmt->close();
            }
        } else {
            // Para registros no protegidos, validar y actualizar normalmente
            // El campo name debe estar presente en POST
            if (!isset($_POST['name']) || empty($_POST['name'])) {
                $message = "El nombre es obligatorio";
                $messageType = "danger";
            } else {
                $name = $_POST['name'];
                // Verificar si la tabla tiene columna id_empresa
                $has_empresa_column = columnExists($conn, $table, 'id_empresa');
                
                // Verificar nombre único (dentro de la empresa si el campo existe)
                $check_query = "SELECT COUNT(*) as count FROM {$table} WHERE name = ? AND id != ?";
                $check_params = [$name, $id];
                $check_types = 'si';
                if ($has_empresa_column && $user_empresa_id !== null) {
                    $check_query .= " AND id_empresa = ?";
                    $check_params[] = $user_empresa_id;
                    $check_types .= 'i';
                }
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param($check_types, ...$check_params);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $name_exists = $check_result->fetch_assoc()['count'] > 0;
                $check_stmt->close();

                if ($name_exists) {
                    $message = "Ya existe otro registro con este nombre";
                    $messageType = "danger";
                } else {
                    // Preparar sentencia de actualización según empresa y tipo de tabla
                    $stmt = null;

                    if ($has_empresa_column && $user_empresa_id !== null) {
                        // Verificar que el registro pertenece a la empresa del usuario antes de editar
                        $verify_query = "SELECT id FROM {$table} WHERE id = ? AND id_empresa = ?";
                        $verify_stmt = $conn->prepare($verify_query);
                        $verify_stmt->bind_param("ii", $id, $user_empresa_id);
                        $verify_stmt->execute();
                        $verify_result = $verify_stmt->get_result();
                        $belongs_to_empresa = $verify_result->num_rows > 0;
                        $verify_stmt->close();

                        if (!$belongs_to_empresa) {
                            $message = "No tienes permiso para editar este registro.";
                            $messageType = "danger";
                        } else {
                            if ($is_cc_table) {
                                // Actualizar también is_active para correos en copia
                                $stmt = $conn->prepare("UPDATE {$table} SET name = ?, description = ?, is_active = ? WHERE id = ? AND id_empresa = ?");
                                $stmt->bind_param("ssiii", $name, $description, $is_active, $id, $user_empresa_id);
                            } else {
                                $stmt = $conn->prepare("UPDATE {$table} SET name = ?, description = ? WHERE id = ? AND id_empresa = ?");
                                $stmt->bind_param("ssii", $name, $description, $id, $user_empresa_id);
                            }
                        }
                    } else {
                        // Tabla sin id_empresa o usuario admin
                        if ($is_cc_table) {
                            $stmt = $conn->prepare("UPDATE {$table} SET name = ?, description = ?, is_active = ? WHERE id = ?");
                            $stmt->bind_param("ssii", $name, $description, $is_active, $id);
                        } else {
                            $stmt = $conn->prepare("UPDATE {$table} SET name = ?, description = ? WHERE id = ?");
                            $stmt->bind_param("ssi", $name, $description, $id);
                        }
                    }

                    if (isset($stmt) && $stmt) {
                        if ($stmt->execute()) {
                            $message = "Registro actualizado correctamente";
                            $messageType = "success";
                        } else {
                            $message = "Error al actualizar el registro: " . $conn->error;
                            $messageType = "danger";
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

// Consulta para mostrar la tabla con filtro de empresa (solo si el campo existe)
$has_empresa_column = columnExists($conn, $table, 'id_empresa');
$show_empresa_column = $has_empresa_column && $user_empresa_id === null; // Solo mostrar columna empresa si es admin

if ($table === 'empresas') {
    $query = "SELECT e.*, eu.nombre as empresa_nombre 
              FROM empresas e
              LEFT JOIN empresas_usuarios eu ON e.id_empresa = eu.id_empresa";
    $params = [];
    $types = '';
    
    if ($has_empresa_column && $user_empresa_id !== null) {
        $query .= " WHERE e.id_empresa = ?";
        $params[] = $user_empresa_id;
        $types = 'i';
    }
    
    $query .= " ORDER BY e.nombre";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
    $result = $conn->query($query);
    }
} else {
    // Para otras tablas, hacer JOIN con empresas_usuarios si tienen campo id_empresa
    if ($has_empresa_column) {
        $query = "SELECT t.*, eu.nombre as empresa_nombre 
                  FROM {$table} t
                  LEFT JOIN empresas_usuarios eu ON t.id_empresa = eu.id_empresa";
    } else {
        $query = "SELECT * FROM {$table}";
    }
    
    $params = [];
    $types = '';
    
    // Para statuses, mostrar siempre los predeterminados (1, 2, 3, 10) y los de la empresa del usuario
    if ($table === 'statuses' && $has_empresa_column && $user_empresa_id !== null) {
        $query .= " WHERE (t.id IN (1, 2, 3, 10) OR t.id_empresa = ?)";
        $params[] = $user_empresa_id;
        $types = 'i';
    } elseif ($has_empresa_column && $user_empresa_id !== null) {
        // Para otras tablas, filtrar solo por empresa del usuario
        $query .= " WHERE t.id_empresa = ?";
        $params[] = $user_empresa_id;
        $types = 'i';
    }
    
    $query .= " ORDER BY t.name";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar <?php echo $table_label; ?> - Ticket GK</title>
    
    <!-- Fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Sistema de diseño moderno -->
    <link rel="stylesheet" href="../../css/modern-design-system.css">
</head>
<body>
    <?php include '../../includes/modern-header.php'; ?>
    
    <div class="body-bg">
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/modern-sidebar.php'; ?>
            
            <main class="main-content main-content-boxed">
                <div class="tickets-page-header">
                    <div>
                        <h1 class="h2 mb-0">
                            <i class="fas fa-cogs me-2"></i>
                            Administrar <?php echo $table_label; ?>
                        </h1>
                    </div>
                    <div class="tickets-page-actions">
                        <a href="configuration.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
                        </a>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                            <i class="fas fa-plus me-2"></i>Agregar Nuevo
                        </button>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="content-card">
                    <div class="card-header-modern">
                        <h5 class="card-title">
                            <i class="fas fa-list me-2"></i>
                            Lista de <?php echo $table_label; ?>
                        </h5>
                        <span class="badge bg-primary"><?php echo $result->num_rows; ?> registros</span>
                    </div>
                    <div class="card-body-modern">
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead class="table-header-modern">
                                    <tr>
                                        <!-- <th>ID</th> -->
                                        <th>Nombre</th>
                                        <th><?php echo ($table === 'empresas') ? 'Dirección' : 'Descripción';?></th>
                                        <?php if ($table === 'ticket_cc_emails'): ?>
                                        <th>Activo</th>
                                        <?php endif; ?>
                                        <?php if ($show_empresa_column): ?>
                                        <th>Empresa</th>
                                        <?php endif; ?>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <?php $fields = ($table === 'empresas') ? getEmpresaFields($row) : $row; ?>
                                            <tr class="table-row-modern">
                                                <!-- <td><span class="badge bg-secondary"><?php echo $fields['id']; ?></span></td> -->
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-tag me-2 text-primary"></i>
                                                        <strong><?php echo htmlspecialchars($fields['name']); ?></strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($fields['description'])): ?>
                                                        <span class="text-muted"><?php echo htmlspecialchars($fields['description']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">Sin descripción</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($table === 'ticket_cc_emails'): ?>
                                                <td>
                                                    <?php if (!empty($fields['is_active'])): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check me-1"></i>Activo
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-ban me-1"></i>Inactivo
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endif; ?>
                                                <?php if ($show_empresa_column): ?>
                                                <td>
                                                    <?php if (!empty($row['empresa_nombre'])): ?>
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-building me-1"></i>
                                                            <?php echo htmlspecialchars($row['empresa_nombre']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-question-circle me-1"></i>
                                                            Sin empresa
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endif; ?>
                                                <td>
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <button type="button" class="btn btn-sm btn-outline-primary edit-btn" 
                                                                data-id="<?php echo $fields['id']; ?>" 
                                                                data-name="<?php echo htmlspecialchars($fields['name']); ?>" 
                                                                data-description="<?php echo htmlspecialchars($fields['description'] ?? ''); ?>"
                                                                data-is-active="<?php echo ($table === 'ticket_cc_emails') ? (int)($fields['is_active'] ?? 1) : 1; ?>"
                                                                data-bs-toggle="modal" data-bs-target="#editModal">
                                                            <i class="fas fa-edit"></i>
                                                            <span class="d-none d-md-inline ms-1">Editar</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <?php
                                                // Nombre, Descripción, Acciones son 3 columnas base
                                                $colspan = 3;
                                                if ($show_empresa_column) {
                                                    $colspan++;
                                                }
                                                if ($table === 'ticket_cc_emails') {
                                                    $colspan++;
                                                }
                                            ?>
                                            <td colspan="<?php echo $colspan; ?>" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No hay registros disponibles</h5>
                                                    <p class="text-muted">Comienza agregando un nuevo registro usando el botón "Agregar Nuevo"</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    </div>

    <style>
    .config-modal .modal-dialog {
        max-width: 480px;
    }

    .config-modal .modal-content.modal-modern {
        border: 0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(23, 32, 51, 0.22);
        background: #fff;
    }

    .config-modal .modal-header.modal-header-modern {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.15rem 1.25rem;
        border-bottom: 1px solid #e8edf5;
        background:
            linear-gradient(180deg, #fbfcfe 0%, #f5f7fb 100%);
    }

    .config-modal .modal-title-wrap {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        min-width: 0;
    }

    .config-modal .modal-title-icon {
        width: 2.45rem;
        height: 2.45rem;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #202654;
        color: #fff;
        flex-shrink: 0;
        font-size: 0.95rem;
    }

    .config-modal .modal-title-icon--edit {
        background: #3a4478;
    }

    .config-modal .modal-kicker {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #7b8798;
        margin-bottom: 0.15rem !important;
    }

    .config-modal .modal-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 750;
        color: #202654;
        letter-spacing: -0.01em;
        line-height: 1.3;
    }

    .config-modal .modal-body {
        padding: 1.25rem 1.25rem 0.5rem;
    }

    .config-modal .form-group-modern {
        margin-bottom: 1.1rem;
    }

    .config-modal .form-label-modern {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.45rem;
        font-size: 0.84rem;
        font-weight: 650;
        color: #334155;
    }

    .config-modal .form-label-modern i {
        width: 1rem;
        color: #64748b;
        text-align: center;
    }

    .config-modal .form-label-modern .req {
        color: #dc2626;
        font-weight: 700;
    }

    .config-modal .form-control.form-control-modern {
        display: block;
        width: 100%;
        border: 1px solid #d7dee8;
        border-radius: 10px;
        padding: 0.7rem 0.85rem;
        font-size: 0.92rem;
        color: #172033;
        background: #fff;
        box-shadow: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        resize: vertical;
    }

    .config-modal .form-control.form-control-modern:focus {
        border-color: #93a4e0;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(32, 38, 84, 0.12);
    }

    .config-modal .form-control.form-control-modern:disabled,
    .config-modal .form-control.form-control-modern.bg-light {
        background: #f1f5f9 !important;
        color: #64748b;
        border-color: #e2e8f0;
    }

    .config-modal .config-modal-hint {
        display: block;
        margin-top: 0.4rem;
        font-size: 0.78rem;
        color: #b45309;
    }

    .config-modal .config-modal-switch {
        padding: 0.75rem 0.9rem;
        border: 1px solid #e8edf5;
        border-radius: 10px;
        background: #f8fafc;
        margin-bottom: 0.75rem;
    }

    .config-modal .config-modal-switch .form-check-input {
        width: 2.4rem;
        height: 1.25rem;
        margin-top: 0.1rem;
        cursor: pointer;
    }

    .config-modal .config-modal-switch .form-check-input:checked {
        background-color: #202654;
        border-color: #202654;
    }

    .config-modal .config-modal-switch .form-check-label {
        font-weight: 600;
        color: #334155;
        margin-left: 0.35rem;
    }

    .config-modal .modal-footer.modal-footer-modern {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.6rem;
        padding: 0.95rem 1.25rem 1.15rem;
        border-top: 1px solid #e8edf5;
        background: #fbfcfe;
    }

    .config-modal .modal-footer-modern--split {
        justify-content: space-between;
    }

    .config-modal .modal-footer-actions {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        margin-left: auto;
    }

    .config-modal .btn-modal-cancel,
    .config-modal .btn-modal-primary,
    .config-modal .btn-modal-danger {
        border-radius: 10px;
        font-weight: 650;
        min-height: 2.45rem;
        padding: 0.55rem 1rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }

    .config-modal .btn-modal-cancel {
        background: #fff;
        border: 1px solid #d5d6de;
        color: #475569;
    }

    .config-modal .btn-modal-cancel:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #334155;
    }

    .config-modal .btn-modal-primary {
        background: #202654;
        border: 1px solid #202654;
        color: #fff;
    }

    .config-modal .btn-modal-primary:hover {
        background: #3a4478;
        border-color: #3a4478;
        color: #fff;
        transform: translateY(-1px);
    }

    .config-modal .btn-modal-danger {
        background: #fff;
        border: 1px solid #fecaca;
        color: #b91c1c;
    }

    .config-modal .btn-modal-danger:hover {
        background: #fef2f2;
        border-color: #fca5a5;
        color: #991b1b;
    }

    @media (max-width: 575.98px) {
        .config-modal .modal-footer-modern--split {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .config-modal .modal-footer-actions {
            width: 100%;
            margin-left: 0;
        }

        .config-modal .modal-footer-actions .btn,
        .config-modal .btn-modal-danger {
            flex: 1 1 auto;
        }
    }
    </style>

    <!-- Modal para agregar -->
    <div class="modal fade config-modal" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-modern">
                <div class="modal-header modal-header-modern">
                    <div class="modal-title-wrap">
                        <span class="modal-title-icon" aria-hidden="true"><i class="fas fa-plus"></i></span>
                        <div>
                            <p class="modal-kicker mb-0">Configuración</p>
                            <h5 class="modal-title" id="addModalLabel"><?php echo htmlspecialchars($add_modal_title); ?></h5>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group-modern">
                            <label for="name" class="form-label-modern">
                                <?php if ($table === 'ticket_cc_emails'): ?>
                                    <i class="fas fa-envelope"></i>Correo electrónico <span class="req">*</span>
                                <?php else: ?>
                                    <i class="fas fa-tag"></i>Nombre <span class="req">*</span>
                                <?php endif; ?>
                            </label>
                            <input
                                type="<?php echo $table === 'ticket_cc_emails' ? 'email' : 'text'; ?>"
                                class="form-control form-control-modern"
                                id="name"
                                name="name"
                                <?php if ($table === 'ticket_cc_emails'): ?>
                                    placeholder="correo@empresa.com"
                                <?php else: ?>
                                    placeholder="Escribe el nombre"
                                <?php endif; ?>
                                required
                            >
                        </div>
                        <div class="form-group-modern">
                            <label for="description" class="form-label-modern">
                                <i class="fas fa-align-left"></i>
                                <?php
                                    if ($table === 'empresas') {
                                        echo 'Dirección';
                                    } elseif ($table === 'ticket_cc_emails') {
                                        echo 'Descripción / Nota (opcional)';
                                    } else {
                                        echo 'Descripción';
                                    }
                                ?>
                            </label>
                            <textarea
                                class="form-control form-control-modern"
                                id="description"
                                name="<?php echo ($table === 'empresas') ? 'direccion' : 'description'; ?>"
                                rows="3"
                                <?php if ($table === 'ticket_cc_emails'): ?>
                                    placeholder="Ej. Copia a supervisor de soporte"
                                <?php else: ?>
                                    placeholder="Opcional"
                                <?php endif; ?>
                            ></textarea>
                        </div>
                        <?php if ($table === 'ticket_cc_emails'): ?>
                        <div class="form-check form-switch config-modal-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">Activo</label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer modal-footer-modern">
                        <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-modal-primary">
                            <i class="fas fa-save me-1"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar -->
    <div class="modal fade config-modal" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-modern">
                <div class="modal-header modal-header-modern">
                    <div class="modal-title-wrap">
                        <span class="modal-title-icon modal-title-icon--edit" aria-hidden="true"><i class="fas fa-pen"></i></span>
                        <div>
                            <p class="modal-kicker mb-0">Configuración</p>
                            <h5 class="modal-title" id="editModalLabel"><?php echo htmlspecialchars($edit_modal_title); ?></h5>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="POST" action="" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="form-group-modern">
                            <label for="edit_name" class="form-label-modern">
                                <?php if ($table === 'ticket_cc_emails'): ?>
                                    <i class="fas fa-envelope"></i>Correo electrónico <span class="req">*</span>
                                <?php else: ?>
                                    <i class="fas fa-tag"></i>Nombre <span class="req">*</span>
                                <?php endif; ?>
                            </label>
                            <input
                                type="<?php echo $table === 'ticket_cc_emails' ? 'email' : 'text'; ?>"
                                class="form-control form-control-modern"
                                id="edit_name"
                                name="name"
                                <?php if ($table === 'ticket_cc_emails'): ?>
                                    placeholder="correo@empresa.com"
                                <?php endif; ?>
                                required
                            >
                            <small class="config-modal-hint d-none" id="name_protected_warning">
                                <i class="fas fa-lock me-1"></i>Este nombre está protegido y no se puede modificar.
                            </small>
                        </div>
                        <div class="form-group-modern">
                            <label for="edit_description" class="form-label-modern">
                                <i class="fas fa-align-left"></i>
                                <?php
                                    if ($table === 'empresas') {
                                        echo 'Dirección';
                                    } elseif ($table === 'ticket_cc_emails') {
                                        echo 'Descripción / Nota (opcional)';
                                    } else {
                                        echo 'Descripción';
                                    }
                                ?>
                            </label>
                            <textarea
                                class="form-control form-control-modern"
                                id="edit_description"
                                name="<?php echo ($table === 'empresas') ? 'direccion' : 'description'; ?>"
                                rows="3"
                                <?php if ($table === 'ticket_cc_emails'): ?>
                                    placeholder="Ej. Copia a supervisor de soporte"
                                <?php endif; ?>
                            ></textarea>
                        </div>
                        <?php if ($table === 'ticket_cc_emails'): ?>
                        <div class="form-check form-switch config-modal-switch">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">Activo</label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer modal-footer-modern modal-footer-modern--split">
                        <button type="button" class="btn btn-modal-danger" id="deleteButton">
                            <i class="fas fa-trash-alt me-1"></i>Eliminar
                        </button>
                        <div class="modal-footer-actions">
                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-modal-primary">
                                <i class="fas fa-save me-1"></i>Actualizar
                            </button>
                        </div>
                        <input type="hidden" id="is_protected_status" value="0">
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para la eliminación -->
    <form id="deleteForm" method="GET" action="" style="display: none;">
        <input type="hidden" name="table" value="<?php echo $table; ?>">
        <input type="hidden" name="delete" id="delete_id" value="">
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editButtons = document.querySelectorAll('.edit-btn');
            const tableName = '<?php echo $table; ?>';
            const protectedIds = [1, 2, 3, 10];
            
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = parseInt(this.getAttribute('data-id'));
                    const name = this.getAttribute('data-name');
                    const description = this.getAttribute('data-description');
                    const isActiveAttr = this.getAttribute('data-is-active');
                    
                    document.getElementById('edit_id').value = id;
                    document.getElementById('edit_name').value = name;
                    document.getElementById('edit_description').value = description;
                    
                    // Campo de activo solo aplica para tabla de correos en copia
                    const editIsActiveInput = document.getElementById('edit_is_active');
                    if (editIsActiveInput) {
                        editIsActiveInput.checked = (isActiveAttr === '1' || isActiveAttr === 'true');
                    }
                    
                    // También establecemos el ID para el formulario de eliminación
                    document.getElementById('delete_id').value = id;
                    
                    // Verificar si es un registro protegido (solo para statuses)
                    const isProtected = tableName === 'statuses' && protectedIds.includes(id);
                    document.getElementById('is_protected_status').value = isProtected ? '1' : '0';
                    
                    const nameInput = document.getElementById('edit_name');
                    const nameWarning = document.getElementById('name_protected_warning');
                    const deleteButton = document.getElementById('deleteButton');
                    
                    if (isProtected) {
                        // Deshabilitar el campo nombre
                        nameInput.disabled = true;
                        nameInput.classList.add('bg-light');
                        nameWarning.classList.remove('d-none');
                        // Remover el atributo name para que no se envíe
                        nameInput.removeAttribute('name');
                        
                        // Ocultar el botón de eliminar
                        deleteButton.style.display = 'none';
                    } else {
                        // Habilitar el campo nombre
                        nameInput.disabled = false;
                        nameInput.classList.remove('bg-light');
                        nameWarning.classList.add('d-none');
                        // Asegurar que el atributo name esté presente
                        nameInput.setAttribute('name', 'name');
                        nameInput.setAttribute('required', 'required');
                        
                        // Mostrar el botón de eliminar
                        deleteButton.style.display = 'inline-flex';
                    }
                });
            });
            
            // Manejador para el botón de eliminar en el modal de edición
            document.getElementById('deleteButton').addEventListener('click', function() {
                if (confirm('¿Está seguro de que desea eliminar este registro? Esta acción no se puede deshacer.')) {
                    document.getElementById('deleteForm').submit();
                }
            });
            
            // Asegurar que el campo name tenga el atributo name al cargar la página
            const nameInput = document.getElementById('edit_name');
            if (!nameInput.hasAttribute('name')) {
                nameInput.setAttribute('name', 'name');
            }
        });
    </script>
</body>
</html>
