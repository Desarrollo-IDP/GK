<?php
require_once '../../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
checkAuth();
$pageTitle = 'Configuración del Sistema';
require_once '../../includes/header.php';

// Obtener empresa del usuario
$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa
$has_empresa_column = false;
$check_column = $conn->query("SHOW COLUMNS FROM catalogos LIKE 'id_empresa'");
if ($check_column && $check_column->num_rows > 0) {
    $has_empresa_column = true;
}

// Procesar acciones
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $modulo = limpiarDatos($_POST['modulo']);
        $campo = limpiarDatos($_POST['campo']);
        $valor = limpiarDatos($_POST['valor']);
        $orden = intval($_POST['orden'] ?? 0);
        
        // Preparar INSERT con o sin id_empresa
        if ($has_empresa_column && $user_empresa_id !== null) {
            $sql = "INSERT INTO catalogos (modulo, campo, valor, orden, id_empresa) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssii', $modulo, $campo, $valor, $orden, $user_empresa_id);
        } else {
            $sql = "INSERT INTO catalogos (modulo, campo, valor, orden) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssi', $modulo, $campo, $valor, $orden);
        }
        
        if ($stmt->execute()) {
            $success_message = 'Valor agregado exitosamente';
        } else {
            if ($conn->errno == 1062) {
                $error_message = 'Este valor ya existe en el catálogo';
            } else {
                $error_message = 'Error al agregar el valor: ' . $conn->error;
            }
        }
    }
    
    if ($action === 'update') {
        $id_catalogo = intval($_POST['id_catalogo']);
        $valor = limpiarDatos($_POST['valor']);
        $orden = intval($_POST['orden'] ?? 0);
        $activo = intval($_POST['activo'] ?? 1);
        
        // Verificar permisos: solo puede modificar catálogos de su empresa
        if ($has_empresa_column && $user_empresa_id !== null) {
            $verify_query = "SELECT id_catalogo FROM catalogos WHERE id_catalogo = ? AND id_empresa = ? LIMIT 1";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bind_param('ii', $id_catalogo, $user_empresa_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                $error_message = 'No tiene permiso para modificar este catálogo';
            } else {
                $sql = "UPDATE catalogos SET valor=?, orden=?, activo=? WHERE id_catalogo=?";
                if ($has_empresa_column && $user_empresa_id !== null) {
                    $sql .= " AND id_empresa = " . intval($user_empresa_id);
                }
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('siii', $valor, $orden, $activo, $id_catalogo);
                
                if ($stmt->execute()) {
                    $success_message = 'Valor actualizado exitosamente';
                } else {
                    $error_message = 'Error al actualizar el valor: ' . $conn->error;
                }
            }
        } else {
            $sql = "UPDATE catalogos SET valor=?, orden=?, activo=? WHERE id_catalogo=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('siii', $valor, $orden, $activo, $id_catalogo);
            
            if ($stmt->execute()) {
                $success_message = 'Valor actualizado exitosamente';
            } else {
                $error_message = 'Error al actualizar el valor: ' . $conn->error;
            }
        }
    }
    
    if ($action === 'delete') {
        $id_catalogo = intval($_POST['id_catalogo']);
        
        // Verificar permisos: solo puede eliminar catálogos de su empresa
        if ($has_empresa_column && $user_empresa_id !== null) {
            $verify_query = "SELECT id_catalogo FROM catalogos WHERE id_catalogo = ? AND id_empresa = ? LIMIT 1";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bind_param('ii', $id_catalogo, $user_empresa_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                $error_message = 'No tiene permiso para eliminar este catálogo';
            } else {
                $sql = "DELETE FROM catalogos WHERE id_catalogo = ?";
                if ($has_empresa_column && $user_empresa_id !== null) {
                    $sql .= " AND id_empresa = " . intval($user_empresa_id);
                }
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $id_catalogo);
                
                if ($stmt->execute()) {
                    $success_message = 'Valor eliminado exitosamente';
                } else {
                    $error_message = 'Error al eliminar el valor: ' . $conn->error;
                }
            }
        } else {
            $sql = "DELETE FROM catalogos WHERE id_catalogo = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id_catalogo);
            
            if ($stmt->execute()) {
                $success_message = 'Valor eliminado exitosamente';
            } else {
                $error_message = 'Error al eliminar el valor: ' . $conn->error;
            }
        }
    }
}

// Definir la configuración de catálogos
// Nota: El módulo 'bodega' con campo 'estado_bodega' ha sido removido intencionalmente
// para que no pueda ser modificado desde esta interfaz
$catalogos_config = [
    [
        'modulo' => 'almacen',
        'titulo' => 'Almacenes',
        'icono' => 'fa-boxes',
        'color' => 'from-green-500 to-green-700',
        'campos' => [
            ['campo' => 'tipo_almacen', 'nombre' => 'Tipo de Almacén'],
            //['campo' => 'estado_almacen', 'nombre' => 'Estado del Almacén']
        ]
    ],
    [
        'modulo' => 'productos',
        'titulo' => 'Productos',
        'icono' => 'fa-box-open',
        'color' => 'from-purple-500 to-purple-700',
        'campos' => [
            ['campo' => 'categoria', 'nombre' => 'Categoría'],
            //['campo' => 'estado_producto', 'nombre' => 'Estado del Producto']
        ]
    ]
];
?>

<style>
    /* Estilos mejorados para configuracion.php */
    .config-header {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 1.5rem 2rem;
        margin-bottom: 1.5rem;
        border-left: 4px solid #1E2A3B;
    }
    
    .config-header h1 {
        color: #1E2A3B;
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .config-header h1 i {
        color: #1E2A3B;
        font-size: 1.5rem;
    }
    
    .tabs-container {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        border: 1px solid #E5E7EB;
    }
    
    .tabs-header {
        display: flex;
        background: #F9FAFB;
        border-bottom: 2px solid #E5E7EB;
    }
    
    .tab-button {
        flex: 1;
        padding: 1.25rem 1.5rem;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        color: #6B7280;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.625rem;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .tab-button:hover {
        background: #F3F4F6;
        color: #1E2A3B;
    }
    
    .tab-button.active {
        color: #1E2A3B;
        background: #FFFFFF;
    }
    
    .tab-button.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 3px;
        background: #1E2A3B;
    }
    
    .tab-content {
        display: none;
        padding: 2rem;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .catalog-section {
        margin-bottom: 2.5rem;
    }
    
    .catalog-section:last-child {
        margin-bottom: 0;
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #E5E7EB;
    }
    
    .section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1E2A3B;
        display: flex;
        align-items: center;
        gap: 0.625rem;
    }
    
    .section-title i {
        color: #1E2A3B;
    }
    
    .items-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1rem;
    }
    
    .catalog-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        background: #F9FAFB;
        border-radius: 8px;
        border: 1px solid #E5E7EB;
        transition: all 0.3s ease;
    }
    
    .catalog-item:hover {
        background: #F3F4F6;
        border-color: #D1D5DB;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }
    
    .item-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
    }
    
    .item-name {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #1E2A3B;
    }
    
    .item-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }
    
    .badge {
        padding: 0.375rem 0.75rem;
        border-radius: 20px;
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .badge-active {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .badge-inactive {
        background-color: #fee2e2;
        color: #991b1b;
    }
    
    .empty-state {
        text-align: center;
        padding: 3.75rem 1.25rem;
        color: #6B7280;
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
        color: #9CA3AF;
    }
    
    .empty-state p {
        font-size: 1rem;
        margin-bottom: 1.25rem;
        color: #6B7280;
    }
    
    .count-badge {
        background: #1E2A3B;
        color: #FFFFFF;
        padding: 0.125rem 0.5rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-left: 0.5rem;
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        border: none;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
    }
    
    .btn-primary {
        background-color: #1E2A3B;
        color: #FFFFFF;
    }
    
    .btn-primary:hover {
        background-color: #000000;
    }
    
    .btn-secondary {
        background-color: #414D5C;
        color: #FFFFFF;
    }
    
    .btn-secondary:hover {
        background-color: #1E2A3B;
    }
    
    .btn-info {
        background-color: #3b82f6;
        color: #FFFFFF;
    }
    
    .btn-info:hover {
        background-color: #2563eb;
    }
    
    .btn-danger {
        background-color: #ef4444;
        color: #FFFFFF;
    }
    
    .btn-danger:hover {
        background-color: #dc2626;
    }
    
    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    .form-control {
        border: 2px solid #D1D5DB;
        border-radius: 8px;
        padding: 0.875rem 1.25rem;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .form-control:focus {
        border-color: #1E2A3B;
        box-shadow: 0 0 0 3px rgba(30, 42, 59, 0.1);
        outline: none;
    }
    
    .form-label {
        color: #1E2A3B;
        font-weight: 600;
        font-size: 0.9375rem;
        margin-bottom: 0.5rem;
        display: block;
    }
    
    .form-group {
        margin-bottom: 1.25rem;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .config-header {
            padding: 1rem 1.5rem;
        }
        
        .tabs-header {
            flex-direction: column;
        }
        
        .tab-button {
            border-bottom: 1px solid #E5E7EB;
        }
        
        .tab-button.active::after {
            height: 0;
            border-left: 3px solid #1E2A3B;
            top: 0;
            bottom: 0;
            left: 0;
            right: auto;
        }
        
        .items-grid {
            grid-template-columns: 1fr;
        }
        
        .tab-content {
            padding: 1.25rem;
        }
        
        .section-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .section-header .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="container">
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="config-header">
        <h1>
            <i class="fas fa-cog"></i> Configuración del Sistema
        </h1>
    </div>

    <!-- Sistema de Pestañas -->
    <div class="tabs-container">
        <div class="tabs-header">
            <?php foreach ($catalogos_config as $index => $config): ?>
                <button class="tab-button <?php echo $index === 0 ? 'active' : ''; ?>" 
                        onclick="cambiarTab('tab-<?php echo $config['modulo']; ?>')"
                        data-tab="tab-<?php echo $config['modulo']; ?>">
                        <i class="fas <?php echo $config['icono']; ?>"></i>
                    <?php echo $config['titulo']; ?>
                        <?php
                    $total_activos = 0;
                    foreach ($config['campos'] as $campo_info) {
                        $campo = $campo_info['campo'];
                        $count_query = "SELECT COUNT(*) as total FROM catalogos WHERE modulo='{$config['modulo']}' AND campo='$campo' AND activo=1";
                        if ($has_empresa_column && $user_empresa_id !== null) {
                            $count_query .= " AND id_empresa = " . intval($user_empresa_id);
                        }
                        $count = $conn->query($count_query)->fetch_assoc()['total'];
                        $total_activos += $count;
                    }
                    ?>
                    <span class="count-badge"><?php echo $total_activos; ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Contenido de las pestañas -->
        <?php foreach ($catalogos_config as $index => $config): ?>
            <div id="tab-<?php echo $config['modulo']; ?>" class="tab-content <?php echo $index === 0 ? 'active' : ''; ?>">
                <?php foreach ($config['campos'] as $campo_info): ?>
                    <div class="catalog-section">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-list-ul"></i>
                                <?php echo $campo_info['nombre']; ?>
                                <?php
                                $campo = $campo_info['campo'];
                                $count_query = "SELECT COUNT(*) as total FROM catalogos WHERE modulo='{$config['modulo']}' AND campo='$campo' AND activo=1";
                                if ($has_empresa_column && $user_empresa_id !== null) {
                                    $count_query .= " AND id_empresa = " . intval($user_empresa_id);
                                }
                                $count = $conn->query($count_query)->fetch_assoc()['total'];
                                ?>
                                <span style="color: #6B7280; font-size: 14px; font-weight: normal;">(<?php echo $count; ?> activos)</span>
                            </div>
                            <button onclick="abrirFormularioAgregar('<?php echo $config['modulo']; ?>', '<?php echo $campo; ?>', '<?php echo $campo_info['nombre']; ?>')" 
                                    class="btn btn-primary">
                                <i class="fas fa-plus"></i> Agregar
                            </button>
                        </div>
                        
                        <div class="items-grid" id="items-<?php echo $config['modulo']; ?>-<?php echo $campo; ?>">
                            <?php
                            $sql = "SELECT * FROM catalogos WHERE modulo='{$config['modulo']}' AND campo='$campo'";
                            if ($has_empresa_column && $user_empresa_id !== null) {
                                $sql .= " AND id_empresa = " . intval($user_empresa_id);
                            }
                            $sql .= " ORDER BY orden ASC, valor ASC";
                            $items = $conn->query($sql);
                            
                            if ($items->num_rows > 0):
                                while ($item = $items->fetch_assoc()):
                            ?>
                                <div class="catalog-item">
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($item['valor']); ?></div>
                                    </div>
                                    <div class="item-actions">
                                        <span class="badge badge-<?php echo $item['activo'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $item['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                        <button onclick='editarValor(<?php echo $item['id_catalogo']; ?>, "<?php echo $item['modulo']; ?>", "<?php echo $item['campo']; ?>", "<?php echo htmlspecialchars($item['valor']); ?>", <?php echo $item['orden']; ?>, <?php echo $item['activo']; ?>)' 
                                                class="btn btn-sm btn-info" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="confirmarEliminar(<?php echo $item['id_catalogo']; ?>)" 
                                                class="btn btn-sm btn-danger" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php 
                                endwhile;
                            else:
                            ?>
                                <div class="empty-state" style="grid-column: 1 / -1;">
                                    <i class="fas fa-inbox"></i>
                                    <p>No hay valores registrados</p>
                                    <button onclick="abrirFormularioAgregar('<?php echo $config['modulo']; ?>', '<?php echo $campo; ?>', '<?php echo $campo_info['nombre']; ?>')" 
                                            class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Agregar el primero
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        </div>
                    <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Agregar/Editar Valor -->
<div class="modal-overlay" id="modal-valor">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="modal-valor-title"><i class="fas fa-plus"></i> Agregar Valor</h3>
            <button class="modal-close" onclick="closeModal('modal-valor')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" id="form_action" value="create">
            <input type="hidden" name="id_catalogo" id="form_id_catalogo">
            <input type="hidden" name="modulo" id="form_modulo">
            <input type="hidden" name="campo" id="form_campo">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="valor" id="form_valor" class="form-control" required>
                </div>
                
                <div class="form-group" style="display: none;">
                    <label class="form-label">Orden</label>
                    <input type="number" name="orden" id="form_orden" class="form-control" value="0" min="0">
                </div>
                
                <div class="form-group" id="activo-group" style="display: none;">
                    <label class="form-label">Estado</label>
                    <select name="activo" id="form_activo" class="form-control">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-valor')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Form para eliminar -->
<form id="form-eliminar" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id_catalogo" id="delete_id_catalogo">
</form>

<script src="../../js/main.js"></script>
<script>
// Sistema de pestañas
function cambiarTab(tabId) {
    // Ocultar todos los contenidos
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Desactivar todos los botones
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Activar el contenido seleccionado
    document.getElementById(tabId).classList.add('active');
    
    // Activar el botón seleccionado
    document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
}

function abrirFormularioAgregar(modulo, campo, nombre) {
    document.getElementById('modal-valor-title').innerHTML = `<i class="fas fa-plus"></i> Agregar ${nombre}`;
    document.getElementById('form_action').value = 'create';
    document.getElementById('form_modulo').value = modulo;
    document.getElementById('form_campo').value = campo;
    document.getElementById('form_valor').value = '';
    document.getElementById('form_orden').value = '0';
    document.getElementById('activo-group').style.display = 'none';
    
    openModal('modal-valor');
}

function editarValor(id, modulo, campo, valor, orden, activo) {
    document.getElementById('modal-valor-title').innerHTML = '<i class="fas fa-edit"></i> Editar Valor';
    document.getElementById('form_action').value = 'update';
    document.getElementById('form_id_catalogo').value = id;
    document.getElementById('form_modulo').value = modulo;
    document.getElementById('form_campo').value = campo;
    document.getElementById('form_valor').value = valor;
    document.getElementById('form_orden').value = orden;
    document.getElementById('form_activo').value = activo;
    document.getElementById('activo-group').style.display = 'block';
    
    openModal('modal-valor');
}

function confirmarEliminar(id) {
    if (confirm('¿Está seguro de que desea eliminar este valor? Esta acción no se puede deshacer.')) {
        document.getElementById('delete_id_catalogo').value = id;
        document.getElementById('form-eliminar').submit();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>

