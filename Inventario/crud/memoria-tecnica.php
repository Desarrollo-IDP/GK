<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
require_once '../../log_activity.php';
checkAuth();

$pageTitle = 'Memoria Técnica';
require_once 'includes/header.php';

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$user_empresa_id = getCurrentUserEmpresaId();
$isAdmin = isAdmin();

// Obtener lista de empresas
$query_empresas = "SELECT id_empresa, nombre FROM empresas_equipos";
$params_e = [];
$types_e = '';

// Verificar si existe columna id_empresa_usuario
$check_column = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
if ($check_column && $check_column->num_rows > 0 && $user_empresa_id !== null) {
    $query_empresas .= " WHERE id_empresa_usuario = ?";
    $params_e[] = $user_empresa_id;
    $types_e = 'i';
}
$query_empresas .= " ORDER BY nombre";

if (!empty($params_e)) {
    $stmt_e = $conn->prepare($query_empresas);
    $stmt_e->bind_param($types_e, ...$params_e);
    $stmt_e->execute();
    $res_empresas = $stmt_e->get_result();
} else {
    $res_empresas = $conn->query($query_empresas);
}

$empresas = [];
while ($row = $res_empresas->fetch_assoc()) {
    $empresas[] = $row;
}
?>

<div class="memoria-container">
    <!-- Sidebar de Empresas -->
    <div class="memoria-sidebar">
        <div class="memoria-sidebar-header">
            <h2 class="text-lg font-bold text-gray-800">Empresas</h2>
            <div class="memoria-search-wrap">
                <i class="fas fa-search text-gray-400"></i>
                <input type="text" id="searchEmpresa" placeholder="Buscar empresa..." onkeyup="filterEmpresas()">
            </div>
        </div>
        <div class="memoria-list" id="empresaList">
            <?php foreach ($empresas as $empresa): ?>
                <div class="memoria-item"
                    onclick="selectEmpresa(<?php echo $empresa['id_empresa']; ?>, '<?php echo addslashes($empresa['nombre']); ?>', this)">
                    <i class="fas fa-building text-gray-400 mr-2"></i>
                    <span class="empresa-nombre"><?php echo htmlspecialchars($empresa['nombre']); ?></span>
                    </i>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div class="memoria-main" id="memoriaMain">
        <!-- Estado vacío -->
        <div id="emptyState" class="memoria-empty-state">
            <div class="empty-icon-wrap">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <h3>Seleccione una empresa</h3>
            <p>Elija una empresa de la lista para visualizar su diagrama de red y memoria técnica.</p>
        </div>

        <!-- Contenido de la Empresa -->
        <div id="companyContent" style="display:none;" class="memoria-content-grid">
            <!-- Header de la empresa seleccionada -->
            <div class="memoria-content-header">
                <div class="flex items-center gap-3">
                    <div class="selected-company-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <h2 id="selectedCompanyName" class="text-xl font-bold text-gray-800">Nombre de la Empresa</h2>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Memoria Técnica y
                            Diagrama</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="exportarMemoriaPDF('sede')" class="btn-pdf-action btn-pdf-sede" title="Exportar reporte PDF de la sede seleccionada">
                        <i class="fas fa-file-pdf text-red-500"></i>
                        <span>Exportar Sede (PDF)</span>
                    </button>
                    <button onclick="exportarMemoriaPDF('todas')" class="btn-pdf-action btn-pdf-empresa" title="Exportar reporte PDF completo de todas las sedes de la empresa">
                        <i class="fas fa-file-pdf text-red-400"></i>
                        <span>Reporte General (PDF)</span>
                    </button>
                </div>
            </div>

            <!-- Selector de Sedes -->
            <div class="memoria-sedes-container">
                <div id="sedesList" class="memoria-sedes-list">
                    <!-- Se cargará vía JS -->
                </div>
                <button onclick="nuevaSede()" class="btn-new-sede" title="Agregar nueva sede">
                    <i class="fas fa-plus"></i>
                </button>
            </div>

            <div class="memoria-grid-body">
                <!-- Columna Diagrama -->
                <div class="memoria-col-diagrama">
                    <div class="memoria-section-title">
                        <span><i class="fas fa-network-wired text-indigo-600 mr-2"></i> Diagrama de Red</span>
                        <button onclick="abrirModalUpload()" class="btn-add-circle" title="Actualizar Diagrama">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div id="diagramContainer" class="diagram-viewer-container">
                        <div id="diagramPlaceholder" class="diagram-placeholder">
                            <i class="fas fa-project-diagram text-gray-200 text-6xl mb-4"></i>
                            <p>Sin diagrama cargado</p>
                        </div>
                        <div id="diagramImageWrap" style="display:none;" class="diagram-img-wrap">
                            <div class="diagram-tools">
                                <button onclick="zoomIn()" title="Acercar"><i class="fas fa-search-plus"></i></button>
                                <button onclick="zoomOut()" title="Alejar"><i class="fas fa-search-minus"></i></button>
                                <button onclick="resetZoom()" title="Reset"><i
                                        class="fas fa-compress-arrows-alt"></i></button>
                                <button onclick="abrirFullscreen()" title="Pantalla Completa"><i
                                        class="fas fa-expand"></i></button>
                                <?php
                                $user_rol = strtolower($_SESSION['user']['rol'] ?? '');
                                if ($user_rol === 'supervisor' || $user_rol === 'administrador' || isAdmin()):
                                    ?>
                                    <button onclick="eliminarDiagrama()"
                                        class="text-red-500 hover:text-red-700 hover:bg-red-50" title="Eliminar Diagrama">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div id="diagramViewport" class="diagram-viewport">
                                <img id="diagramImg" src="" alt="Diagrama de red" draggable="false">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Memoria Técnica e Historial -->
                <div class="memoria-col-right">
                    <!-- Sección: Memoria Técnica (Notas) -->
                    <div class="memoria-section-box memoria-box-notas">
                        <div class="memoria-section-title">
                            <span><i class="fas fa-file-alt text-indigo-600 mr-2"></i> Memoria Técnica</span>
                            <button onclick="abrirModalNota()" class="btn-add-circle" title="Agregar Nota">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div id="notesList" class="notes-list">
                            <!-- Se cargará vía JS -->
                        </div>
                    </div>

                    <?php
                    $user_rol = strtolower($_SESSION['user']['rol'] ?? '');
                    if ($user_rol === 'supervisor'):
                        ?>
                        <!-- Sección: Historial (Movimientos) -->
                        <div class="memoria-section-box memoria-box-historial">
                            <div class="memoria-section-title">
                                <i class="fas fa-history text-indigo-600 mr-2"></i> Historial de Movimientos
                            </div>
                            <div id="historyList" class="history-list-compact">
                                <!-- Se cargará vía JS -->
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Agregar Nota -->
<div id="modalNota" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle">Agregar Nota a Memoria Técnica</h3>
            <button onclick="cerrarModalNota()"><i class="fas fa-times"></i></button>
        </div>
        <form id="formNota" onsubmit="guardarNota(event)">
            <div class="modal-body">
                <input type="hidden" id="notaIdEmpresa">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                    <input type="text" id="notaTitulo" class="modal-input"
                        placeholder="Ej: Configuración de Rack, Acceso VPN..." required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción / Información</label>
                    <textarea id="notaDescripcion" class="modal-textarea" rows="6"
                        placeholder="Detalle la información técnica relevante..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="cerrarModalNota()" class="btn-cancel">Cancelar</button>
                <button type="submit" class="btn-save">Guardar Nota</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para Subir Diagrama (Reutilizando lógica) -->
<div id="modalUpload" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Actualizar Diagrama de Red</h3>
            <button onclick="cerrarModalUpload()"><i class="fas fa-times"></i></button>
        </div>
        <form id="formUpload" onsubmit="subirDiagrama(event)">
            <div class="modal-body">
                <input type="hidden" id="uploadIdEmpresa">
                <div class="diagrama-dropzone" id="dropzone" onclick="document.getElementById('fileInput').click()"
                    ondragover="event.preventDefault(); this.classList.add('active')"
                    ondragleave="this.classList.remove('active')" ondrop="handleDrop(event)">
                    <div id="dropzoneContent">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-300 mb-2"></i>
                        <p class="text-sm text-gray-500">Arrastra una imagen o haz clic para seleccionar</p>
                        <p class="text-xs text-gray-400 mt-1">PNG, JPG, SVG (Máx 10MB)</p>
                    </div>
                    <div id="dropzonePreview" style="display:none;">
                        <img id="previewImg" src="" class="max-h-40 mx-auto rounded shadow-sm">
                        <p id="fileName" class="text-xs text-gray-500 mt-2 font-medium"></p>
                    </div>
                    <input type="file" id="fileInput" accept="image/*" style="display:none;"
                        onchange="handleFile(this.files[0])">
                </div>
                <div id="uploadProgress" class="progress-wrap" style="display:none;">
                    <div class="progress-bar">
                        <div id="progressFill" class="progress-fill"></div>
                    </div>
                    <span id="progressText" class="text-xs text-gray-500">Subiendo...</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="cerrarModalUpload()" class="btn-cancel">Cancelar</button>
                <button type="submit" id="btnSubmitUpload" class="btn-save" disabled>Subir Diagrama</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Sede (Crear/Editar) -->
<div id="modalSede" class="modal-overlay" style="display:none;">
    <div class="modal-box" style="max-width: 400px;">
        <div class="modal-header">
            <h3 id="modalSedeTitle" class="text-lg font-bold text-gray-800">Sede / Sucursal</h3>
            <button onclick="cerrarModalSede()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="mb-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de la Sede</label>
                <input type="text" id="inputSedeNombre" class="modal-input" placeholder="Ej. Sucursal Centro..."
                    autofocus>
                <input type="hidden" id="inputSedeOldName">
                <p id="modalSedeHelp" class="text-xs text-gray-400 mt-2"></p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="cerrarModalSede()" class="btn-cancel">Cancelar</button>
            <button type="button" onclick="confirmarSede()" class="btn-save">
                <i class="fas fa-check mr-1"></i> <span id="btnSedeText">Confirmar</span>
            </button>
        </div>
    </div>
</div>

<!-- Fullscreen Viewer (Diagrama) -->
<div id="fsViewer" class="fs-overlay" style="display:none;" onclick="cerrarFullscreen()">
    <div class="fs-toolbar" onclick="event.stopPropagation()">
        <span id="fsTitle">Diagrama de Red</span>
        <div class="flex items-center gap-4">
            <span id="fsZoomLabel">100%</span>
            <button onclick="cerrarFullscreen()"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div class="fs-canvas">
        <img id="fsImg" src="" alt="Diagrama" onclick="event.stopPropagation()" draggable="false">
    </div>
</div>

<style>
    /* Contenedor Principal */
    .memoria-container {
        display: flex;
        height: calc(100vh - 70px);
        background: #f8fafc;
        overflow: hidden;
    }

    /* Sidebar */
    .memoria-sidebar {
        width: 320px;
        background: white;
        border-right: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }

    .memoria-sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .memoria-search-wrap {
        position: relative;
        margin-top: 1rem;
    }

    .memoria-search-wrap i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
    }

    .memoria-search-wrap input {
        width: 100%;
        padding: 0.6rem 1rem 0.6rem 2.5rem;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        font-size: 0.875rem;
        outline: none;
        transition: border-color 0.2s;
    }

    .memoria-search-wrap input:focus {
        border-color: #6366f1;
    }

    .memoria-list {
        flex: 1;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .memoria-item {
        padding: 1rem 1.25rem;
        border-radius: 0.75rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        transition: all 0.2s;
        margin-bottom: 0.25rem;
    }

    .memoria-item:hover {
        background: #f1f5f9;
    }

    .memoria-item.active {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
    }

    .memoria-item.active .empresa-nombre {
        color: #1e40af;
        font-weight: 600;
    }

    .memoria-item.active i.fa-building {
        color: #3b82f6;
    }

    /* Main Content */
    .memoria-main {
        flex: 1;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    .memoria-empty-state {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        text-align: center;
        padding: 2rem;
    }

    .empty-icon-wrap {
        width: 100px;
        height: 100px;
        background: #f1f5f9;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        margin-bottom: 1.5rem;
    }

    .memoria-empty-state h3 {
        font-size: 1.5rem;
        color: #475569;
        margin-bottom: 0.5rem;
    }

    .memoria-content-grid {
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .memoria-content-header {
        background: white;
        padding: 1.5rem 2.5rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .selected-company-icon {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        color: white;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .btn-add-note,
    .btn-upload-diag {
        padding: 0.6rem 1.2rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        transition: all 0.2s;
    }

    .btn-add-note {
        background: #2563eb;
        color: white;
    }

    .btn-add-note:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }

    .btn-upload-diag {
        background: white;
        color: #1e293b;
        border: 1px solid #e2e8f0;
    }

    .btn-upload-diag:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    .btn-pdf-action {
        padding: 0.6rem 1.1rem;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        cursor: pointer;
        border: none;
    }

    .btn-pdf-sede {
        background: #ffffff;
        color: #1e293b;
        border: 1px solid #cbd5e1;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .btn-pdf-sede:hover {
        background: #fef2f2;
        border-color: #fca5a5;
        color: #991b1b;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(220, 38, 38, 0.1);
    }

    .btn-pdf-empresa {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #ffffff;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2);
    }

    .btn-pdf-empresa:hover {
        background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.3);
    }

    .memoria-grid-body {
        display: grid;
        grid-template-columns: 1fr 480px;
        gap: 2rem;
        padding: 2rem;
        flex: 1;
        overflow: hidden;
        background: #f1f5f9;
    }

    @media (max-width: 1280px) {
        .memoria-grid-body {
            grid-template-columns: 1fr;
            overflow-y: auto;
        }
    }

    .memoria-col-diagrama {
        display: flex;
        flex-direction: column;
        background: white;
        border-radius: 1.5rem;
        border: 1px solid rgba(226, 232, 240, 0.8);
        overflow: hidden;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    }

    .memoria-col-right {
        display: flex;
        flex-direction: column;
        gap: 2rem;
        height: 100%;
        overflow: hidden;
    }

    .memoria-section-box {
        background: white;
        border-radius: 1.5rem;
        border: 1px solid rgba(226, 232, 240, 0.8);
        overflow: hidden;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
    }

    /* Sedes Bar */
    .memoria-sedes-container {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0 1.5rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 1rem;
    }

    .memoria-sedes-list {
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
        padding-bottom: 4px;
    }

    .sede-tab {
        padding: 0.6rem 1.25rem;
        background: transparent;
        border: none;
        border-bottom: 2px solid transparent;
        border-radius: 0;
        font-size: 0.9rem;
        font-weight: 500;
        color: #64748b;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        margin-right: 0.5rem;
    }

    .sede-tab:hover {
        color: #1e293b;
        background: #f1f5f9;
        border-radius: 0.5rem 0.5rem 0 0;
    }

    .sede-tab.active {
        background: transparent;
        color: #2563eb;
        border-bottom: 2px solid #2563eb;
        border-radius: 0;
        box-shadow: none;
    }

    .btn-new-sede {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #f1f5f9;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .btn-new-sede:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    .sede-tab-actions {
        display: none;
        gap: 0.4rem;
        margin-left: 0.6rem;
        padding-left: 0.6rem;
        border-left: 1px solid rgba(255, 255, 255, 0.3);
    }

    .sede-tab.active .sede-tab-actions {
        display: flex;
        color: #64748b;
    }

    .sede-tab.active .sede-action-btn:hover {
        color: #ef4444;
    }

    .sede-tab.active .fa-edit:hover {
        color: #2563eb;
    }

    .sede-action-btn {
        opacity: 0.8;
        transition: opacity 0.2s;
        font-size: 0.75rem;
    }

    .sede-action-btn:hover {
        opacity: 1;
    }

    .memoria-box-notas {
        flex: 1.5;
        /* Más espacio para las notas */
        min-height: 0;
    }

    .memoria-box-historial {
        flex: 1;
        /* Menos espacio para el historial */
        min-height: 250px;
    }

    .memoria-section-title {
        padding: 0.85rem 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        font-weight: 700;
        color: #1e293b;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #fcfcfd;
    }

    .btn-add-circle {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #f1f5f9;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        font-size: 0.8rem;
    }

    .btn-add-circle:hover {
        background: #e2e8f0;
        color: #2563eb;
        transform: scale(1.1);
    }

    /* Diagram Viewer */
    .diagram-viewer-container {
        flex: 1;
        position: relative;
        background: #f1f5f9;
        min-height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .diagram-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        color: #94a3b8;
    }

    .diagram-img-wrap {
        width: 100%;
        height: 100%;
        position: relative;
        overflow: hidden;
    }

    .diagram-viewport {
        width: 100%;
        height: 100%;
        overflow: auto;
        cursor: grab;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .diagram-viewport:active {
        cursor: grabbing;
    }

    #diagramImg {
        max-width: none;
        transition: transform 0.2s ease;
        transform-origin: center center;
    }

    .diagram-tools {
        position: absolute;
        bottom: 1.5rem;
        right: 1.5rem;
        display: flex;
        gap: 0.5rem;
        z-index: 10;
        background: rgba(255, 255, 255, 0.9);
        padding: 0.5rem;
        border-radius: 0.75rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(4px);
    }

    .diagram-tools button {
        width: 36px;
        height: 36px;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #475569;
        transition: all 0.2s;
    }

    .diagram-tools button:hover {
        background: #f1f5f9;
        color: #6366f1;
    }

    /* Listas (Notas e Historial) */
    .notes-list,
    .history-list-compact {
        flex: 1;
        overflow-y: auto;
        padding: 1.25rem;
    }

    /* Nota Card */
    .note-card {
        padding: 1.5rem;
        border-radius: 1.25rem;
        background: white;
        border: 1px solid #f1f5f9;
        margin-bottom: 1.25rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        cursor: grab;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    }

    .note-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 20px -8px rgba(0, 0, 0, 0.08);
        border-color: #e2e8f0;
    }

    .note-card.pinned {
        background: #fffbeb;
        border: 1px solid #fde68a;
    }

    .note-card.pinned:hover {
        background: #fffcf0;
        border-color: #fbbf24;
    }

    .note-card.dragging {
        opacity: 0.4;
        transform: scale(0.98);
        border: 2px dashed #2563eb;
    }

    .note-card.drag-over {
        border-top: 3px solid #2563eb;
        margin-top: 1rem;
    }

    .drag-handle {
        cursor: grab;
        color: #d1d5db;
        padding: 4px;
    }

    .drag-handle:active {
        cursor: grabbing;
    }

    .note-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .note-title {
        font-weight: 700;
        color: #1e293b;
        font-size: 0.95rem;
    }

    .note-date {
        font-size: 0.75rem;
        color: #94a3b8;
    }

    .note-body {
        font-size: 0.875rem;
        color: #475569;
        line-height: 1.5;
        white-space: pre-wrap;
    }

    .note-footer {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #edf2f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.7rem;
        color: #94a3b8;
    }

    /* Historial Compacto */
    .history-item-compact {
        display: flex;
        gap: 1rem;
        padding: 1.25rem 0;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s;
    }

    .history-item-compact:hover {
        background: #fcfdfe;
    }

    .history-item-compact:last-child {
        border-bottom: none;
    }

    .history-icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .icon-diag {
        background: #dcfce7;
        color: #166534;
    }

    .icon-note {
        background: #e0e7ff;
        color: #3730a3;
    }

    .icon-delete {
        background: #fee2e2;
        color: #dc2626;
    }

    .history-info-compact {
        flex: 1;
    }

    .history-text-compact {
        font-size: 0.85rem;
        color: #334155;
        margin-bottom: 0.15rem;
    }

    .history-meta-compact {
        font-size: 0.75rem;
        color: #94a3b8;
        display: flex;
        justify-content: space-between;
    }

    .badge-nota {
        background: #e0e7ff;
        color: #4338ca;
    }

    .badge-diagrama {
        background: #d1fae5;
        color: #065f46;
    }

    /* Modals */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }

    .modal-box {
        background: white;
        width: 100%;
        max-width: 600px;
        border-radius: 1.25rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .modal-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        font-weight: 700;
        color: #1e293b;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        padding: 1rem 1.5rem;
        background: #f8fafc;
        border-top: 1px solid #f1f5f9;
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }

    .modal-input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        outline: none;
        transition: border-color 0.2s;
    }

    .modal-input:focus {
        border-color: #6366f1;
    }

    .modal-textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        outline: none;
        resize: none;
        transition: border-color 0.2s;
    }

    .modal-textarea:focus {
        border-color: #6366f1;
    }

    .btn-cancel {
        padding: 0.6rem 1.2rem;
        border-radius: 0.5rem;
        font-weight: 600;
        color: #64748b;
    }

    .btn-save {
        padding: 0.6rem 1.2rem;
        border-radius: 0.5rem;
        font-weight: 600;
        background: #6366f1;
        color: white;
    }

    .btn-save:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Dropzone */
    .diagrama-dropzone {
        border: 2px dashed #e2e8f0;
        border-radius: 1rem;
        padding: 3rem 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .diagrama-dropzone:hover,
    .diagrama-dropzone.active {
        border-color: #6366f1;
        background: #f5f3ff;
    }

    /* Fullscreen */
    .fs-overlay {
        position: fixed;
        inset: 0;
        background: #0f172a;
        z-index: 3000;
        display: flex;
        flex-direction: column;
    }

    .fs-toolbar {
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: white;
        background: rgba(0, 0, 0, 0.5);
    }

    .fs-canvas {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: auto;
    }

    #fsImg {
        max-width: none;
        transition: transform 0.2s ease;
    }

    /* Progress */
    .progress-wrap {
        margin-top: 1rem;
    }

    .progress-bar {
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 0.25rem;
    }

    .progress-fill {
        height: 100%;
        background: #6366f1;
        width: 0%;
        transition: width 0.3s;
    }
</style>

<script>
    let currentEmpresaId = null;
    let currentSede = null;
    let sessionSedes = []; // Sedes creadas localmente que aún no tienen datos en DB
    let pendingDiagramFile = null;
    let editingNoteId = null;
    let currentZoom = 1;
    let isDragging = false;
    let startX, startY, scrollLeft, scrollTop;

    function exportarMemoriaPDF(tipo = 'sede') {
        if (!currentEmpresaId) {
            alert('Por favor, seleccione una empresa primero.');
            return;
        }

        let url = `Reportes/exportar_memoria_pdf.php?id_empresa=${currentEmpresaId}`;
        if (tipo === 'sede' && currentSede) {
            url += `&sede=${encodeURIComponent(currentSede)}`;
        } else {
            url += `&sede=todas`;
        }

        window.open(url, '_blank');
    }

    // Filtrar empresas
    function filterEmpresas() {
        const search = document.getElementById('searchEmpresa').value.toLowerCase();
        const items = document.querySelectorAll('.memoria-item');
        items.forEach(item => {
            const text = item.querySelector('.empresa-nombre').textContent.toLowerCase();
            item.style.display = text.includes(search) ? 'flex' : 'none';
        });
    }

    // Seleccionar empresa
    function selectEmpresa(id, nombre, element) {
        currentEmpresaId = id;
        currentSede = null;
        sessionSedes = [];

        // UI update
        document.querySelectorAll('.memoria-item').forEach(i => i.classList.remove('active'));
        element.classList.add('active');

        document.getElementById('emptyState').style.display = 'none';
        document.getElementById('companyContent').style.display = 'flex';
        document.getElementById('selectedCompanyName').textContent = nombre;

        // Cargar datos
        cargarMemoria(id);
        resetZoom();
    }

    function cargarMemoria(id, sede = null) {
        if (sede) currentSede = sede;
        const sedeParam = currentSede ? encodeURIComponent(currentSede) : '';
        // Cargar datos de la API (agregando un timestamp para evitar caché)
        fetch(`api/memoria-tecnica.php?id_empresa=${id}&sede=${sedeParam}&_t=${Date.now()}`)
            .then(async r => {
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Error al parsear JSON:", text);
                    throw new Error("Respuesta inválida del servidor");
                }
            })
            .then(res => {
                if (res.success) {
                    let dbSedes = res.sedes || [];
                    let allSedes = [...new Set([...dbSedes, ...sessionSedes])];

                    if (res.sede_actual) {
                        currentSede = res.sede_actual;
                    }
                    renderSedes(allSedes);

                    if (currentSede) {
                        renderNotas(res.actuales);
                        renderHistorial(res.historial);
                        const diagrama = res.actuales.find(m => m.tipo === 'diagrama');
                        renderDiagrama(diagrama);
                    } else {
                        // Limpiar vistas si no hay sede
                        document.getElementById('notesList').innerHTML = '<div class="text-center py-10 text-gray-400">Seleccione o cree una sede para ver el contenido</div>';
                        document.getElementById('historyList').innerHTML = '';
                        renderDiagrama(null);
                    }
                }
            });
    }

    function renderSedes(sedes) {
        const list = document.getElementById('sedesList');
        if (sedes.length === 0) {
            list.innerHTML = '<span class="text-xs text-gray-400 italic py-2">Sin sedes registradas</span>';
            return;
        }
        list.innerHTML = sedes.map(s => `
            <div class="sede-tab ${s === currentSede ? 'active' : ''}" onclick="selectSede(this.dataset.sede)" data-sede="${escapeHTML(s)}">
                <i class="fas fa-map-marker-alt mr-1"></i> 
                <span>${escapeHTML(s)}</span>
                <div class="sede-tab-actions">
                    <i class="fas fa-edit sede-action-btn" onclick="event.stopPropagation(); editarSede(this.closest('.sede-tab').dataset.sede)" title="Renombrar"></i>
                    <i class="fas fa-trash-alt sede-action-btn" onclick="event.stopPropagation(); eliminarSede(this.closest('.sede-tab').dataset.sede)" title="Eliminar"></i>
                </div>
            </div>
        `).join('');
    }

    function selectSede(sede) {
        currentSede = sede;
        cargarMemoria(currentEmpresaId);
    }

    function nuevaSede() {
        document.getElementById('modalSedeTitle').textContent = 'Nueva Sede / Sucursal';
        document.getElementById('btnSedeText').textContent = 'Crear Sede';
        document.getElementById('modalSedeHelp').textContent = 'Al crearla, podrás asignarle notas y diagramas específicos.';
        document.getElementById('inputSedeNombre').value = '';
        document.getElementById('inputSedeOldName').value = '';
        document.getElementById('modalSede').style.display = 'flex';
        setTimeout(() => document.getElementById('inputSedeNombre').focus(), 100);
    }

    function editarSede(oldName) {
        document.getElementById('modalSedeTitle').textContent = 'Renombrar Sede';
        document.getElementById('btnSedeText').textContent = 'Actualizar Nombre';
        document.getElementById('modalSedeHelp').textContent = 'Se actualizarán todas las notas y diagramas asociados.';
        document.getElementById('inputSedeNombre').value = oldName;
        document.getElementById('inputSedeOldName').value = oldName;
        document.getElementById('modalSede').style.display = 'flex';
        setTimeout(() => document.getElementById('inputSedeNombre').focus(), 100);
    }

    function cerrarModalSede() {
        document.getElementById('modalSede').style.display = 'none';
    }

    function confirmarSede() {
        const nombre = document.getElementById('inputSedeNombre').value.trim();
        const oldName = document.getElementById('inputSedeOldName').value;

        if (!nombre) {
            showAlert('Por favor, ingrese un nombre para la sede', 'warning');
            return;
        }

        if (oldName) {
            // EDITAR
            if (nombre === oldName) {
                cerrarModalSede();
                return;
            }
            const formData = new FormData();
            formData.append('action', 'edit_sede');
            formData.append('id_empresa', currentEmpresaId);
            formData.append('sede_old', oldName);
            formData.append('sede_new', nombre);

            fetch('api/memoria-tecnica.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        currentSede = nombre;
                        cerrarModalSede();
                        cargarMemoria(currentEmpresaId);
                    } else {
                        showAlert('Error al renombrar la sede', 'error');
                    }
                });
        } else {
            // CREAR
            const formData = new FormData();
            formData.append('action', 'crear_sede');
            formData.append('id_empresa', currentEmpresaId);
            formData.append('sede', nombre);

            fetch('api/memoria-tecnica.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        currentSede = nombre;
                        cerrarModalSede();
                        cargarMemoria(currentEmpresaId);
                    } else {
                        showAlert('Error al crear la sede', 'error');
                    }
                });
        }
    }

    function eliminarSede(sede) {
        showConfirm(`¿Está seguro de eliminar la sede "${sede}" y todo su contenido?`, () => {
            const formData = new FormData();
            formData.append('action', 'delete_sede');
            formData.append('id_empresa', currentEmpresaId);
            formData.append('sede', sede);

            fetch('api/memoria-tecnica.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        currentSede = 'General';
                        cargarMemoria(currentEmpresaId);
                    } else {
                        showAlert('Error al eliminar la sede', 'error');
                    }
                });
        });
    }

    function eliminarDiagrama() {
        if (!currentSede) return;
        showConfirm(`¿Está seguro de eliminar el diagrama de red de la sede "${currentSede}"?`, () => {
            const formData = new FormData();
            formData.append('action', 'delete_diagrama');
            formData.append('id_empresa', currentEmpresaId);
            formData.append('sede', currentSede);

            fetch('api/memoria-tecnica.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showAlert('Diagrama eliminado correctamente', 'success');
                        cargarMemoria(currentEmpresaId);
                    } else {
                        showAlert('Error al eliminar diagrama', 'error');
                    }
                });
        });
    }

    function renderNotas(data) {
        const list = document.getElementById('notesList');
        const notas = data.filter(item => item.tipo === 'nota' && item.activo == 1);

        if (!notas || notas.length === 0) {
            list.innerHTML = '<div class="text-center py-10 text-gray-400">Sin notas técnicas registradas</div>';
            return;
        }

        list.innerHTML = notas.map(item => {
            const date = new Date(item.fecha_actualizacion).toLocaleString('es-MX', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            const isPinned = item.fijado == 1;

            return `
                <div class="note-card ${isPinned ? 'pinned' : ''}" 
                     ${!isPinned ? 'draggable="true" ondragstart="handleDragStart(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondragend="handleDragEnd(event)" ondrop="handleDropNote(event)"' : ''} 
                     data-id="${item.id}">
                    <div class="note-header">
                        <div class="flex items-center gap-2">
                            ${isPinned ? '<i class="fas fa-thumbtack text-amber-500 text-xs"></i>' : '<i class="fas fa-grip-vertical drag-handle mr-1"></i>'}
                            <span class="note-title">${escapeHTML(item.titulo)}</span>
                        </div>
                        <span class="note-date">${date}</span>
                    </div>
                    <div class="note-body">${escapeHTML(item.descripcion || '')}</div>
                    <div class="note-footer">
                        <div class="flex items-center gap-3">
                            <span><i class="fas fa-user mr-1"></i> ${escapeHTML(item.usuario_nombre || 'Desconocido')}</span>
                        </div>
                        <div class="flex gap-3">
                            <button onclick="togglePin(${item.id}, ${item.fijado})" class="${isPinned ? 'text-amber-500' : 'text-gray-400'} hover:text-amber-600 transition-colors" title="${isPinned ? 'Desfijar' : 'Fijar'}">
                                <i class="fas fa-thumbtack"></i>
                            </button>
                            <button onclick="prepararEdicionDesdeBoton(this)" 
                                    data-id="${item.id}"
                                    data-titulo="${escapeHTML(item.titulo)}"
                                    data-descripcion="${escapeHTML(item.descripcion || '')}"
                                    data-sede="${escapeHTML(item.sede || 'General')}"
                                    class="text-blue-400 hover:text-blue-600 transition-colors" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="eliminarItem(${item.id})" class="text-red-400 hover:text-red-600 transition-colors" title="Eliminar">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderHistorial(data) {
        const list = document.getElementById('historyList');
        if (!list) return; // Si no tiene permiso, el elemento no existe

        if (!data || data.length === 0) {
            list.innerHTML = '<div class="text-center py-4 text-gray-400 text-sm">Sin actividad reciente</div>';
            return;
        }

        list.innerHTML = data.map(item => {
            const date = new Date(item.fecha_hora).toLocaleString('es-MX', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            const action = item.accion.toLowerCase();
            let iconClass = 'icon-note';
            let icon = 'fa-sticky-note';

            if (action.includes('eliminó') && action.includes('sede')) {
                iconClass = 'icon-delete';
                icon = 'fa-trash-alt';
            } else if (action.includes('eliminó') && action.includes('nota')) {
                iconClass = 'icon-delete';
                icon = 'fa-sticky-note';
            } else if (action.includes('eliminó') && action.includes('diagrama')) {
                iconClass = 'icon-delete';
                icon = 'fa-project-diagram';
            } else if (action.includes('diagrama')) {
                iconClass = 'icon-diag';
                icon = 'fa-project-diagram';
            }

            return `
                <div class="history-item-compact">
                    <div class="history-icon-circle ${iconClass}">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="history-info-compact">
                        <div class="history-text-compact">${item.accion}</div>
                        <div class="history-meta-compact">
                            <span>${date}</span>
                            <span>${item.usuario_nombre || '---'}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderDiagrama(diagrama) {
        const placeholder = document.getElementById('diagramPlaceholder');
        const wrap = document.getElementById('diagramImageWrap');
        const img = document.getElementById('diagramImg');

        if (diagrama && diagrama.archivo_url) {
            placeholder.style.display = 'none';
            wrap.style.display = 'block';
            img.src = diagrama.archivo_url + '?t=' + Date.now();
        } else {
            placeholder.style.display = 'flex';
            wrap.style.display = 'none';
        }
    }

    // Zoom & Pan
    const viewport = document.getElementById('diagramViewport');
    const img = document.getElementById('diagramImg');

    function applyZoom() {
        img.style.transform = `scale(${currentZoom})`;
    }

    function zoomIn() {
        currentZoom = Math.min(currentZoom + 0.25, 4);
        applyZoom();
    }

    function zoomOut() {
        currentZoom = Math.max(currentZoom - 0.25, 0.25);
        applyZoom();
    }

    function resetZoom() {
        currentZoom = 1;
        applyZoom();
        if (viewport) {
            viewport.scrollLeft = (viewport.scrollWidth - viewport.clientWidth) / 2;
            viewport.scrollTop = (viewport.scrollHeight - viewport.clientHeight) / 2;
        }
    }

    // Drag to pan
    if (viewport) {
        viewport.addEventListener('mousedown', (e) => {
            isDragging = true;
            viewport.style.cursor = 'grabbing';
            startX = e.pageX - viewport.offsetLeft;
            startY = e.pageY - viewport.offsetTop;
            scrollLeft = viewport.scrollLeft;
            scrollTop = viewport.scrollTop;
        });

        window.addEventListener('mouseup', () => {
            isDragging = false;
            if (viewport) viewport.style.cursor = 'grab';
        });

        viewport.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            e.preventDefault();
            const x = e.pageX - viewport.offsetLeft;
            const y = e.pageY - viewport.offsetTop;
            const walkX = (x - startX);
            const walkY = (y - startY);
            viewport.scrollLeft = scrollLeft - walkX;
            viewport.scrollTop = scrollTop - walkY;
        });

        viewport.addEventListener('wheel', (e) => {
            if (e.ctrlKey) {
                e.preventDefault();
                e.deltaY < 0 ? zoomIn() : zoomOut();
            }
        }, { passive: false });
    }

    // Modals
    function abrirModalNota() {
        if (!currentSede) {
            showAlert('Primero debe seleccionar o crear una sede', 'warning');
            return;
        }
        editingNoteId = null;
        document.getElementById('modalTitle').textContent = 'Agregar Nota a Memoria Técnica';
        document.getElementById('notaIdEmpresa').value = currentEmpresaId;
        document.getElementById('formNota').reset();
        document.getElementById('modalNota').style.display = 'flex';
    }

    function prepararEdicion(id, titulo, descripcion, sede) {
        editingNoteId = id;
        document.getElementById('modalTitle').textContent = 'Editar Nota Técnica';
        document.getElementById('notaIdEmpresa').value = currentEmpresaId;
        document.getElementById('notaTitulo').value = titulo;
        document.getElementById('notaDescripcion').value = descripcion;
        document.getElementById('modalNota').style.display = 'flex';
    }

    function cerrarModalNota() {
        document.getElementById('modalNota').style.display = 'none';
        document.getElementById('formNota').reset();
        editingNoteId = null;
    }

    function guardarNota(e) {
        e.preventDefault();
        const id_empresa = document.getElementById('notaIdEmpresa').value;
        const titulo = document.getElementById('notaTitulo').value;
        const descripcion = document.getElementById('notaDescripcion').value;
        const sede = currentSede;

        if (editingNoteId) {
            // Actualizar nota existente
            fetch('api/memoria-tecnica.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${editingNoteId}&titulo=${encodeURIComponent(titulo)}&descripcion=${encodeURIComponent(descripcion)}&sede=${encodeURIComponent(sede)}`
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showAlert('Nota actualizada correctamente', 'success');
                        cerrarModalNota();
                        cargarMemoria(id_empresa);
                    } else {
                        showAlert(res.error || 'Error al actualizar', 'error');
                    }
                });
        } else {
            // Crear nueva nota
            const formData = new FormData();
            formData.append('id_empresa', id_empresa);
            formData.append('tipo', 'nota');
            formData.append('titulo', titulo);
            formData.append('descripcion', descripcion);
            formData.append('sede', sede);

            fetch('api/memoria-tecnica.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showAlert('Nota guardada correctamente', 'success');
                        cerrarModalNota();
                        cargarMemoria(id_empresa);
                    } else {
                        showAlert(res.error || 'Error al guardar', 'error');
                    }
                });
        }
    }

    function abrirModalUpload() {
        if (!currentSede) {
            showAlert('Primero debe seleccionar o crear una sede', 'warning');
            return;
        }
        document.getElementById('uploadIdEmpresa').value = currentEmpresaId;
        document.getElementById('modalUpload').style.display = 'flex';
    }

    function cerrarModalUpload() {
        document.getElementById('modalUpload').style.display = 'none';
        document.getElementById('dropzonePreview').style.display = 'none';
        document.getElementById('dropzoneContent').style.display = 'block';
        document.getElementById('btnSubmitUpload').disabled = true;
        document.getElementById('uploadProgress').style.display = 'none';
        document.getElementById('formUpload').reset();
        pendingDiagramFile = null;
    }

    function handleFile(file) {
        if (!file) return;
        if (!file.type.startsWith('image/')) {
            showAlert('Solo se permiten imágenes', 'error');
            return;
        }

        pendingDiagramFile = file;

        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('dropzonePreview').style.display = 'block';
            document.getElementById('dropzoneContent').style.display = 'none';
            document.getElementById('btnSubmitUpload').disabled = false;
        };
        reader.readAsDataURL(file);
    }

    function handleDrop(e) {
        e.preventDefault();
        document.getElementById('dropzone').classList.remove('active');
        handleFile(e.dataTransfer.files[0]);
    }

    function subirDiagrama(e) {
        e.preventDefault();
        const file = pendingDiagramFile || document.getElementById('fileInput').files[0];
        const id = document.getElementById('uploadIdEmpresa').value;
        const sede = currentSede;

        if (!file) {
            showAlert('Por favor, seleccione una imagen', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('diagrama', file);
        formData.append('id_empresa', id);
        formData.append('sede', sede);

        const progressWrap = document.getElementById('uploadProgress');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        const btn = document.getElementById('btnSubmitUpload');

        progressWrap.style.display = 'block';
        btn.disabled = true;

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/upload-diagrama.php', true);

        xhr.upload.onprogress = (ev) => {
            if (ev.lengthComputable) {
                const pct = Math.round((ev.loaded / ev.total) * 100);
                progressFill.style.width = pct + '%';
                progressText.textContent = `Subiendo... ${pct}%`;
            }
        };

        xhr.onload = () => {
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.success) {
                    showAlert('Diagrama actualizado correctamente', 'success');
                    cerrarModalUpload();
                    cargarMemoria(id);
                } else {
                    showAlert(res.error || 'Error al subir', 'error');
                    btn.disabled = false;
                }
            } catch (e) {
                console.error("Respuesta del servidor no válida:", xhr.responseText);
                showAlert('Error en la respuesta del servidor. Revise la consola para más detalles.', 'error');
                btn.disabled = false;
            }
        };

        xhr.send(formData);
    }

    function togglePin(id, currentFijado) {
        const nuevoEstado = currentFijado == 1 ? 0 : 1;
        fetch('api/memoria-tecnica.php', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&fijado=${nuevoEstado}`
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    cargarMemoria(currentEmpresaId);
                } else {
                    showAlert('Error al cambiar estado', 'error');
                }
            });
    }

    // Drag & Drop Logic
    let dragSrcEl = null;

    function handleDragStart(e) {
        dragSrcEl = e.currentTarget;
        e.dataTransfer.effectAllowed = 'move';
        // Usar una imagen de arrastre vacía o personalizada si se desea, 
        // pero por ahora solo ajustaremos la opacidad para que sea más limpio
        e.currentTarget.classList.add('dragging');
    }

    function handleDragOver(e) {
        if (e.preventDefault) e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const target = e.currentTarget;
        if (target !== dragSrcEl && !target.classList.contains('pinned')) {
            target.classList.add('drag-over');
        }
        return false;
    }

    function handleDragLeave(e) {
        e.currentTarget.classList.remove('drag-over');
    }

    function handleDragEnd(e) {
        e.currentTarget.classList.remove('dragging');
        document.querySelectorAll('.note-card').forEach(card => card.classList.remove('drag-over'));
    }

    function handleDropNote(e) {
        e.stopPropagation();
        e.preventDefault();

        if (dragSrcEl !== e.currentTarget) {
            const list = document.getElementById('notesList');
            const cards = Array.from(list.querySelectorAll('.note-card:not(.pinned)'));
            const srcIdx = cards.indexOf(dragSrcEl);
            const destIdx = cards.indexOf(e.currentTarget);

            if (srcIdx > -1 && destIdx > -1) {
                // Mover el elemento en el DOM
                if (srcIdx < destIdx) {
                    e.currentTarget.after(dragSrcEl);
                } else {
                    e.currentTarget.before(dragSrcEl);
                }

                // Guardar nuevo orden
                saveNewOrder();
            }
        }
        return false;
    }

    function saveNewOrder() {
        const list = document.getElementById('notesList');
        const ids = Array.from(list.querySelectorAll('.note-card:not(.pinned)')).map(card => card.dataset.id);

        const formData = new FormData();
        formData.append('action', 'save_order');
        ids.forEach(id => formData.append('ids[]', id));

        fetch('api/memoria-tecnica.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(res => {
                if (!res.success) showAlert('Error al guardar el orden', 'error');
            });
    }

    function eliminarItem(id) {
        showConfirm('¿Está seguro de eliminar este registro?', () => {
            fetch('api/memoria-tecnica.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showAlert('Registro eliminado', 'success');
                        cargarMemoria(currentEmpresaId);
                    } else {
                        showAlert(res.error || 'Error al eliminar', 'error');
                    }
                });
        });
    }

    // Fullscreen
    function abrirFullscreen() {
        const src = document.getElementById('diagramImg').src;
        document.getElementById('fsImg').src = src;
        document.getElementById('fsViewer').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function cerrarFullscreen() {
        document.getElementById('fsViewer').style.display = 'none';
        document.body.style.overflow = '';
    }

    // Atajos de teclado
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            cerrarFullscreen();
            cerrarModalNota();
            cerrarModalUpload();
        }
    });

    function escapeHTML(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function prepararEdicionDesdeBoton(btn) {
        const id = parseInt(btn.dataset.id);
        const titulo = btn.dataset.titulo;
        const descripcion = btn.dataset.descripcion;
        const sede = btn.dataset.sede;
        prepararEdicion(id, titulo, descripcion, sede);
    }
</script>

<?php require_once 'includes/footer.php'; ?>