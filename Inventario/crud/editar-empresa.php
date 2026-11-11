<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
require_once '../../log_activity.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
$id = $_GET['id'] ?? 0;

// Verificar si existe columna id_empresa_usuario
$check_column = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

if ($id > 0) {
    // Verificar que la empresa pertenezca a la empresa del usuario
    if ($has_empresa_usuario_column && $user_empresa_id !== null) {
        $stmt = $conn->prepare("SELECT * FROM empresas_equipos WHERE id_empresa = ? AND id_empresa_usuario = ?");
        $stmt->bind_param("ii", $id, $user_empresa_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM empresas_equipos WHERE id_empresa = ?");
        $stmt->bind_param("i", $id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $empresa_original = $result->fetch_assoc();
    
    if (!$empresa_original) {
        header('Location: visualizar-empresas.php?error=no_permission');
        exit;
    }
    
    // Verificación adicional de permisos
    if ($user_empresa_id !== null && $has_empresa_usuario_column) {
        if ($empresa_original['id_empresa_usuario'] != $user_empresa_id) {
            header('Location: visualizar-empresas.php?error=no_permission');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar permisos antes de actualizar
    if ($has_empresa_usuario_column && $user_empresa_id !== null) {
        $verify_stmt = $conn->prepare("SELECT id_empresa FROM empresas_equipos WHERE id_empresa = ? AND id_empresa_usuario = ?");
        $verify_stmt->bind_param("ii", $id, $user_empresa_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            header('Location: visualizar-empresas.php?error=no_permission');
            exit;
        }
        $verify_stmt->close();
    }
    
    $stmt = $conn->prepare("UPDATE empresas_equipos SET nombre = ?, direccion = ? WHERE id_empresa = ?");
    $stmt->bind_param("ssi", $_POST['nombre'], $_POST['direccion'], $id);
    
    if ($stmt->execute()) {
        $cambios = [];
        if ($empresa_original['nombre'] != $_POST['nombre']) {
            $cambios[] = "Nombre: " . $empresa_original['nombre'] . " → " . $_POST['nombre'];
        }
        
        $contacto_original = getContactoEmpresa($conn, $id, CONTACTO_ORIGEN_EMPRESAS_EQUIPOS);

        if ($contacto_original || !empty($_POST['contacto']) || !empty($_POST['telefono']) || !empty($_POST['correo'])) {
            upsertContactoEmpresa(
                $conn,
                $id,
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
            $id,
            "Se actualizó la empresa: " . $_POST['nombre'] . "\n" . implode("\n", $cambios)
        );
        
        header('Location: visualizar-empresas.php');
        exit;
    }
}

$pageTitle = 'Editar Empresa';
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    header('Location: visualizar-empresas.php');
    exit;
}


$stmt = $conn->prepare("
    SELECT e.*, c.id_contacto, c.contacto, c.telefono, c.correo
    FROM empresas_equipos e
    " . contactosEmpresaLeftJoinSql($conn, CONTACTO_ORIGEN_EMPRESAS_EQUIPOS) . "
    WHERE e.id_empresa = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$empresa = $result->fetch_assoc();

if (!$empresa) {
    header('Location: visualizar-empresas.php');
    exit;
}
?>

<div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
    <div class="inicio-header mb-8">
        <div class="flex items-center gap-3 mb-1">
            <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg">
                <i class="fas fa-building text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Editar empresa</h1>
                <p class="inicio-header-subtitle">Actualiza los datos de la empresa y su contacto principal.</p>
            </div>
        </div>
        
    </div>

    <form id="editarEmpresaForm" method="POST" class="inv-form-card bg-white rounded-2xl shadow-xl border border-gray-100 p-6 lg:p-8">
        <input type="hidden" name="id_empresa" value="<?php echo $empresa['id_empresa']; ?>">
        <input type="hidden" name="id_contacto" value="<?php echo $empresa['id_contacto'] ?? ''; ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="nombre">
                    <i class="fas fa-building w-4 mr-1 text-gray-400"></i> Nombre de la empresa
                </label>
                <input type="text" id="nombre" name="nombre" required readonly
                       value="<?php echo htmlspecialchars($empresa['nombre']); ?>"
                       class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-100 text-gray-600 cursor-not-allowed">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="direccion">
                    <i class="fas fa-map-marker-alt w-4 mr-1 text-gray-400"></i> Dirección
                </label>
                <input type="text" id="direccion" name="direccion" required
                       value="<?php echo htmlspecialchars($empresa['direccion']); ?>"
                       class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="contacto">
                    <i class="fas fa-user w-4 mr-1 text-gray-400"></i> Nombre del contacto
                </label>
                <input type="text" id="contacto" name="contacto"
                       value="<?php echo htmlspecialchars($empresa['contacto'] ?? ''); ?>"
                       class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
        
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="telefono">
                    <i class="fas fa-phone w-4 mr-1 text-gray-400"></i> Teléfono
                </label>
                <input type="text" id="telefono" name="telefono"
                       value="<?php echo htmlspecialchars($empresa['telefono'] ?? ''); ?>"
                       class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
        
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="correo">
                    <i class="fas fa-envelope w-4 mr-1 text-gray-400"></i> Correo electrónico
                </label>
                <input type="email" id="correo" name="correo"
                       value="<?php echo htmlspecialchars($empresa['correo'] ?? ''); ?>"
                       class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
        </div>
            
        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100">
            <button type="submit" class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all">
                <i class="fas fa-save"></i> Guardar cambios
            </button>
            <a href="visualizar-empresas.php" class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all">
                <i class="fas fa-times"></i> Cancelar
            </a> 
        </div>
    </form>
</div>

<script>
document.getElementById('editarEmpresaForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('api/update-empresa.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'visualizar-empresas.php';
        } else {
            showAlert(data.error || 'Error al actualizar la empresa', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error al actualizar la empresa', 'error');
    });
});
</script>

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