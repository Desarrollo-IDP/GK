<?php
require_once __DIR__ . '/auth.php';
checkAuth();
if (!(function_exists('canManageUsers') && canManageUsers())) {
    header('Location: ' . appUrl('/direc.php'));
    exit;
}

// Incluir configuración de base de datos
require_once __DIR__ . '/Inventario/crud/includes/db.php';

// Verificar y crear tabla empresas_usuarios si no existe
try {
    if ($conn instanceof mysqli) {
        $table_check = $conn->query("SHOW TABLES LIKE 'empresas_usuarios'");
        if (!$table_check || $table_check->num_rows === 0) {
            // Crear tabla empresas_usuarios
            $create_table_sql = "CREATE TABLE IF NOT EXISTS `empresas_usuarios` (
                `id_empresa` INT(11) NOT NULL AUTO_INCREMENT,
                `nombre` VARCHAR(255) NOT NULL,
                `descripcion` TEXT NULL DEFAULT NULL,
                `direccion` VARCHAR(500) NULL DEFAULT NULL,
                `telefono` VARCHAR(50) NULL DEFAULT NULL,
                `email` VARCHAR(255) NULL DEFAULT NULL,
                `contacto` VARCHAR(255) NULL DEFAULT NULL,
                `activo` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id_empresa`),
                INDEX `idx_empresas_usuarios_activo` (`activo`),
                INDEX `idx_empresas_usuarios_nombre` (`nombre`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if (!$conn->query($create_table_sql)) {
                error_log("Error creando tabla empresas_usuarios: " . $conn->error);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error verificando tabla empresas_usuarios: " . $e->getMessage());
}

$message = '';
$messageType = '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'add_empresa':
                // Agregar nueva empresa
                $nombre = trim($_POST['nombre'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $direccion = trim($_POST['direccion'] ?? '');
                $telefono = trim($_POST['telefono'] ?? '');
                $email_empresa = trim($_POST['email_empresa'] ?? '');
                $contacto = trim($_POST['contacto'] ?? '');
                $activo = isset($_POST['activo']) ? 1 : 0;
                
                if (empty($nombre)) {
                    $message = "El nombre de la empresa es obligatorio.";
                    $messageType = "error";
                } else {
                    $stmt = $conn->prepare("INSERT INTO empresas_usuarios (nombre, descripcion, direccion, telefono, email, contacto, activo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssssi", $nombre, $descripcion, $direccion, $telefono, $email_empresa, $contacto, $activo);
                    
                    if ($stmt->execute()) {
                        $message = "Empresa agregada exitosamente";
                        $messageType = "success";
                    } else {
                        $message = "Error al agregar empresa: " . $conn->error;
                        $messageType = "error";
                    }
                }
                break;
                
            case 'edit_empresa':
                // Editar empresa
                $id_empresa = intval($_POST['id_empresa'] ?? 0);
                $nombre = trim($_POST['nombre'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $direccion = trim($_POST['direccion'] ?? '');
                $telefono = trim($_POST['telefono'] ?? '');
                $email_empresa = trim($_POST['email_empresa'] ?? '');
                $contacto = trim($_POST['contacto'] ?? '');
                $activo = isset($_POST['activo']) ? 1 : 0;
                
                if (empty($nombre) || $id_empresa <= 0) {
                    $message = "Datos inválidos.";
                    $messageType = "error";
                } else {
                    $stmt = $conn->prepare("UPDATE empresas_usuarios SET nombre = ?, descripcion = ?, direccion = ?, telefono = ?, email = ?, contacto = ?, activo = ? WHERE id_empresa = ?");
                    $stmt->bind_param("ssssssii", $nombre, $descripcion, $direccion, $telefono, $email_empresa, $contacto, $activo, $id_empresa);
                    
                    if ($stmt->execute()) {
                        $message = "Empresa actualizada exitosamente";
                        $messageType = "success";
                    } else {
                        $message = "Error al actualizar empresa: " . $conn->error;
                        $messageType = "error";
                    }
                }
                break;
                
            case 'delete_empresa':
                // Eliminar empresa
                $id_empresa = intval($_POST['id_empresa'] ?? 0);
                
                if ($id_empresa <= 0) {
                    $message = "ID de empresa inválido.";
                    $messageType = "error";
                } else {
                    // Verificar si hay usuarios asociados
                    $check_stmt = $conn->prepare("SELECT COUNT(*) as total FROM usuarios WHERE id_empresa = ?");
                    $check_stmt->bind_param("i", $id_empresa);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result()->fetch_assoc();
                    
                    if ($check_result['total'] > 0) {
                        $message = "No se puede eliminar la empresa porque tiene usuarios asociados.";
                        $messageType = "error";
                    } else {
                        $stmt = $conn->prepare("DELETE FROM empresas_usuarios WHERE id_empresa = ?");
                        $stmt->bind_param("i", $id_empresa);
                        
                        if ($stmt->execute()) {
                            $message = "Empresa eliminada exitosamente";
                            $messageType = "success";
                        } else {
                            $message = "Error al eliminar empresa: " . $conn->error;
                            $messageType = "error";
                        }
                    }
                }
                break;
        }
    }
}

// Obtener usuario para editar
$editEmpresa = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM empresas_usuarios WHERE id_empresa = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result_edit = $stmt->get_result();
    $editEmpresa = $result_edit->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Empresas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1E2A3B;
            --secondary-color: #2C3E50;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            overflow-x: hidden;
        }
        
        .custom-bg {
            background: linear-gradient(-45deg, var(--primary-color), var(--secondary-color));
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
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
        }
        
        /* Responsive para desktop grande */
        @media (min-width: 1441px) {
            .container {
                max-width: 1400px;
            }
            
            .modal-content {
                max-width: 600px;
                padding: 2.5rem;
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
            
            .modal-content {
                margin: 10% auto;
                padding: 1.5rem;
                width: 95%;
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
            
            .modal-content {
                padding: 1rem;
                width: 98%;
            }
            
            table {
                font-size: 0.75rem;
            }
            
            th, td {
                padding: 0.25rem !important;
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
                <i class="fas fa-building mr-2"></i>
                Gestión de Empresas
            </h1>
            <a href="<?php echo htmlspecialchars(appUrl('/gestion-usuarios.php'), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white text-gray-800 px-4 py-2 rounded hover:bg-gray-100 transition">
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

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Lista de Empresas</h2>
                <div class="flex space-x-2">
                    <?php if (isCurrentUserAdmin()): ?>
                    <a href="<?php echo htmlspecialchars(appUrl('/tickets/modules/configuration/empresa_email_config.php'), ENT_QUOTES, 'UTF-8'); ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-envelope mr-2"></i>
                        Configuración de Email
                    </a>
                    <?php endif; ?>
                    <button onclick="openAddEmpresaModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>
                        Agregar Empresa
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Contacto</th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Teléfono</th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        $empresas_list_query = "SELECT * FROM empresas_usuarios ORDER BY nombre";
                        $empresas_list_result = $conn->query($empresas_list_query);
                        if ($empresas_list_result && $empresas_list_result->num_rows > 0):
                            while ($empresa_item = $empresas_list_result->fetch_assoc()):
                        ?>
                        <tr>
                            <td class="py-4 px-6"><?php echo htmlspecialchars($empresa_item['id_empresa']); ?></td>
                            <td class="py-4 px-6"><?php echo htmlspecialchars($empresa_item['nombre']); ?></td>
                            <td class="py-4 px-6"><?php echo htmlspecialchars($empresa_item['contacto'] ?? '-'); ?></td>
                            <td class="py-4 px-6"><?php echo htmlspecialchars($empresa_item['email'] ?? '-'); ?></td>
                            <td class="py-4 px-6"><?php echo htmlspecialchars($empresa_item['telefono'] ?? '-'); ?></td>
                            <td class="py-4 px-6">
                                <?php if ($empresa_item['activo']): ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Activa</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Inactiva</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6">
                                <button onclick="openEditEmpresaModal(<?php echo htmlspecialchars(json_encode($empresa_item)); ?>)" 
                                        class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button onclick="eliminarEmpresa(<?php echo $empresa_item['id_empresa']; ?>, '<?php echo htmlspecialchars($empresa_item['nombre']); ?>')" 
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </td>
                        </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="7" class="py-4 px-6 text-center text-gray-500">No hay empresas registradas</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para agregar empresa -->
    <div id="addEmpresaModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Agregar Empresa</h3>
                <button onclick="closeModal('addEmpresaModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_empresa">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nombre *</label>
                    <input type="text" name="nombre" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <textarea name="descripcion" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dirección</label>
                    <input type="text" name="direccion" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Teléfono</label>
                    <input type="text" name="telefono" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email_empresa" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contacto</label>
                    <input type="text" name="contacto" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="activo" checked class="mr-2">
                        <span class="text-sm">Activa</span>
                    </label>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('addEmpresaModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para editar empresa -->
    <div id="editEmpresaModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Editar Empresa</h3>
                <button onclick="closeModal('editEmpresaModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" id="editEmpresaForm">
                <input type="hidden" name="action" value="edit_empresa">
                <input type="hidden" name="id_empresa" id="edit_empresa_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nombre *</label>
                    <input type="text" name="nombre" id="edit_empresa_nombre" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <textarea name="descripcion" id="edit_empresa_descripcion" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dirección</label>
                    <input type="text" name="direccion" id="edit_empresa_direccion" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Teléfono</label>
                    <input type="text" name="telefono" id="edit_empresa_telefono" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email_empresa" id="edit_empresa_email" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contacto</label>
                    <input type="text" name="contacto" id="edit_empresa_contacto" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="activo" id="edit_empresa_activo" class="mr-2">
                        <span class="text-sm">Activa</span>
                    </label>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editEmpresaModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddEmpresaModal() {
            document.getElementById('addEmpresaModal').style.display = 'block';
        }

        function openEditEmpresaModal(empresa) {
            document.getElementById('edit_empresa_id').value = empresa.id_empresa;
            document.getElementById('edit_empresa_nombre').value = empresa.nombre || '';
            document.getElementById('edit_empresa_descripcion').value = empresa.descripcion || '';
            document.getElementById('edit_empresa_direccion').value = empresa.direccion || '';
            document.getElementById('edit_empresa_telefono').value = empresa.telefono || '';
            document.getElementById('edit_empresa_email').value = empresa.email || '';
            document.getElementById('edit_empresa_contacto').value = empresa.contacto || '';
            document.getElementById('edit_empresa_activo').checked = empresa.activo == 1;
            document.getElementById('editEmpresaModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

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

        function eliminarEmpresa(id, nombre) {
            if (confirm(`¿Estás seguro de que deseas eliminar la empresa "${nombre}"?\n\nNota: No se puede eliminar si tiene usuarios asociados.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_empresa">
                    <input type="hidden" name="id_empresa" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Auto-ocultar mensaje de éxito después de 4 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.getElementById('messageAlert');
            if (successMessage) {
                setTimeout(function() {
                    closeMessage();
                }, 4000);
            }
        });
    </script>
</body>
</html>
