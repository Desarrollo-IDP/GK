<?php
require_once '../../includes/db.php';
require_once '../../../auth.php';
require_once '../../../includes/empresa_helper.php';
checkAuth();
$pageTitle = 'Generar Carta de Entrega';
require_once '../../includes/header.php';

// Obtener empresa del usuario
$user_empresa_id = getCurrentUserEmpresaId();

// Verificar si existe columna id_empresa en productos_almacen
$has_empresa_column = false;
$check_column = $conn->query("SHOW COLUMNS FROM productos_almacen LIKE 'id_empresa'");
if ($check_column && $check_column->num_rows > 0) {
    $has_empresa_column = true;
}

// Obtener productos disponibles
$selectedCategory = $_GET['categoria'] ?? '';
$selectedAlmacen = $_GET['almacen'] ?? '';

$productosQuery = "SELECT p.*, a.nombre as almacen_nombre, a.codigo as almacen_codigo, b.nombre as bodega_nombre
                   FROM productos_almacen p 
                   LEFT JOIN almacenes a ON p.id_almacen = a.id_almacen
                   LEFT JOIN bodegas b ON a.id_bodega = b.id_bodega
                   WHERE p.estado_producto = 'Disponible' AND p.cantidad > 0";

// Aplicar filtro de empresa si existe la columna y el usuario tiene empresa asignada
if ($has_empresa_column && $user_empresa_id !== null) {
    $productosQuery .= " AND p.id_empresa = " . intval($user_empresa_id);
}

if (!empty($selectedCategory)) {
    $productosQuery .= " AND p.categoria = '" . $conn->real_escape_string($selectedCategory) . "'";
}
if (!empty($selectedAlmacen)) {
    $productosQuery .= " AND p.id_almacen = " . intval($selectedAlmacen);
}

$productosQuery .= " ORDER BY p.nombre_producto";
$productosResult = $conn->query($productosQuery);

// Obtener categorías
$categorias_query = "SELECT DISTINCT categoria FROM productos_almacen WHERE categoria IS NOT NULL AND categoria != ''";
if ($has_empresa_column && $user_empresa_id !== null) {
    $categorias_query .= " AND id_empresa = " . intval($user_empresa_id);
}
$categorias_query .= " ORDER BY categoria";
$categoriasResult = $conn->query($categorias_query);

// Obtener almacenes
$almacenes_query = "SELECT a.*, b.nombre as bodega_nombre FROM almacenes a LEFT JOIN bodegas b ON a.id_bodega = b.id_bodega";
// Verificar si almacenes tiene columna id_empresa
$check_almacen_column = $conn->query("SHOW COLUMNS FROM almacenes LIKE 'id_empresa'");
if ($check_almacen_column && $check_almacen_column->num_rows > 0 && $user_empresa_id !== null) {
    $almacenes_query .= " WHERE a.id_empresa = " . intval($user_empresa_id);
}
$almacenes_query .= " ORDER BY a.nombre";
$almacenesResult = $conn->query($almacenes_query);
?>

<style>
    /* Estilos mejorados para generar-carta.php */
    .generar-carta-header {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 1.5rem 2rem;
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 4px solid #1E2A3B;
    }
    
    .generar-carta-header h1 {
        color: #1E2A3B;
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .generar-carta-header h1 i {
        color: #1E2A3B;
        font-size: 1.5rem;
    }
    
    .generar-carta-header .btn-secondary {
        background-color: #414D5C;
        color: #FFFFFF;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .generar-carta-header .btn-secondary:hover {
        background-color: #1E2A3B;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }
    
    .filters-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        border: 1px solid #E5E7EB;
    }
    
    .filters-card .form-control {
        border: 2px solid #D1D5DB;
        border-radius: 8px;
        padding: 0.875rem 1.25rem;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .filters-card .form-control:focus {
        border-color: #1E2A3B;
        box-shadow: 0 0 0 3px rgba(30, 42, 59, 0.1);
        outline: none;
    }
    
    .filters-card .btn {
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
    
    .filters-card .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
    }
    
    .filters-card .btn-secondary {
        background-color: #414D5C;
        color: #FFFFFF;
    }
    
    .filters-card .btn-secondary:hover {
        background-color: #1E2A3B;
    }
    
    .form-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 1.5rem;
        border: 1px solid #E5E7EB;
        overflow: hidden;
    }
    
    .form-card-header {
        background-color: #1E2A3B;
        color: #FFFFFF;
        padding: 1.25rem 1.5rem;
        border-bottom: 2px solid #000000;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .form-card-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .form-card-header h3 i {
        font-size: 1.1rem;
    }
    
    .form-card-body {
        padding: 2rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        color: #1E2A3B;
        font-weight: 600;
        font-size: 0.9375rem;
        margin-bottom: 0.5rem;
        display: block;
    }
    
    .form-control {
        border: 2px solid #D1D5DB;
        border-radius: 8px;
        padding: 0.875rem 1rem;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .form-control:focus {
        border-color: #1E2A3B;
        box-shadow: 0 0 0 3px rgba(30, 42, 59, 0.1);
        outline: none;
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 100px;
    }
    
    .table-container {
        background: #FFFFFF;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #E5E7EB;
    }
    
    .table-container table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .table-container table thead {
        background-color: #1E2A3B;
    }
    
    .table-container table thead th {
        padding: 1rem 1.25rem;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #FFFFFF;
        border-bottom: 2px solid #000000;
        text-align: left;
    }
    
    .table-container table tbody tr {
        border-bottom: 1px solid #E5E7EB;
        transition: background-color 0.2s ease;
    }
    
    .table-container table tbody tr:hover {
        background-color: #F9FAFB;
    }
    
    .table-container table tbody tr:last-child {
        border-bottom: none;
    }
    
    .table-container table tbody td {
        padding: 1rem 1.25rem;
        font-size: 0.9375rem;
        color: #1E2A3B;
    }
    
    .table-container table tbody td strong {
        color: #1E2A3B;
        font-weight: 600;
    }
    
    .table-container table tbody td code {
        background: #F3F4F6;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.875rem;
        color: #1E2A3B;
        font-weight: 600;
    }
    
    .badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8125rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .badge-info {
        background-color: #dbeafe;
        color: #1e40af;
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
    
    .btn-success {
        background-color: #10b981;
        color: #FFFFFF;
    }
    
    .btn-success:hover {
        background-color: #059669;
    }
    
    .btn-secondary {
        background-color: #414D5C;
        color: #FFFFFF;
    }
    
    .btn-secondary:hover {
        background-color: #1E2A3B;
    }
    
    .btn-primary {
        background-color: #1E2A3B;
        color: #FFFFFF;
    }
    
    .btn-primary:hover {
        background-color: #000000;
    }
    
    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    .btn-lg {
        padding: 1rem 2rem;
        font-size: 1rem;
    }
    
    .alert {
        border-radius: 8px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        border-left: 4px solid;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .alert-success {
        background-color: #d1fae5;
        border-color: #065f46;
        color: #065f46;
    }
    
    .alert-danger {
        background-color: #fee2e2;
        border-color: #991b1b;
        color: #991b1b;
    }
    
    .cantidad-input {
        width: 100px;
        text-align: center;
    }
    
    .cantidad-input:disabled {
        background-color: #f3f4f6;
        cursor: not-allowed;
        opacity: 0.6;
    }
    
    .producto-checkbox {
        width: 20px;
        height: 20px;
        cursor: pointer;
        accent-color: #1E2A3B;
    }
    
    .producto-checkbox:checked {
        accent-color: #1E2A3B;
    }
    
    @media (max-width: 768px) {
        .generar-carta-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        
        .generar-carta-header .btn-secondary {
            width: 100%;
            justify-content: center;
        }
        
        .filters-card > div {
            grid-template-columns: 1fr !important;
        }
        
        .form-card-body > div {
            grid-template-columns: 1fr !important;
        }
        
        .form-card-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
    }
</style>

<div class="container">
    <div class="generar-carta-header">
        <h1>
            <i class="fas fa-file-export"></i> Generar Carta de Entrega
        </h1>
        <a href="./cartas-entrega.php" class="btn-secondary">
            <i class="fas fa-list"></i> Ver Cartas Generadas
        </a>
    </div>

    <!-- Filtros -->
    <form method="get" class="filters-card">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
            <div class="form-group mb-0">
                <label class="form-label">Filtrar por Categoría</label>
                <select name="categoria" class="form-control" onchange="this.form.submit()">
                    <option value="">Todas las categorías</option>
                    <?php while ($cat = $categoriasResult->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($cat['categoria']); ?>"
                                <?php echo $selectedCategory === $cat['categoria'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['categoria']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label">Filtrar por Almacén</label>
                <select name="almacen" class="form-control" onchange="this.form.submit()">
                    <option value="">Todos los almacenes</option>
                    <?php while ($alm = $almacenesResult->fetch_assoc()): ?>
                        <option value="<?php echo $alm['id_almacen']; ?>"
                                <?php echo $selectedAlmacen == $alm['id_almacen'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($alm['nombre']); ?> (<?php echo htmlspecialchars($alm['bodega_nombre']); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label">&nbsp;</label>
                <?php if ($selectedCategory || $selectedAlmacen): ?>
                    <a href="./generar-carta.php" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                        <i class="fas fa-times"></i> Limpiar Filtros
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <!-- Mensaje de error si existe -->
    <?php if (isset($_SESSION['error_carta'])): ?>
        <div class="alert alert-danger">
            <strong><i class="fas fa-exclamation-triangle"></i> Error:</strong>
            <div><?php echo $_SESSION['error_carta']; ?></div>
        </div>
        <?php unset($_SESSION['error_carta']); ?>
    <?php endif; ?>
    
    <!-- Mensaje de éxito si existe -->
    <?php if (isset($_SESSION['success_carta'])): ?>
        <div class="alert alert-success" id="successAlert">
            <strong><i class="fas fa-check-circle"></i> Éxito:</strong>
            <?php echo $_SESSION['success_carta']; ?>
            <?php if (isset($_SESSION['carta_generada'])): ?>
                <br><br>
                <a href="descargar-pdf-carta.php" target="_blank" class="btn btn-primary" id="pdfLink">
                    <i class="fas fa-file-pdf"></i> Abrir PDF de la Carta
                </a>
            <?php endif; ?>
        </div>
        <?php unset($_SESSION['success_carta']); ?>
    <?php endif; ?>

    <!-- Formulario de Carta de Entrega -->
    <form method="post" action="./procesar-carta.php" id="formCartaEntrega">
        <input type="hidden" name="action" value="generar_carta">
        
        <!-- Información del Receptor -->
        <div class="form-card">
            <div class="form-card-header">
                <h3><i class="fas fa-user"></i> Información del Receptor</h3>
            </div>
            <div class="form-card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Nombre Completo del Receptor *</label>
                        <input type="text" name="nombre_receptor" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">RFC *</label>
                        <input type="text" name="identificacion_receptor" class="form-control" required>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Empresa/Organización *</label>
                        <input type="text" name="empresa_receptor" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Área/Departamento</label>
                        <input type="text" name="area_receptor" class="form-control">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Fecha de Entrega *</label>
                        <input type="date" name="fecha_entrega" id="fecha_entrega" class="form-control" value="<?php echo htmlspecialchars(fechaHoyAlmacen()); ?>" required>
                        <small class="text-muted">Hoy (<?php echo htmlspecialchars(ALMACEN_TIMEZONE); ?>): <?php echo htmlspecialchars(fechaHoraActualAlmacen()); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Entrega *</label>
                        <select name="tipo_entrega" class="form-control" required>
                            <option value="Definitiva">Definitiva</option>
                            <option value="Temporal">Temporal</option>
                            <option value="Préstamo">Préstamo</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Fecha de Devolución (si aplica)</label>
                        <input type="date" name="fecha_devolucion" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="3"></textarea>
                </div>
            </div>
        </div>

        <!-- Selección de Productos -->
        <div class="form-card">
            <div class="form-card-header">
                <h3><i class="fas fa-box-open"></i> Selección de Productos</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" id="selectAll" class="btn btn-sm btn-secondary">
                        Seleccionar Todos
                    </button>
                    <button type="button" id="deselectAll" class="btn btn-sm btn-secondary">
                        Deseleccionar Todos
                    </button>
                </div>
            </div>
            
            <div class="form-card-body" style="padding: 0;">
                <div class="table-container">
                <table id="table-productos">
                    <thead>
                        <tr>
                            <th style="width: 50px;">
                                <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300">
                            </th>
                            <th>Código</th>
                            <th>Producto</th>
                            <th class="hide-mobile">Categoría</th>
                            <th class="hide-mobile">Almacén</th>
                            <th>Disponibles</th>
                            <th>Cantidad a Entregar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($productosResult->num_rows > 0): ?>
                            <?php while ($producto = $productosResult->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" 
                                           name="productos_seleccionados[]" 
                                           value="<?php echo $producto['id_producto']; ?>" 
                                           class="producto-checkbox"
                                           data-max="<?php echo $producto['cantidad']; ?>"
                                           onchange="toggleCantidad(this)">
                                </td>
                                <td><code><?php echo htmlspecialchars($producto['codigo_producto']); ?></code></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($producto['nombre_producto']); ?></strong>
                                    <?php if ($producto['marca']): ?>
                                        <br><small><?php echo htmlspecialchars($producto['marca']); ?> <?php echo htmlspecialchars($producto['modelo'] ?? ''); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="hide-mobile"><?php echo htmlspecialchars($producto['categoria'] ?? 'N/A'); ?></td>
                                <td class="hide-mobile">
                                    <small><?php echo htmlspecialchars($producto['almacen_codigo']); ?></small><br>
                                    <?php echo htmlspecialchars($producto['almacen_nombre']); ?>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo $producto['cantidad']; ?> <?php echo $producto['unidad']; ?>
                                    </span>
                                </td>
                                <td>
                                    <input type="number" 
                                           name="cantidades[<?php echo $producto['id_producto']; ?>]" 
                                           id="cantidad_<?php echo $producto['id_producto']; ?>"
                                           class="form-control cantidad-input" 
                                           min="1" 
                                           max="<?php echo $producto['cantidad']; ?>"
                                           value="1"
                                           disabled
                                           style="width: 100px;">
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No hay productos disponibles</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="form-card">
            <div class="form-card-body" style="text-align: center; padding: 2rem;">
                <button type="submit" class="btn btn-lg btn-success">
                    <i class="fas fa-file-pdf"></i> Generar Carta de Entrega PDF
                </button>
                <a href="./cartas-entrega.php" class="btn btn-lg btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </div>
    </form>
</div>

<script src="../../js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // La fecha de entrega la define el servidor (fechaHoyAlmacen); no sobrescribir con la del navegador

    const selectAllBtn = document.getElementById('selectAll');
    const deselectAllBtn = document.getElementById('deselectAll');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.producto-checkbox');
    
    // Seleccionar todos
    selectAllBtn.addEventListener('click', function() {
        checkboxes.forEach(cb => {
            cb.checked = true;
            toggleCantidad(cb);
        });
        selectAllCheckbox.checked = true;
    });
    
    // Deseleccionar todos
    deselectAllBtn.addEventListener('click', function() {
        checkboxes.forEach(cb => {
            cb.checked = false;
            toggleCantidad(cb);
        });
        selectAllCheckbox.checked = false;
    });
    
    // Checkbox maestro
    selectAllCheckbox.addEventListener('change', function() {
        checkboxes.forEach(cb => {
            cb.checked = this.checked;
            toggleCantidad(cb);
        });
    });
    
    // Validar formulario
    document.getElementById('formCartaEntrega').addEventListener('submit', function(e) {
        const checked = document.querySelectorAll('.producto-checkbox:checked');
        if (checked.length === 0) {
            e.preventDefault();
            alert('Debe seleccionar al menos un producto');
            return false;
        }
        
        // Validar cantidades disponibles ANTES de enviar
        let valid = true;
        let errores = [];
        
        checked.forEach(cb => {
            const id = cb.value;
            const input = document.getElementById('cantidad_' + id);
            const cantidad = parseInt(input.value) || 0;
            const max = parseInt(input.max) || 0;
            const productoNombre = cb.closest('tr').querySelector('td:nth-child(3) strong').textContent;
            
            if (cantidad < 1) {
                valid = false;
                errores.push(`La cantidad para "${productoNombre}" debe ser mayor a 0`);
            } else if (cantidad > max) {
                valid = false;
                errores.push(`La cantidad para "${productoNombre}" no puede ser mayor a ${max} (cantidad disponible)`);
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Errores de validación:\n\n' + errores.join('\n'));
            return false;
        }
        
        // Mostrar mensaje de carga
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando PDF...';
        
        // El formulario se enviará normalmente
        // La página se recargará después de que se procese la carta
    });
});

function toggleCantidad(checkbox) {
    const id = checkbox.value;
    const input = document.getElementById('cantidad_' + id);
    
    if (checkbox.checked) {
        input.disabled = false;
        input.required = true;
        input.classList.remove('bg-gray-100');
    } else {
        input.disabled = true;
        input.required = false;
        input.classList.add('bg-gray-100');
    }
}
</script>


<?php require_once '../../includes/footer.php'; ?>

<?php if (isset($_GET['generar_pdf']) && isset($_SESSION['carta_generada'])): ?>
<script>
// Esperar a que todo esté cargado antes de abrir el PDF
window.addEventListener('load', function() {
    // Pequeño delay para asegurar que todo esté listo
    setTimeout(function() {
        try {
            // Intentar abrir el PDF
            const pdfWindow = window.open('descargar-pdf-carta.php', '_blank', 'noopener,noreferrer');
            
            // Limpiar el parámetro de la URL
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('generar_pdf');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        } catch (e) {
            console.error('Error al abrir PDF:', e);
        }
    }, 300);
});

// Ocultar el mensaje de éxito después de 3 minutos
setTimeout(function() {
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        // Agregar animación de desvanecimiento
        successAlert.style.transition = 'opacity 0.5s ease-out';
        successAlert.style.opacity = '0';
        
        // Ocultar completamente después de la animación
        setTimeout(function() {
            successAlert.style.display = 'none';
        }, 500);
    }
}, 180000); // 3 minutos (180000 milisegundos)

// Limpiar la sesión después de 5 minutos para evitar que se acumule
setTimeout(function() {
    // Hacer una petición para limpiar la sesión después de un tiempo
    fetch('descargar-pdf-carta.php?limpiar_sesion=1', {method: 'GET'})
        .catch(function() {
            // Ignorar errores
        });
}, 300000); // 5 minutos
</script>
<?php endif; ?>

