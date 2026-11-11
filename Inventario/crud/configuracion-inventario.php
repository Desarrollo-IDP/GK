<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
$is_admin = isAdmin();

// Verificar si existen las tablas de configuración
$table_ubicaciones = $conn->query("SHOW TABLES LIKE 'inventario_config_ubicaciones'");
$table_activos = $conn->query("SHOW TABLES LIKE 'inventario_config_activos'");
$has_config_tables = $table_ubicaciones && $table_ubicaciones->num_rows > 0 && $table_activos && $table_activos->num_rows > 0;

$check_empresas = $conn->query("SHOW COLUMNS FROM empresas LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_empresas && $check_empresas->num_rows > 0;

// Empresa activa para config: usuario normal = su empresa; admin = puede elegir
$config_empresa_id = $user_empresa_id; // id_empresa_usuario que usamos para filtrar
if ($is_admin && $has_empresa_usuario_column && isset($_GET['empresa'])) {
    $empresa_inventario_id = (int) $_GET['empresa'];
    $r = $conn->prepare("SELECT id_empresa_usuario FROM empresas WHERE id_empresa = ?");
    $r->bind_param("i", $empresa_inventario_id);
    $r->execute();
    $res = $r->get_result();
    $row = $res->fetch_assoc();
    if ($row && $row['id_empresa_usuario'] !== null) {
        $config_empresa_id = (int) $row['id_empresa_usuario'];
    }
    $res->free();
    $r->close();
}

$pageTitle = 'Configuración - Ubicación y Activo';
require_once 'includes/header.php';

// Listar empresas para selector de admin
$empresas_list = [];
if ($is_admin && $has_empresa_usuario_column) {
    $q = $conn->query("SELECT id_empresa, nombre, id_empresa_usuario FROM empresas ORDER BY nombre");
    while ($e = $q->fetch_assoc()) {
        $empresas_list[] = $e;
    }
    if ($q) $q->free_result();
}
?>

<?php
$count_ubicaciones = 0;
$count_activos = 0;
if ($has_config_tables && $config_empresa_id !== null) {
    $st = $conn->prepare("SELECT COUNT(*) as c FROM inventario_config_ubicaciones WHERE id_empresa_usuario = ?");
    $st->bind_param("i", $config_empresa_id);
    $st->execute();
    $count_ubicaciones = (int) $st->get_result()->fetch_assoc()['c'];
    $st->close();
    $st = $conn->prepare("SELECT COUNT(*) as c FROM inventario_config_activos WHERE id_empresa_usuario = ?");
    $st->bind_param("i", $config_empresa_id);
    $st->execute();
    $count_activos = (int) $st->get_result()->fetch_assoc()['c'];
    $st->close();
}
?>
<style>
    /* Base inventario */
    .inv-page-wrap { max-width: 1600px; }
    .inv-brand-bg { background-color: #1E2A3B; }
    .inv-input { min-height: 42px; border-radius: 0.75rem; }
    .inv-input:hover { border-color: #cbd5e1; }
    .inv-btn-primary:hover { filter: brightness(1.08); }
    .inicio-header {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
        padding: 1.25rem 1.75rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
    }
    .inicio-header-subtitle { margin: 0.25rem 0 0; font-size: 0.9rem; color: #6b7280; }
    /* Tabs */
    .tabs-container {
        background: #ffffff;
        border-radius: 1rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        border: 1px solid #f3f4f6;
    }
    .tabs-header { display: flex; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
    .tab-button {
        flex: 1;
        padding: 1rem 1.25rem;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 0.9375rem;
        font-weight: 600;
        color: #6b7280;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        position: relative;
    }
    .tab-button:hover { background: #f3f4f6; color: #1E2A3B; }
    .tab-button.active { color: #1E2A3B; background: #ffffff; }
    .tab-button.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 3px;
        background: #1E2A3B;
        border-radius: 3px 3px 0 0;
    }
    .tab-content { display: none; padding: 1.5rem 1.75rem; }
    .tab-content.active { display: block; }
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }
    .section-title { font-size: 1.125rem; font-weight: 700; color: #1E2A3B; display: flex; align-items: center; gap: 0.5rem; }
    .section-title i { color: #1E2A3B; }
    .section-title .count-text { color: #6b7280; font-size: 0.875rem; font-weight: normal; margin-left: 0.25rem; }
    .items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem; }
    .catalog-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        background: #f9fafb;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }
    .catalog-item:hover {
        background: #f3f4f6;
        border-color: #d1d5db;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }
    .catalog-item .item-name { font-size: 0.9375rem; font-weight: 600; color: #1E2A3B; flex: 1; }
    .catalog-item .item-actions { display: flex; gap: 0.5rem; align-items: center; }
    .catalog-item .editar-item, .catalog-item .eliminar-item { padding: 0.375rem 0.75rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 500; transition: all 0.2s; }
    .catalog-item .editar-item { color: #1d4ed8; background: #eff6ff; }
    .catalog-item .editar-item:hover { background: #dbeafe; color: #1e40af; }
    .catalog-item .eliminar-item { color: #b91c1c; background: #fef2f2; }
    .catalog-item .eliminar-item:hover { background: #fee2e2; color: #991b1b; }
    .empty-state { text-align: center; padding: 2.5rem 1rem; color: #6b7280; grid-column: 1 / -1; }
    .empty-state i { font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.5; color: #9ca3af; }
    .empty-state p { font-size: 0.9375rem; margin-bottom: 0; color: #6b7280; }
    .count-badge { background: #1E2A3B; color: #fff; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; margin-left: 0.375rem; }
    .modal-backdrop {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5); z-index: 1050;
        display: flex; align-items: center; justify-content: center;
        padding: 1rem; opacity: 0; visibility: hidden; transition: opacity 0.2s, visibility 0.2s;
    }
    .modal-backdrop.active { opacity: 1; visibility: visible; }
    .modal-box {
        background: #fff; border-radius: 1rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        width: 100%; max-width: 420px; transform: scale(0.96); transition: transform 0.2s;
        border: 1px solid #f3f4f6;
    }
    .modal-backdrop.active .modal-box { transform: scale(1); }
    .modal-confirm-box { max-width: 380px; }
    .modal-header-inv { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb; }
    .modal-header-inv h3 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #1E2A3B; }
    .modal-close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; line-height: 1; padding: 0.25rem; border-radius: 0.5rem; }
    .modal-close-btn:hover { color: #1E2A3B; background: #f3f4f6; }
    @media (max-width: 768px) {
        .inicio-header { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
        .inicio-header h1 { font-size: 1.25rem; }
        .tabs-header { flex-direction: column; }
        .tab-button.active::after { height: 0; border-left: 3px solid #1E2A3B; top: 0; bottom: 0; left: 0; right: auto; border-radius: 0; }
        .items-grid { grid-template-columns: 1fr; }
        .section-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
    }
</style>

<div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
    <?php if (!$has_config_tables): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 text-red-800 px-4 py-3 text-sm">
            Las tablas de configuración no existen. Ejecuta el script SQL: <code class="bg-red-100 px-1.5 py-0.5 rounded font-mono text-xs">sql/inventario_config_ubicacion_activo.sql</code>
        </div>
    <?php elseif ($config_empresa_id === null && $is_admin): ?>
        <div class="inicio-header mb-8">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg flex-shrink-0">
                    <i class="fas fa-cog text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Configuración</h1>
                    <p class="inicio-header-subtitle">Ubicaciones y activos del equipo por empresa.</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 lg:p-8 max-w-md">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-building text-gray-500"></i> Seleccionar empresa
            </h2>
            <?php if (!empty($empresas_list)): ?>
            <form method="GET">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Empresa</label>
                <select name="empresa" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none" onchange="this.form.submit()">
                    <option value="">— Elegir empresa —</option>
                    <?php foreach ($empresas_list as $emp): ?>
                        <option value="<?php echo (int)$emp['id_empresa']; ?>"><?php echo htmlspecialchars($emp['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>
    <?php else: ?>

    <div class="inicio-header mb-8">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg flex-shrink-0">
                <i class="fas fa-cog text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Configuración</h1>
                <p class="inicio-header-subtitle">Gestiona ubicaciones y activos del equipo para el inventario.</p>
            </div>
        </div>
    </div>

    <?php if ($is_admin && !empty($empresas_list) && isset($_GET['empresa'])): ?>
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <label class="block text-sm font-medium text-gray-700">Empresa</label>
            <select name="empresa" class="inv-input px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none min-w-[200px]" onchange="this.form.submit()">
                <?php foreach ($empresas_list as $emp): ?>
                    <option value="<?php echo (int)$emp['id_empresa']; ?>" <?php echo (isset($_GET['empresa']) && (int)$_GET['empresa'] === (int)$emp['id_empresa']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>

    <div class="tabs-container">
        <div class="tabs-header">
            <button type="button" class="tab-button active" data-tab="tab-ubicaciones" onclick="cambiarTabConfig('tab-ubicaciones')">
                <i class="fas fa-map-marker-alt text-sm"></i>
                Ubicaciones
                <span class="count-badge"><?php echo $count_ubicaciones; ?></span>
            </button>
            <button type="button" class="tab-button" data-tab="tab-activos" onclick="cambiarTabConfig('tab-activos')">
                <i class="fas fa-tag text-sm"></i>
                Activos del equipo
                <span class="count-badge"><?php echo $count_activos; ?></span>
            </button>
        </div>

        <!-- Pestaña Ubicaciones -->
        <div id="tab-ubicaciones" class="tab-content active">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-list-ul"></i> Ubicaciones
                    <span class="count-text">(<?php echo $count_ubicaciones; ?> <?php echo $count_ubicaciones === 1 ? 'opción' : 'opciones'; ?>)</span>
                </div>
                <button type="button" id="btn_agregar_ubicacion" class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all">
                    <i class="fas fa-plus"></i> Agregar
                </button>
            </div>
            <div class="items-grid" id="lista_ubicaciones_wrapper">
                <?php
                if ($has_config_tables && $config_empresa_id !== null) {
                    $stmt = $conn->prepare("SELECT id, valor FROM inventario_config_ubicaciones WHERE id_empresa_usuario = ? ORDER BY valor");
                    $stmt->bind_param("i", $config_empresa_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        echo '<div class="catalog-item" data-id="' . (int)$row['id'] . '">';
                        echo '<span class="item-name valor-texto">' . htmlspecialchars($row['valor']) . '</span>';
                        echo '<div class="item-actions">';
                        echo '<button type="button" class="editar-item text-blue-600 hover:text-blue-900 text-sm font-medium bg-transparent border-none cursor-pointer">Editar</button>';
                        echo '<button type="button" class="eliminar-item text-red-600 hover:text-red-900 text-sm font-medium bg-transparent border-none cursor-pointer">Eliminar</button>';
                        echo '</div></div>';
                    }
                    $res->free();
                    $stmt->close();
                }
                ?>
                <div id="msg_ubicaciones" class="empty-state" style="display:none;">
                    <i class="fas fa-inbox"></i>
                    <p>No hay ubicaciones. Agrega una arriba.</p>
                </div>
            </div>
        </div>

        <!-- Pestaña Activos -->
        <div id="tab-activos" class="tab-content">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-list-ul"></i> Activos del equipo
                    <span class="count-text">(<?php echo $count_activos; ?> <?php echo $count_activos === 1 ? 'opción' : 'opciones'; ?>)</span>
                </div>
                <button type="button" id="btn_agregar_activo" class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all">
                    <i class="fas fa-plus"></i> Agregar
                </button>
            </div>
            <div class="items-grid" id="lista_activos_wrapper">
                <?php
                if ($has_config_tables && $config_empresa_id !== null) {
                    $stmt = $conn->prepare("SELECT id, valor FROM inventario_config_activos WHERE id_empresa_usuario = ? ORDER BY valor");
                    $stmt->bind_param("i", $config_empresa_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        echo '<div class="catalog-item" data-id="' . (int)$row['id'] . '">';
                        echo '<span class="item-name valor-texto">' . htmlspecialchars($row['valor']) . '</span>';
                        echo '<div class="item-actions">';
                        echo '<button type="button" class="editar-item text-blue-600 hover:text-blue-900 text-sm font-medium bg-transparent border-none cursor-pointer">Editar</button>';
                        echo '<button type="button" class="eliminar-item text-red-600 hover:text-red-900 text-sm font-medium bg-transparent border-none cursor-pointer">Eliminar</button>';
                        echo '</div></div>';
                    }
                    $res->free();
                    $stmt->close();
                }
                ?>
                <div id="msg_activos" class="empty-state" style="display:none;">
                    <i class="fas fa-inbox"></i>
                    <p>No hay activos. Agrega uno arriba.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div id="modalEditar" class="modal-backdrop" aria-hidden="true">
    <div class="modal-box p-6" role="dialog" aria-labelledby="modalEditarTitulo">
        <div class="modal-header-inv">
            <h3 id="modalEditarTitulo">Editar valor</h3>
            <button type="button" class="modal-close-btn" id="modalEditarCerrar2" aria-label="Cerrar">&times;</button>
        </div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nuevo valor</label>
        <input type="text" id="modalEditarInput" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none mb-5" maxlength="500" placeholder="Escriba el nuevo valor">
        <div class="flex justify-end gap-3">
            <button type="button" id="modalEditarCerrar" class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all">Cancelar</button>
            <button type="button" id="modalEditarGuardar" class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal Confirmar eliminar -->
<div id="modalEliminar" class="modal-backdrop" aria-hidden="true">
    <div class="modal-box modal-confirm-box p-6" role="dialog">
        <div class="modal-header-inv">
            <h3>¿Eliminar esta opción?</h3>
            <button type="button" class="modal-close-btn" id="modalEliminarCerrar2" aria-label="Cerrar">&times;</button>
        </div>
        <p id="modalEliminarTexto" class="text-gray-600 text-sm mb-5">Esta acción no se puede deshacer.</p>
        <div class="flex justify-end gap-3">
            <button type="button" id="modalEliminarCerrar" class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all">Cancelar</button>
            <button type="button" id="modalEliminarConfirmar" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white bg-red-600 hover:bg-red-700 shadow-md hover:shadow-lg transition-all">Eliminar</button>
        </div>
    </div>
</div>

<script>
function cambiarTabConfig(tabId) {
    document.querySelectorAll('.tab-content').forEach(function(el) { el.classList.remove('active'); });
    document.querySelectorAll('.tab-button').forEach(function(el) { el.classList.remove('active'); });
    var tab = document.getElementById(tabId);
    if (tab) tab.classList.add('active');
    var btn = document.querySelector('[data-tab="' + tabId + '"]');
    if (btn) btn.classList.add('active');
}
(function() {
    var configEmpresaId = <?php echo $config_empresa_id !== null ? (int)$config_empresa_id : 'null'; ?>;

    var modalEditar = document.getElementById('modalEditar');
    var modalEditarInput = document.getElementById('modalEditarInput');
    var modalEditarGuardar = document.getElementById('modalEditarGuardar');
    var modalEditarCerrar = document.getElementById('modalEditarCerrar');
    var modalEliminar = document.getElementById('modalEliminar');
    var modalEliminarTexto = document.getElementById('modalEliminarTexto');
    var modalEliminarConfirmar = document.getElementById('modalEliminarConfirmar');
    var modalEliminarCerrar = document.getElementById('modalEliminarCerrar');

    var editCallback = null;
    var addCallback = null;
    var deleteCallback = null;

    function openModalAdd(titulo, tipo, wrapperId, msgId) {
        document.getElementById('modalEditarTitulo').textContent = titulo;
        modalEditarInput.value = '';
        modalEditarInput.placeholder = tipo === 'ubicacion' ? ' ' : ' ';
        editCallback = null;
        addCallback = function(valor) {
            agregar(tipo, valor, wrapperId, msgId);
        };
        modalEditar.classList.add('active');
        modalEditar.setAttribute('aria-hidden', 'false');
        modalEditarInput.focus();
    }

    function toggleMsg(wrapperId, msgId) {
        var wrapper = document.getElementById(wrapperId);
        var msg = document.getElementById(msgId);
        if (wrapper && msg) {
            var count = wrapper.querySelectorAll('.catalog-item').length;
            msg.style.display = count === 0 ? 'block' : 'none';
        }
    }
    toggleMsg('lista_ubicaciones_wrapper', 'msg_ubicaciones');
    toggleMsg('lista_activos_wrapper', 'msg_activos');

    function request(url, body, method) {
        method = method || 'POST';
        if (configEmpresaId != null) body.id_empresa_usuario = configEmpresaId;
        return fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined
        }).then(function(r) { return r.json(); });
    }

    function openModalEdit(valorActual, onSave) {
        document.getElementById('modalEditarTitulo').textContent = 'Editar valor';
        modalEditarInput.placeholder = 'Escriba el nuevo valor';
        editCallback = onSave;
        addCallback = null;
        modalEditarInput.value = valorActual;
        modalEditarInput.focus();
        modalEditar.classList.add('active');
        modalEditar.setAttribute('aria-hidden', 'false');
    }
    function closeModalEdit() {
        modalEditar.classList.remove('active');
        modalEditar.setAttribute('aria-hidden', 'true');
        editCallback = null;
        addCallback = null;
    }
    function openModalDelete(texto, onConfirm) {
        deleteCallback = onConfirm;
        modalEliminarTexto.textContent = texto || 'Esta acción no se puede deshacer.';
        modalEliminar.classList.add('active');
        modalEliminar.setAttribute('aria-hidden', 'false');
    }
    function closeModalDelete() {
        modalEliminar.classList.remove('active');
        modalEliminar.setAttribute('aria-hidden', 'true');
        deleteCallback = null;
    }

    modalEditarCerrar.onclick = closeModalEdit;
    if (document.getElementById('modalEditarCerrar2')) document.getElementById('modalEditarCerrar2').onclick = closeModalEdit;
    modalEditarGuardar.onclick = function() {
        var nuevo = modalEditarInput.value.trim();
        if (!nuevo) return;
        if (editCallback) editCallback(nuevo);
        else if (addCallback) addCallback(nuevo);
        closeModalEdit();
    };
    modalEditar.onclick = function(e) {
        if (e.target === modalEditar) closeModalEdit();
    };
    modalEditarInput.onkeydown = function(e) {
        if (e.key === 'Escape') closeModalEdit();
        if (e.key === 'Enter') modalEditarGuardar.click();
    };

    modalEliminarCerrar.onclick = closeModalDelete;
    if (document.getElementById('modalEliminarCerrar2')) document.getElementById('modalEliminarCerrar2').onclick = closeModalDelete;
    modalEliminarConfirmar.onclick = function() {
        if (deleteCallback) deleteCallback();
        closeModalDelete();
    };
    modalEliminar.onclick = function(e) {
        if (e.target === modalEliminar) closeModalDelete();
    };

    function agregar(tipo, valor, wrapperId, msgId) {
        if (!valor || !configEmpresaId) return;
        request('api/config-inventario.php', { accion: 'agregar', tipo: tipo, valor: valor }).then(function(data) {
            if (data.ok && data.id) {
                var wrapper = document.getElementById(wrapperId);
                var msg = document.getElementById(msgId);
                var div = document.createElement('div');
                div.className = 'catalog-item';
                div.setAttribute('data-id', data.id);
                div.innerHTML = '<span class="item-name valor-texto">' + escapeHtml(valor) + '</span><div class="item-actions"><button type="button" class="editar-item text-blue-600 hover:text-blue-900 text-sm font-medium bg-transparent border-none cursor-pointer">Editar</button><button type="button" class="eliminar-item text-red-600 hover:text-red-900 text-sm font-medium bg-transparent border-none cursor-pointer">Eliminar</button></div>';
                wrapper.insertBefore(div, msg);
                msg.style.display = 'none';
                bindItem(div, tipo, wrapperId, msgId);
                updateCountBadge(tipo === 'ubicacion' ? 'tab-ubicaciones' : 'tab-activos');
            } else {
                alert(data.error || 'Error al agregar');
            }
        });
    }
    function updateCountBadge(tabId) {
        var btn = document.querySelector('[data-tab="' + tabId + '"]');
        if (!btn) return;
        var wrapper = tabId === 'tab-ubicaciones' ? document.getElementById('lista_ubicaciones_wrapper') : document.getElementById('lista_activos_wrapper');
        var badge = btn.querySelector('.count-badge');
        if (badge && wrapper) badge.textContent = wrapper.querySelectorAll('.catalog-item').length;
    }
    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function bindItem(el, tipo, wrapperId, msgId) {
        var span = el.querySelector('.valor-texto') || el.querySelector('.item-name');
        if (!span) return;
        el.querySelector('.editar-item').onclick = function() {
            openModalEdit(span.textContent, function(nuevo) {
                request('api/config-inventario.php', { accion: 'editar', tipo: tipo, id: parseInt(el.getAttribute('data-id'), 10), valor: nuevo }).then(function(data) {
                    if (data.ok) { span.textContent = nuevo; } else { alert(data.error || 'Error al editar'); }
                });
            });
        };
        el.querySelector('.eliminar-item').onclick = function() {
            openModalDelete('Se eliminará la opción: ' + span.textContent, function() {
                request('api/config-inventario.php', { accion: 'eliminar', tipo: tipo, id: parseInt(el.getAttribute('data-id'), 10) }).then(function(data) {
                    if (data.ok) {
                        el.remove();
                        toggleMsg(wrapperId, msgId);
                        updateCountBadge(tipo === 'ubicacion' ? 'tab-ubicaciones' : 'tab-activos');
                    } else { alert(data.error || 'Error al eliminar'); }
                });
            });
        };
    }
    function bindLi(li, tipo, listId, msgId) {
        li.querySelector('.editar-item').onclick = function() {
            var span = li.querySelector('.valor-texto');
            openModalEdit(span.textContent, function(nuevo) {
                request('api/config-inventario.php', { accion: 'editar', tipo: tipo, id: parseInt(li.getAttribute('data-id'), 10), valor: nuevo }).then(function(data) {
                    if (data.ok) { span.textContent = nuevo; } else { alert(data.error || 'Error al editar'); }
                });
            });
        };
        li.querySelector('.eliminar-item').onclick = function() {
            var span = li.querySelector('.valor-texto');
            openModalDelete('Se eliminará: “‘ + span.textContent +’”', function() {
                request('api/config-inventario.php', { accion: 'eliminar', tipo: tipo, id: parseInt(li.getAttribute('data-id'), 10) }).then(function(data) {
                    if (data.ok) {
                        li.remove();
                        toggleMsg(listId, msgId);
                    } else {
                        alert(data.error || 'Error al eliminar');
                    }
                });
            });
        };
    }

    document.querySelectorAll('#lista_ubicaciones_wrapper .catalog-item').forEach(function(el) { bindItem(el, 'ubicacion', 'lista_ubicaciones_wrapper', 'msg_ubicaciones'); });
    document.querySelectorAll('#lista_activos_wrapper .catalog-item').forEach(function(el) { bindItem(el, 'activo', 'lista_activos_wrapper', 'msg_activos'); });

    document.getElementById('btn_agregar_ubicacion').onclick = function() {
        openModalAdd('Agregar ubicación', 'ubicacion', 'lista_ubicaciones_wrapper', 'msg_ubicaciones');
    };
    document.getElementById('btn_agregar_activo').onclick = function() {
        openModalAdd('Agregar activo del equipo', 'activo', 'lista_activos_wrapper', 'msg_activos');
    };
})();
</script>

<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
