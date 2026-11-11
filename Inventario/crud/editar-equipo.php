<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
require_once '../../log_activity.php'; 
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
$id = $_GET['id'] ?? 0;

$return_url = 'visualizar-equipos.php';

if ($id > 0) {
    // Verificar si existe columna id_empresa_usuario
    $check_column = $conn->query("SHOW COLUMNS FROM equipos LIKE 'id_empresa_usuario'");
    $has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;
    
    if ($has_empresa_usuario_column && $user_empresa_id !== null) {
        // Verificar que el equipo pertenezca a la empresa del usuario
        $stmt = $conn->prepare("SELECT * FROM equipos WHERE id_equipo = ? AND id_empresa_usuario = ?");
        $stmt->bind_param("ii", $id, $user_empresa_id);
    } else {
    $stmt = $conn->prepare("SELECT * FROM equipos WHERE id_equipo = ?");
    $stmt->bind_param("i", $id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $equipo = $result->fetch_assoc();
    
    if (!$equipo) {
        header('Location: ' . $return_url);
        exit;
    }
    
    // Verificar permisos adicionales si el usuario tiene empresa asignada
    if ($user_empresa_id !== null && $has_empresa_usuario_column) {
        if ($equipo['id_empresa_usuario'] != $user_empresa_id) {
            header('Location: ' . $return_url . '?error=no_permission');
            exit;
        }
    }
}

$pageTitle = 'Editar Equipo';
require_once 'includes/header.php';

// Filtrar empresas por empresa del usuario (tabla empresas_equipos)
$check_column_empresas = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column_empresas = $check_column_empresas && $check_column_empresas->num_rows > 0;

if ($has_empresa_usuario_column_empresas && $user_empresa_id !== null) {
    $empresas_query = "SELECT id_empresa, nombre FROM empresas_equipos WHERE id_empresa_usuario = ? ORDER BY nombre";
    $empresas_stmt = $conn->prepare($empresas_query);
    $empresas_stmt->bind_param("i", $user_empresa_id);
    $empresas_stmt->execute();
    $empresas_result = $empresas_stmt->get_result();
} else {
    $empresas_query = "SELECT id_empresa, nombre FROM empresas_equipos ORDER BY nombre";
    $empresas_result = $conn->query($empresas_query);
}

$documentos = [];
if (!empty($equipo['documentos'])) {
    $documentos = json_decode($equipo['documentos'], true) ?: [];
}

$filter_params = [];
if (isset($_GET['empresa'])) {
    $filter_params['empresa'] = $_GET['empresa'];
}
if (isset($_GET['responsable'])) {
    $filter_params['responsable'] = $_GET['responsable'];
}

// Opciones de Ubicación y Activo para la empresa del equipo
$opciones_ubicacion = [];
$opciones_activo = [];
$empresa_config_id = isset($equipo['id_empresa_usuario']) ? $equipo['id_empresa_usuario'] : $user_empresa_id;
$tabla_ubicaciones = $conn->query("SHOW TABLES LIKE 'inventario_config_ubicaciones'");
$tabla_activos = $conn->query("SHOW TABLES LIKE 'inventario_config_activos'");
if ($tabla_ubicaciones && $tabla_ubicaciones->num_rows > 0 && $tabla_activos && $tabla_activos->num_rows > 0 && $empresa_config_id !== null) {
    $st = $conn->prepare("SELECT valor FROM inventario_config_ubicaciones WHERE id_empresa_usuario = ? ORDER BY valor");
    $st->bind_param("i", $empresa_config_id);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $opciones_ubicacion[] = $r['valor'];
    }
    $res->free();
    $st->close();
    $st = $conn->prepare("SELECT valor FROM inventario_config_activos WHERE id_empresa_usuario = ? ORDER BY valor");
    $st->bind_param("i", $empresa_config_id);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $opciones_activo[] = $r['valor'];
    }
    $res->free();
    $st->close();
}
$valor_ubicacion = isset($equipo['ubicacion']) ? $equipo['ubicacion'] : '';
$valor_activo = isset($equipo['activo_equipo']) ? $equipo['activo_equipo'] : '';
if ($valor_ubicacion !== '' && !in_array($valor_ubicacion, $opciones_ubicacion, true)) {
    $opciones_ubicacion = array_merge([$valor_ubicacion], $opciones_ubicacion);
}
if ($valor_activo !== '' && !in_array($valor_activo, $opciones_activo, true)) {
    $opciones_activo = array_merge([$valor_activo], $opciones_activo);
}
?>

<div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
    <div class="inicio-header mb-8">
        <div class="flex items-center gap-3 mb-1">
            <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg">
                <i class="fas fa-pen-to-square text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Editar equipo</h1>
                <p class="inicio-header-subtitle">Modifica los datos del equipo en el inventario.</p>
            </div>
        </div>
        
    </div>

    <form id="formEquipo" class="inv-form-card bg-white rounded-2xl shadow-xl border border-gray-100 p-6 lg:p-8" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $equipo['id_equipo']; ?>">
        <?php foreach ($filter_params as $key => $value): ?>
            <input type="hidden" name="filter_<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>">
        <?php endforeach; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="empresa"><i class="fas fa-building w-4 mr-1 text-gray-400"></i> Empresa</label>
                <select id="empresa" name="empresa" disabled class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-100 text-gray-600 cursor-not-allowed">
                    <?php while ($empresa = $empresas_result->fetch_assoc()): ?>
                        <option value="<?php echo $empresa['id_empresa']; ?>" <?php echo ($empresa['id_empresa'] == $equipo['id_empresa']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($empresa['nombre']); ?></option>
                    <?php endwhile; ?>
                </select>
                <input type="hidden" name="empresa" value="<?php echo $equipo['id_empresa']; ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="equipo"><i class="fas fa-desktop w-4 mr-1 text-gray-400"></i> Equipo</label>
                <input id="equipo" name="nombre_equipo" type="text" value="<?php echo htmlspecialchars($equipo['nombre_equipo']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="responsable"><i class="fas fa-user w-4 mr-1 text-gray-400"></i> Responsable</label>
                <input id="responsable" name="responsable" type="text" value="<?php echo htmlspecialchars($equipo['responsable']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="sistema_operativo"><i class="fas fa-laptop w-4 mr-1 text-gray-400"></i> Sistema operativo</label>
                <input id="sistema_operativo" name="sistema_operativo" type="text" value="<?php echo htmlspecialchars($equipo['sistema_operativo']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="mac"><i class="fas fa-network-wired w-4 mr-1 text-gray-400"></i> MAC</label>
                <input id="mac" name="direccion_mac" type="text" value="<?php echo htmlspecialchars($equipo['direccion_mac']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none font-mono text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="tipo"><i class="fas fa-tag w-4 mr-1 text-gray-400"></i> Tipo</label>
                <input id="tipo" name="tipo" type="text" value="<?php echo htmlspecialchars($equipo['tipo']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="fabricante"><i class="fas fa-industry w-4 mr-1 text-gray-400"></i> Fabricante</label>
                <input id="fabricante" name="fabricante" type="text" value="<?php echo htmlspecialchars($equipo['fabricante']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="modelo"><i class="fas fa-cube w-4 mr-1 text-gray-400"></i> Modelo</label>
                <input id="modelo" name="modelo" type="text" value="<?php echo htmlspecialchars($equipo['modelo']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="no_serie"><i class="fas fa-barcode w-4 mr-1 text-gray-400"></i> Nº serie</label>
                <input id="no_serie" name="numero_serie" type="text" value="<?php echo htmlspecialchars($equipo['numero_serie']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none font-mono text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="ram"><i class="fas fa-memory w-4 mr-1 text-gray-400"></i> RAM</label>
                <input id="ram" name="ram" type="text" value="<?php echo htmlspecialchars($equipo['ram']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="disco_duro"><i class="fas fa-hdd w-4 mr-1 text-gray-400"></i> Disco duro</label>
                <input id="disco_duro" name="disco_duro" type="text" value="<?php echo htmlspecialchars($equipo['disco_duro']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="procesador"><i class="fas fa-microchip w-4 mr-1 text-gray-400"></i> Procesador</label>
                <input id="procesador" name="procesador" type="text" value="<?php echo htmlspecialchars($equipo['procesador']); ?>" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="ubicacion"><i class="fas fa-map-marker-alt w-4 mr-1 text-gray-400"></i> Ubicación</label>
                <select id="ubicacion" name="ubicacion" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                    <option value="">Seleccione ubicación</option>
                    <?php foreach ($opciones_ubicacion as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($valor_ubicacion === $opt) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5" for="activo_equipo"><i class="fas fa-check-circle w-4 mr-1 text-gray-400"></i> Activo del equipo</label>
                <select id="activo_equipo" name="activo_equipo" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                    <option value="">Seleccione activo</option>
                    <?php foreach ($opciones_activo as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($valor_activo === $opt) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (!empty($documentos)): ?>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-600 mb-2"><i class="fas fa-file-alt w-4 mr-1 text-gray-400"></i> Documentos actuales</label>
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-2">
                <?php foreach ($documentos as $index => $documento): ?>
                    <div class="flex items-center justify-between py-2 px-3 bg-white border border-gray-100 rounded-lg">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" name="eliminar_documentos[]" value="<?php echo $index; ?>" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <a href="ver-documento.php?id=<?php echo $equipo['id_equipo']; ?>&doc=<?php echo $index; ?>" target="_blank" rel="noopener" class="inv-doc-link text-sm font-medium inv-brand-text hover:underline"><?php echo htmlspecialchars($documento['nombre']); ?></a>
                        </div>
                        <span class="text-xs text-gray-500 font-mono"><?php echo number_format(strlen(base64_decode($documento['contenido'])) / 1024, 2); ?> KB</span>
                    </div>
                <?php endforeach; ?>
                <p class="text-sm text-gray-500 mt-2">Marque las casillas de los documentos que desea eliminar.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-600 mb-2" for="documentos"><i class="fas fa-upload w-4 mr-1 text-gray-400"></i> Agregar documentos</label>
            <input type="file" id="documentos" name="documentos[]" multiple class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:font-medium file:inv-brand-bg file:text-white file:cursor-pointer">
            <p class="mt-1 text-sm text-gray-500">Puede seleccionar múltiples archivos.</p>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-600 mb-1.5" for="observaciones"><i class="fas fa-comment-alt w-4 mr-1 text-gray-400"></i> Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="4" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 transition-all outline-none resize-y"><?php echo htmlspecialchars($equipo['observaciones']); ?></textarea>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100">
            <button type="submit" class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all">
                <i class="fas fa-save"></i> Guardar cambios
            </button>
            <a href="<?php echo $return_url; ?>" class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<style>
.inv-page-wrap { max-width: 1200px; }
.inv-brand-bg { background-color: #1E2A3B; }
.inv-brand-text { color: #1E2A3B; }
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
.inv-form-card .inv-input { min-height: 42px; }
.inv-form-card .inv-input:hover { border-color: #cbd5e1; }
.inv-doc-link { text-decoration: none; }
.inv-btn-primary:hover { filter: brightness(1.08); }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const formEquipo = document.getElementById('formEquipo');

    formEquipo.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(formEquipo);
        
        try {
            const response = await fetch('actualizar-equipo.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Error en la respuesta del servidor:', errorText);
                throw new Error(`El servidor respondió con estado: ${response.status}`);
            }
            
            const result = await response.json();
            if (result.success) {
                window.location.href = result.redirect || '<?php echo $return_url; ?>';
            } else {
                throw new Error(result.error || 'Error desconocido');
            }
            
        } catch (error) {
            console.error('Error:', error);
            showAlert('Error al actualizar el equipo: ' + error.message, 'error');
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>