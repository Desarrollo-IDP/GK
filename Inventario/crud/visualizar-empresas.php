<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();
$isAdmin = isAdmin();

$pageTitle = 'Visualizar Empresas';
require_once 'includes/header.php';

// Verificar si existe columna id_empresa_usuario en empresas_equipos
$check_column = $conn->query("SHOW COLUMNS FROM empresas_equipos LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_column && $check_column->num_rows > 0;

$search = trim($_GET['search'] ?? '');

$query = "SELECT e.*, c.contacto, c.telefono, c.correo 
          FROM empresas_equipos e 
          " . contactosEmpresaLeftJoinSql($conn, CONTACTO_ORIGEN_EMPRESAS_EQUIPOS);
$params = [];
$types = '';

if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $query .= " WHERE e.id_empresa_usuario = ?";
    $params[] = $user_empresa_id;
    $types .= 'i';
}

if ($search !== '') {
    $like = '%' . $search . '%';
    $query .= $types ? " AND " : " WHERE ";
    $query .= "(e.nombre LIKE ? OR e.direccion LIKE ? OR c.contacto LIKE ? OR c.telefono LIKE ? OR c.correo LIKE ?)";
    $params = array_merge($params, array_fill(0, 5, $like));
    $types .= str_repeat('s', 5);
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
?>

<div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
    <div class="inicio-header mb-6">
        <div class="flex items-center gap-3 mb-1">
            <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg">
                <i class="fas fa-building text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Listado de empresas</h1>
                <p class="inicio-header-subtitle">Empresas registradas en el inventario</p>
            </div>
        </div>
        
    </div>

    <div class="empresa-filters-card mb-5">
        <form method="GET" class="empresa-filters-form">
            <div class="empresa-filters-left">
                <div class="empresa-filters-input-wrap">
                    <i class="fas fa-search text-gray-400 text-sm"></i>
                    <input
                        type="text"
                        name="search"
                        value="<?php echo htmlspecialchars($search ?? ''); ?>"
                        placeholder="Buscar por nombre, dirección o contacto..."
                        class="empresa-filters-input"
                    >
                </div>
                <button type="submit" class="empresa-filters-btn-search">
                    <i class="fas fa-search"></i>
                    <span>Buscar</span>
                </button>
            </div>
            <?php if ($isAdmin): ?>
            <a href="agregar-empresa.php" class="empresa-filters-btn-add">
                <i class="fas fa-plus"></i>
                <span>Nueva empresa</span>
            </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="inventory-table-wrap bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="table-container overflow-x-auto">
            <table class="inventory-table min-w-full">
                <thead>
                    <tr>
                        <th class="inv-th">Nombre</th>
                        <th class="inv-th">Dirección</th>
                        <th class="inv-th">Fecha registro</th>
                        <th class="inv-th">Contactos</th>
                        <?php if ($isAdmin): ?>
                        <th class="inv-th inv-th-actions">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowIndex = 0; while ($empresa = $result->fetch_assoc()): $rowIndex++; ?>
                    <tr class="inv-tr <?php echo $rowIndex % 2 === 0 ? 'inv-tr-even' : ''; ?>">
                        <td class="inv-td font-medium text-gray-900"><?php echo htmlspecialchars($empresa['nombre']); ?></td>
                        <td class="inv-td text-gray-700"><?php echo htmlspecialchars($empresa['direccion']); ?></td>
                        <td class="inv-td text-gray-600 text-sm whitespace-nowrap">
                            <?php echo date('d/m/Y', strtotime($empresa['fecha_registro'])); ?>
                        </td>
                        <td class="inv-td text-sm">
                            <?php if ($empresa['contacto']): ?>
                                <div class="space-y-0.5">
                                    <div><span class="font-semibold text-gray-700">Contacto:</span> <?php echo htmlspecialchars($empresa['contacto']); ?></div>
                                    <div><span class="font-semibold text-gray-700">Tel:</span> <?php echo htmlspecialchars($empresa['telefono']); ?></div>
                                    <div><span class="font-semibold text-gray-700">Email:</span> <?php echo htmlspecialchars($empresa['correo']); ?></div>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-400 italic">Sin contactos registrados</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td class="inv-td inv-td-actions">
                            <a href="editar-empresa.php?id=<?php echo $empresa['id_empresa']; ?>" 
                               class="inv-btn inv-btn-edit"><i class="fas fa-pen mr-1 text-[11px]"></i> Editar</a>
                            <button type="button" onclick="deleteEmpresa(<?php echo $empresa['id_empresa']; ?>)" 
                                    class="inv-btn inv-btn-delete"><i class="fas fa-trash-alt mr-1 text-[11px]"></i> Eliminar</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function deleteEmpresa(id) {
    showConfirm('¿Está seguro de que desea eliminar esta empresa?', function() {
        fetch('api/delete-empresa.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showAlert(data.error || 'Error al eliminar la empresa', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error al eliminar la empresa', 'error');
        });
    });
}
</script>

<style>
.inv-page-wrap { max-width: 1600px; }
.inv-brand-bg { background-color: #1E2A3B; }
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
.empresa-filters-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.08);
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-start;
    gap: 0.75rem 1.5rem;
}
.empresa-filters-form {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
}
.empresa-filters-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1 1 auto;
}
.empresa-filters-input-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 0.9rem;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background-color: #f9fafb;
    min-width: 420px;
    flex: 1 1 auto;
}
.empresa-filters-input {
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.875rem;
    color: #111827;
    min-width: 0;
}
.empresa-filters-input::placeholder {
    color: #9ca3af;
}
.empresa-filters-btn-search,
.empresa-filters-btn-add {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: background-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
}
.empresa-filters-btn-search {
    background-color: #111827;
    color: #f9fafb;
}
.empresa-filters-btn-search:hover {
    background-color: #020617;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.35);
    transform: translateY(-1px);
}
.empresa-filters-btn-add {
    background-color: #ffffff;
    color: #111827;
    border: 1px solid #e5e7eb;
    margin-left: auto;
}
.empresa-filters-btn-add:hover {
    background-color: #f3f4f6;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.15);
    transform: translateY(-1px);
}
@media (max-width: 768px) {
    .empresa-filters-card {
        align-items: stretch;
        padding: 1rem;
    }
    .empresa-filters-form {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    .empresa-filters-left {
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        gap: 0.5rem;
    }
    .empresa-filters-input-wrap {
        min-width: 0;
        width: 100%;
        box-sizing: border-box;
    }
    .empresa-filters-btn-search {
        width: 100%;
        justify-content: center;
    }
    .empresa-filters-btn-add {
        margin-left: 0;
        width: 100%;
        justify-content: center;
    }
}
.inventory-table-wrap { min-height: 200px; }
.table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    width: 100%;
}
.inventory-table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    font-size: 0.875rem;
}
.inventory-table thead { position: sticky; top: 0; z-index: 5; }
.inv-th {
    background: #1E2A3B;
    color: #fff;
    font-weight: 600;
    text-align: left;
    padding: 0.75rem 1rem;
    white-space: nowrap;
    border-bottom: 2px solid #2d3a50;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.inv-th:not(:last-child) { border-right: 1px solid rgba(255,255,255,0.08); }
.inv-th-actions { text-align: center; min-width: 150px; }
.inv-tr { transition: background-color 0.12s ease; }
.inv-tr:hover { background-color: #f8fafc; }
.inv-tr-even { background-color: #fafbfc; }
.inv-tr-even:hover { background-color: #f1f5f9; }
.inv-td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    color: #334155;
    vertical-align: middle;
}
.inv-td-actions {
    white-space: nowrap;
    text-align: right;
}
.inv-td-actions .inv-btn {
    margin-right: 0.3rem;
    width: 95px;
    min-width: 95px;
    max-width: 95px;
    box-sizing: border-box;
}
.inv-td-actions .inv-btn:last-child {
    margin-right: 0;
}
.inv-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    background-color: #ffffff;
    text-decoration: none;
    transition: color 0.15s ease, border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    flex-shrink: 0;
}
.inv-btn-edit {
    color: #1d4ed8;
    border-color: #bfdbfe;
}
.inv-btn-edit:hover {
    background-color: #eff6ff;
    border-color: #60a5fa;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
}
.inv-btn-delete {
    color: #b91c1c;
    border-color: #fecaca;
}
.inv-btn-delete:hover {
    background-color: #fef2f2;
    border-color: #f97373;
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.2);
}
@media (max-width: 768px) {
    .inventory-table { min-width: 800px; }
    .inv-th, .inv-td { padding: 0.5rem 0.65rem; font-size: 0.75rem; }
    .inv-th-actions {
        min-width: 200px;
        text-align: center;
    }
    .inv-td-actions {
        min-width: 200px;
        text-align: center;
        white-space: nowrap;
        vertical-align: middle;
    }
    .inv-td-actions .inv-btn {
        margin-right: 0.35rem;
        width: 95px;
        min-width: 95px;
        max-width: 95px;
        box-sizing: border-box;
    }
    .inv-td-actions .inv-btn:last-child {
        margin-right: 0;
    }
    .inv-td-actions {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>