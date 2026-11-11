<?php
require_once 'includes/db.php';
require_once '../../auth.php';
require_once '../../includes/empresa_helper.php';
checkAuth();

$user_empresa_id = getCurrentUserEmpresaId();

$pageTitle = 'Carta Mail - Cumplimiento Legal';
require_once 'includes/header.php';

// Verificar si existe columna id_empresa_usuario en requesters
$check_column = $conn->query("SHOW COLUMNS FROM requesters LIKE 'id_empresa'");
$has_empresa_column = $check_column && $check_column->num_rows > 0;

// Verificar si existe columna id_empresa_usuario en empresas
$check_empresa_usuario_column = $conn->query("SHOW COLUMNS FROM empresas LIKE 'id_empresa_usuario'");
$has_empresa_usuario_column = $check_empresa_usuario_column && $check_empresa_usuario_column->num_rows > 0;

// Catálogo de empresas (para el campo Empresa del solicitante) - filtrado por id_empresa del usuario
$companies_query = "SELECT id_empresa, nombre FROM empresas";
$companies_params = [];
$companies_types = '';

if ($has_empresa_usuario_column && $user_empresa_id !== null) {
    $companies_query .= " WHERE id_empresa_usuario = ?";
    $companies_params[] = $user_empresa_id;
    $companies_types = 'i';
}

$companies_query .= " ORDER BY nombre";

if (!empty($companies_params)) {
    $companies_stmt = $conn->prepare($companies_query);
    $companies_stmt->bind_param($companies_types, ...$companies_params);
    $companies_stmt->execute();
    $companies = $companies_stmt->get_result();
} else {
    $companies = $conn->query($companies_query);
}

// Filtros de búsqueda para solicitantes
$buscar_texto = $_GET['buscar_texto'] ?? '';
$buscar_empresa = $_GET['buscar_empresa'] ?? '';

// Obtener requesters filtrados por empresa y filtros
$requestersQuery = "SELECT r.*, e.nombre as company_name, eu.nombre as empresa_nombre 
                    FROM requesters r 
                    LEFT JOIN empresas e ON r.company_id = e.id_empresa
                    LEFT JOIN empresas_usuarios eu ON r.id_empresa = eu.id_empresa";

$params = [];
$types = '';
$conditions = [];

if ($has_empresa_column && $user_empresa_id !== null) {
    $conditions[] = "r.id_empresa = ?";
    $params[] = $user_empresa_id;
    $types .= 'i';
}

if ($buscar_texto !== '') {
    $like = '%' . $buscar_texto . '%';
    $conditions[] = "(r.name LIKE ? OR r.email LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($buscar_empresa !== '') {
    $conditions[] = "e.id_empresa = ?";
    $params[] = (int)$buscar_empresa;
    $types .= 'i';
}

if (!empty($conditions)) {
    $requestersQuery .= " WHERE " . implode(' AND ', $conditions);
}

$requestersQuery .= " ORDER BY r.name";

if (!empty($params)) {
    $stmt = $conn->prepare($requestersQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $requestersResult = $stmt->get_result();
} else {
    $requestersResult = $conn->query($requestersQuery);
}

// Obtener cartas generadas
$cartasQuery = "SELECT c.*, r.name as requester_name, r.email as requester_email, r.phone as requester_phone
                FROM cartas_cumplimiento_legal c
                LEFT JOIN requesters r ON c.id_requester = r.id";

$cartas_params = [];
$cartas_types = '';

if ($has_empresa_column && $user_empresa_id !== null) {
    $cartasQuery .= " WHERE c.id_empresa = ?";
    $cartas_params[] = $user_empresa_id;
    $cartas_types = 'i';
}

$cartasQuery .= " ORDER BY c.fecha_creacion DESC";

if (!empty($cartas_params)) {
    $cartas_stmt = $conn->prepare($cartasQuery);
    $cartas_stmt->bind_param($cartas_types, ...$cartas_params);
    $cartas_stmt->execute();
    $cartasResult = $cartas_stmt->get_result();
} else {
    $cartasResult = $conn->query($cartasQuery);
}

// Obtener un array de IDs de requesters que ya tienen carta para deshabilitar el botón
$requesters_con_carta = [];
if ($cartasResult->num_rows > 0) {
    $cartasResult->data_seek(0); // Resetear el puntero
    while ($carta = $cartasResult->fetch_assoc()) {
        $requesters_con_carta[$carta['id_requester']] = true;
    }
    $cartasResult->data_seek(0); // Resetear nuevamente para el while principal
}
?>

<style>
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
        justify-content: space-between;
    }
    .inicio-header-subtitle { margin: 0.25rem 0 0; font-size: 0.9rem; color: #6b7280; }
    /* Modales */
    .modal {
        display: none;
        position: fixed;
        z-index: 1050;
        inset: 0;
        background-color: rgba(15,23,42,0.55);
        padding: 1.5rem;
        overflow-y: auto;
    }
    .modal-content {
        background-color: #ffffff;
        margin: 3rem auto;
        border-radius: 1rem;
        width: 100%;
        max-width: 560px;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.45);
        border: 1px solid #f3f4f6;
    }
    .modal-header {
        background-color: #1E2A3B;
        color: #ffffff;
        padding: 1.25rem 1.5rem;
        border-radius: 1rem 1rem 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modal-header h2 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .close {
        color: #e5e7eb;
        font-size: 1.5rem;
        font-weight: bold;
        cursor: pointer;
        background: none;
        border: none;
        line-height: 1;
        border-radius: 999px;
        padding: 0.15rem 0.4rem;
    }
    .close:hover {
        background-color: rgba(255,255,255,0.12);
        color: #ffffff;
    }
    .modal-body { padding: 1.75rem 1.75rem 1.5rem; }
    @media (max-width: 768px) {
        .inicio-header { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
        .inicio-header h1 { font-size: 1.25rem; }
        .modal-content { margin: 2.5rem auto; }
    }
</style>

<div class="container mx-auto p-4 lg:p-6 inv-page-wrap">
    <div class="inicio-header mb-8">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-12 h-12 rounded-xl inv-brand-bg text-white shadow-lg flex-shrink-0">
                <i class="fas fa-envelope-open-text text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 tracking-tight">Cartas de correo electrónico</h1>
                <p class="inicio-header-subtitle">Gestiona solicitantes y cartas responsivas de uso de correo corporativo.</p>
            </div>
        </div>
        <a href="reporte.php" class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all text-sm">
            <i class="fas fa-arrow-left"></i>
            Volver a reportes
        </a>
    </div>

    <!-- Mensajes de éxito/error -->
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_carta'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
            <strong><i class="fas fa-exclamation-triangle"></i> Error:</strong>
            <div><?php echo $_SESSION['error_carta']; ?></div>
        </div>
        <?php unset($_SESSION['error_carta']); ?>
    <?php endif; ?>
    
    <!-- Mensaje de éxito si existe -->
    <?php if (isset($_SESSION['success_carta'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded" id="successAlert">
            <strong><i class="fas fa-check-circle"></i> Éxito:</strong>
            <?php echo $_SESSION['success_carta']; ?>
            <?php if (isset($_SESSION['carta_cumplimiento_generada'])): ?>
                <br><br>
                <a href="generar-pdf-carta-mail.php" target="_blank" class="bg-black text-white px-4 py-2 rounded hover:bg-gray-600 inline-block" id="pdfLink">
                    <i class="fas fa-file-pdf"></i> Abrir PDF de la Carta
                </a>
            <?php endif; ?>
        </div>
        <?php unset($_SESSION['success_carta']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <!-- Filtros de búsqueda -->
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-5 lg:p-6 mb-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="fas fa-user w-4 mr-1.5 text-gray-400"></i> Nombre o email
                    </label>
                    <input
                        type="text"
                        name="buscar_texto"
                        value="<?php echo htmlspecialchars($buscar_texto); ?>"
                        placeholder="Buscar solicitante por nombre o correo..."
                        class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="fas fa-building w-4 mr-1.5 text-gray-400"></i> Empresa
                    </label>
                    <select
                        name="buscar_empresa"
                        class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none"
                    >
                        <option value="">Todas las empresas</option>
                        <?php if ($companies && $companies->num_rows > 0): ?>
                            <?php
                            $companies->data_seek(0);
                            while ($company = $companies->fetch_assoc()):
                                $idEmpresa = (int)$company['id_empresa'];
                            ?>
                                <option value="<?php echo $idEmpresa; ?>" <?php echo ($buscar_empresa !== '' && (int)$buscar_empresa === $idEmpresa) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="flex flex-wrap gap-3 justify-start md:justify-end">
                    <button
                        type="submit"
                        class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all"
                    >
                        <i class="fas fa-filter"></i>
                        Filtrar
                    </button>
                    <?php if ($buscar_texto !== '' || $buscar_empresa !== ''): ?>
                    <a
                        href="carta-mail.php"
                        class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all"
                    >
                        <i class="fas fa-undo"></i>
                        Limpiar
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Sección de Requesters -->
    <div class="mb-8">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-users text-gray-500"></i>
                    Gestión de solicitantes
                </h2>
                <button type="button" class="inv-btn-primary inline-flex items-center gap-2 px-4 py-2 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all text-xs sm:text-sm" onclick="abrirModalAgregar()">
                    <i class="fas fa-user-plus text-xs"></i>
                    Agregar solicitante
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teléfono</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empresa</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($requestersResult->num_rows > 0): ?>
                            <?php while ($requester = $requestersResult->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#<?php echo htmlspecialchars($requester['requester_number'] ?? $requester['id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($requester['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($requester['email']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($requester['phone'] ?? 'N/A'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($requester['company_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors" onclick="editarRequester(<?php echo $requester['id']; ?>, '<?php echo htmlspecialchars($requester['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($requester['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($requester['phone'] ?? '', ENT_QUOTES); ?>', <?php echo $requester['company_id'] ?? 'null'; ?>)">
                                                Editar
                                            </button>
                                            <?php if (isset($requesters_con_carta[$requester['id']])): ?>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-300 cursor-default"
                                                    disabled
                                                    title="Ya existe una carta para este usuario"
                                                >
                                                    <i class="fas fa-check text-[10px]"></i>
                                                    Carta generada
                                                </button>
                                            <?php else: ?>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium text-white inv-brand-bg hover:opacity-90 transition-opacity"
                                                    onclick="generarCarta(<?php echo $requester['id']; ?>)"
                                                >
                                                    <i class="fas fa-file-signature text-[10px]"></i>
                                                    Generar carta
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-red-600 text-white hover:bg-red-700 transition-colors" onclick="eliminarRequester(<?php echo $requester['id']; ?>)">
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-5 text-center text-sm text-gray-500">
                                    No hay solicitantes registrados.
                                    <button type="button" class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-emerald-600 text-white hover:bg-emerald-700 ml-2" onclick="abrirModalAgregar()">Agregar uno ahora</button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sección de Cartas Generadas -->
    <div class="mb-4 mt-8">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-file-signature text-gray-500"></i>
                    Cartas generadas
                </h2>
            </div>
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número de Carta</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Solicitante</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Generación</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($cartasResult->num_rows > 0): ?>
                        <?php while ($carta = $cartasResult->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($carta['numero_carta']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="font-medium"><?php echo htmlspecialchars($carta['requester_name']); ?></div>
                                    <div class="text-gray-500 text-xs"><?php echo htmlspecialchars($carta['requester_email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($carta['fecha_generacion'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($carta['tipo_cumplimiento'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php
                                    $estado_bg = 'bg-yellow-100 text-yellow-800';
                                    if ($carta['estado'] == 'Firmada') $estado_bg = 'bg-green-100 text-green-800';
                                    if ($carta['estado'] == 'Cancelada') $estado_bg = 'bg-red-100 text-red-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $estado_bg; ?>">
                                        <?php echo htmlspecialchars($carta['estado']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex gap-2">
                                        <?php if (!empty($carta['archivo_firmado']) && $carta['estado'] == 'Firmada'): ?>
                                            <a href="uploads/cartas_firmadas/<?php echo urlencode($carta['archivo_firmado']); ?>" target="_blank" class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors">
                                                Ver firmada
                                            </a>
                                            <a href="ver-carta-mail-pdf.php?numero=<?php echo urlencode($carta['numero_carta']); ?>" target="_blank" class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors">
                                                Ver original
                                            </a>
                                            <button type="button" class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-amber-500 text-white hover:bg-amber-600 transition-colors" onclick="subirFirma(<?php echo $carta['id_carta']; ?>, '<?php echo htmlspecialchars($carta['numero_carta']); ?>', true)">
                                                Reemplazar firma
                                            </button>
                                            <button type="button" class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-red-600 text-white hover:bg-red-700 transition-colors" onclick="eliminarFirma(<?php echo $carta['id_carta']; ?>, '<?php echo htmlspecialchars($carta['numero_carta']); ?>', '<?php echo htmlspecialchars($carta['archivo_firmado']); ?>')">
                                                Eliminar firma
                                            </button>
                                        <?php else: ?>
                                            <a href="ver-carta-mail-pdf.php?numero=<?php echo urlencode($carta['numero_carta']); ?>" target="_blank" class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors">
                                                Ver
                                            </a>
                                            <?php if ($carta['estado'] == 'Pendiente'): ?>
                                                <button type="button" class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-amber-500 text-white hover:bg-amber-600 transition-colors" onclick="subirFirma(<?php echo $carta['id_carta']; ?>, '<?php echo htmlspecialchars($carta['numero_carta']); ?>', false)">
                                                    Subir firma
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                No hay cartas generadas aún.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Agregar/Editar Requester -->
<div id="modalRequester" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalRequesterTitle">Agregar Solicitante</h2>
            <span class="close" onclick="cerrarModalRequester()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formRequester" method="post" action="procesar-carta-mail.php">
                <input type="hidden" name="action" id="requesterAction" value="agregar_requester">
                <input type="hidden" name="requester_id" id="requesterId">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre completo *</label>
                    <input type="text" name="name" id="requesterName" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email *</label>
                    <input type="email" name="email" id="requesterEmail" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Teléfono</label>
                    <input type="text" name="phone" id="requesterPhone" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none">
                </div>
                
                <div class="mb-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Empresa</label>
                    <select name="company_id" id="requesterCompanyId" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none">
                        <option value="">Seleccione una empresa</option>
                        <?php if ($companies && $companies->num_rows > 0): ?>
                            <?php
                            // Rebobinar el result set por si se reutiliza
                            $companies->data_seek(0);
                            while ($company = $companies->fetch_assoc()):
                            ?>
                                <option value="<?php echo (int)$company['id_empresa']; ?>">
                                    <?php echo htmlspecialchars($company['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="flex gap-3 justify-end mt-6">
                    <button type="button" class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all" onclick="cerrarModalRequester()">Cancelar</button>
                    <button type="submit" class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Generar Carta -->
<div id="modalGenerarCarta" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Generar Carta Responsiva de Correo Electrónico</h2>
            <span class="close" onclick="cerrarModalGenerarCarta()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formGenerarCarta" method="post" action="procesar-carta-mail.php">
                <input type="hidden" name="action" value="generar_carta">
                <input type="hidden" name="id_requester" id="cartaRequesterId">
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-4">
                        Se generará una carta responsiva estándar sobre el uso del correo electrónico corporativo con el contenido predefinido.
                    </p>
                </div>
                
                <div class="flex gap-3 justify-end mt-6">
                    <button type="button" class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all" onclick="cerrarModalGenerarCarta()">Cancelar</button>
                    <button type="submit" class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all">
                        Generar carta PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Subir Firma -->
<div id="modalSubirFirma" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalSubirFirmaTitle">Subir Carta Firmada</h2>
            <span class="close" onclick="cerrarModalSubirFirma()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formSubirFirma" method="post" action="procesar-carta-mail.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="subir_firma">
                <input type="hidden" name="id_carta" id="firmaCartaId">
                <input type="hidden" name="numero_carta" id="firmaNumeroCarta">
                <input type="hidden" name="archivo_anterior" id="firmaArchivoAnterior">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Archivo PDF firmado *</label>
                    <input type="file" name="archivo_firmado" id="archivoFirmadoInput" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none" accept=".pdf" required>
                    <p class="text-xs text-gray-500 mt-1">Solo archivos PDF. Tamaño máximo: 10MB</p>
                    <p id="archivoActualInfo" class="text-xs text-orange-600 mt-2" style="display: none;"></p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fecha de firma</label>
                    <input type="date" name="fecha_firma" class="inv-input w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-indigo-800 focus:ring-2 focus:ring-indigo-200 outline-none" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="flex gap-3 justify-end mt-6">
                    <button type="button" class="inv-btn-secondary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-gray-700 bg-gray-100 border border-gray-200 hover:bg-gray-200 hover:border-gray-300 transition-all" onclick="cerrarModalSubirFirma()">Cancelar</button>
                    <button type="submit" class="inv-btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white inv-brand-bg shadow-md hover:shadow-lg transition-all" id="btnSubirFirma">
                        Subir archivo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Funciones para modales
function abrirModalAgregar() {
    document.getElementById('modalRequesterTitle').textContent = 'Agregar Solicitante';
    document.getElementById('requesterAction').value = 'agregar_requester';
    document.getElementById('formRequester').reset();
    document.getElementById('requesterId').value = '';
    document.getElementById('modalRequester').style.display = 'block';
}

function editarRequester(id, name, email, phone, companyId) {
    document.getElementById('modalRequesterTitle').textContent = 'Editar Solicitante';
    document.getElementById('requesterAction').value = 'editar_requester';
    document.getElementById('requesterId').value = id;
    document.getElementById('requesterName').value = name || '';
    document.getElementById('requesterEmail').value = email || '';
    document.getElementById('requesterPhone').value = phone || '';
    document.getElementById('requesterCompanyId').value = companyId || '';
    document.getElementById('modalRequester').style.display = 'block';
}

function generarCarta(id) {
    document.getElementById('cartaRequesterId').value = id;
    document.getElementById('modalGenerarCarta').style.display = 'block';
}

function subirFirma(id, numero, esReemplazo) {
    document.getElementById('firmaCartaId').value = id;
    document.getElementById('firmaNumeroCarta').value = numero;
    
    if (esReemplazo) {
        document.getElementById('modalSubirFirmaTitle').textContent = 'Reemplazar Carta Firmada';
        document.getElementById('btnSubirFirma').textContent = 'Reemplazar Archivo';
        // Obtener el nombre del archivo actual desde la base de datos o pasarlo como parámetro
        // Por ahora, solo mostramos un mensaje
        document.getElementById('archivoActualInfo').style.display = 'block';
        document.getElementById('archivoActualInfo').textContent = 'Se eliminará el archivo actual y se subirá el nuevo.';
    } else {
        document.getElementById('modalSubirFirmaTitle').textContent = 'Subir Carta Firmada';
        document.getElementById('btnSubirFirma').textContent = 'Subir Archivo';
        document.getElementById('archivoActualInfo').style.display = 'none';
    }
    
    // Limpiar el formulario
    document.getElementById('formSubirFirma').reset();
    document.getElementById('firmaCartaId').value = id;
    document.getElementById('firmaNumeroCarta').value = numero;
    
    document.getElementById('modalSubirFirma').style.display = 'block';
}

function eliminarFirma(id, numero, archivo) {
    if (confirm('¿Está seguro de que desea eliminar el archivo firmado? Esta acción no se puede deshacer.')) {
        // Crear un formulario temporal para enviar la solicitud
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'procesar-carta-mail.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'eliminar_firma';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id_carta';
        idInput.value = id;
        form.appendChild(idInput);
        
        const archivoInput = document.createElement('input');
        archivoInput.type = 'hidden';
        archivoInput.name = 'archivo_firmado';
        archivoInput.value = archivo;
        form.appendChild(archivoInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function eliminarRequester(id) {
    if (confirm('¿Está seguro de que desea eliminar este solicitante?')) {
        window.location.href = 'procesar-carta-mail.php?action=eliminar_requester&id=' + id;
    }
}

function cerrarModalRequester() {
    document.getElementById('modalRequester').style.display = 'none';
}

function cerrarModalGenerarCarta() {
    document.getElementById('modalGenerarCarta').style.display = 'none';
}

function cerrarModalSubirFirma() {
    document.getElementById('modalSubirFirma').style.display = 'none';
}

// Cerrar modales al hacer clic fuera
window.onclick = function(event) {
    const modalRequester = document.getElementById('modalRequester');
    const modalGenerarCarta = document.getElementById('modalGenerarCarta');
    const modalSubirFirma = document.getElementById('modalSubirFirma');
    
    if (event.target == modalRequester) {
        modalRequester.style.display = 'none';
    }
    if (event.target == modalGenerarCarta) {
        modalGenerarCarta.style.display = 'none';
    }
    if (event.target == modalSubirFirma) {
        modalSubirFirma.style.display = 'none';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>

<?php if (isset($_GET['generar_pdf']) && isset($_SESSION['carta_cumplimiento_generada'])): ?>
<script>
// Esperar a que todo esté cargado antes de abrir el PDF
window.addEventListener('load', function() {
    // Pequeño delay para asegurar que todo esté listo
    setTimeout(function() {
        try {
            // Intentar abrir el PDF
            const pdfWindow = window.open('generar-pdf-carta-mail.php', '_blank', 'noopener,noreferrer');
            
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
    fetch('generar-pdf-carta-mail.php?limpiar_sesion=1', {method: 'GET'})
        .catch(function() {
            // Ignorar errores
        });
}, 300000); // 5 minutos
</script>
<?php endif; ?>

