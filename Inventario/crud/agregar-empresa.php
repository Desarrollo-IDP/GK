<?php
ob_start(); 
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
require_once '../../log_activity.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar si existe columna id_empresa_usuario
    $check_column = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
    $has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;
    
    // Verificar nombre único dentro de la empresa del usuario
    $check_query = "SELECT id_empresa FROM empresas_equipos WHERE nombre = ?";
    $check_params = [$_POST['nombre']];
    $check_types = 's';
    
    if ($has_empresa_usuario_column && $user_empresa_id !== null) {
        $check_query .= " AND id_empresa_usuario = ?";
        $check_params[] = $user_empresa_id;
        $check_types .= 'i';
    }
    
    $check_stmt = $conn->prepare($check_query);
    if (!$check_stmt) {
        die("Error en la preparación de la consulta: " . $conn->error);
    }
    
    $check_stmt->bind_param($check_types, ...$check_params);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $error = "La empresa '" . htmlspecialchars($_POST['nombre']) . "' ya existe" . ($has_empresa_usuario_column && $user_empresa_id !== null ? " en esta empresa" : "") . ".";
    } else {
        // Insertar con id_empresa_usuario si existe el campo
        if ($has_empresa_usuario_column && $user_empresa_id !== null) {
            $stmt = $conn->prepare("INSERT INTO empresas_equipos (nombre, direccion, id_empresa_usuario) VALUES (?, ?, ?)");
            if (!$stmt) {
                die("Error en la preparación de la consulta: " . $conn->error);
            }
            $stmt->bind_param("ssi", $_POST['nombre'], $_POST['direccion'], $user_empresa_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO empresas_equipos (nombre, direccion) VALUES (?, ?)");
        if (!$stmt) {
            die("Error en la preparación de la consulta: " . $conn->error);
        }
        $stmt->bind_param("ss", $_POST['nombre'], $_POST['direccion']);
        }
        
        if ($stmt->execute()) {
            $id_empresa = $conn->insert_id;
            
            if (!empty($_POST['contacto']) || !empty($_POST['telefono']) || !empty($_POST['correo'])) {
                if (!upsertContactoEmpresa(
                    $conn,
                    $id_empresa,
                    $_POST['contacto'] ?? '',
                    $_POST['telefono'] ?? '',
                    $_POST['correo'] ?? '',
                    CONTACTO_ORIGEN_EMPRESAS_EQUIPOS
                )) {
                    $error = "Error al guardar los contactos";
                }
            }
            
            logActivity(
                "Creación de empresa (equipos)",
                "empresas_equipos",
                $id_empresa,
                "Se creó una nueva empresa: " . $_POST['nombre']
            );
            
            header('Location: visualizar-empresas.php');
            exit;
        } else {
            $error = "Error al ejecutar la consulta: " . $stmt->error;
        }
    }
}

$pageTitle = 'Agregar Empresa';
require_once 'includes/header.php';
?>
<div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
    <div class="inicio-header mb-8">
        <div class="flex items-center gap-3 mb-1">
            <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg">
                <i class="fas fa-building text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Agregar empresa</h1>
                <p class="inicio-header-subtitle">Registra una nueva empresa para asociar equipos y contactos.</p>
            </div>
        </div>
        
    </div>
    
    <?php if (isset($error)): ?>
        <div class="inv-alert-error mb-6 flex items-start gap-3 px-4 py-3 rounded-xl border border-red-200 bg-red-50 text-red-800">
            <i class="fas fa-exclamation-circle mt-0.5 text-red-500"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>
        
    <form method="POST" class="inv-form-card bg-white rounded-2xl shadow-xl border border-gray-100 p-6 lg:p-8">
    
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">
                    <i class="fas fa-building w-4 mr-1 text-gray-400"></i> Nombre de la empresa
                </label>
                <input type="text" name="nombre" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none" required>
            </div>
        
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">
                    <i class="fas fa-map-marker-alt w-4 mr-1 text-gray-400"></i> Dirección
                </label>
                <input type="text" name="direccion" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none" required>
            </div>
        
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">
                    <i class="fas fa-user w-4 mr-1 text-gray-400"></i> Nombre del contacto
                </label>
                <input type="text" name="contacto" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none" required>
            </div>
        
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">
                    <i class="fas fa-phone w-4 mr-1 text-gray-400"></i> Teléfono
                </label>
                <input type="text" name="telefono" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none" required>
            </div>
        
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">
                    <i class="fas fa-envelope w-4 mr-1 text-gray-400"></i> Correo electrónico
                </label>
                <input type="email" name="correo" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none" required>
            </div>
        </div>
    
        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100">
            <button type="submit" class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="visualizar-empresas.php" class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </div>
    </form>
</div>
<style>
.inv-page-wrap { max-width: 900px; }
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
<?php require_once 'includes/footer.php'; ?>