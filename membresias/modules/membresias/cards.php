<?php

// Define current page for active menu highlighting
$current_page = 'membresias_cards';

include '../../includes/db.php';
include '../../includes/config.php';
include '../../includes/header.php';
include '../../config/mercadopago_config.php';

// Obtener la empresa del usuario logueado
$empresa_id = getCurrentUserEmpresaIdOrDefault();

// Obtener los tipos de membresía de la empresa del usuario
try {
    // Consulta simplificada y robusta
    $sql = "SELECT 
                t.id, 
                t.nombre,
                t.descripcion,
                t.precio,
                t.duracion_dias,
                t.empresa_id,
                COALESCE(COUNT(m.id), 0) as total_membresias
            FROM tipos_membresia t
            LEFT JOIN membresias m ON t.id = m.tipo_membresia_id AND m.empresa_id = ?
            WHERE t.empresa_id = ?
            GROUP BY t.id, t.nombre, t.descripcion, t.precio, t.duracion_dias, t.empresa_id
            ORDER BY t.nombre ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empresa_id, $empresa_id]);
    $tipos_membresia = $stmt->fetchAll(PDO::FETCH_ASSOC);
    

    
} catch (PDOException $e) {
    error_log("ERROR: Error al cargar los tipos de membresía: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error al cargar los tipos de membresía: ' . htmlspecialchars($e->getMessage()) . '</div>';
    $tipos_membresia = [];
}

// Función para formatear precio
function formatPrecio($precio) {
    return '$' . number_format($precio, 2);
}

// Función para obtener el estado del tipo de membresía
function getEstadoTipoMembresia($total_membresias) {
    // Asegurar que el valor sea numérico y manejar valores null
    $total = (int)($total_membresias ?? 0);
    error_log("DEBUG: getEstadoTipoMembresia - Total membresías: " . $total . " (original: " . $total_membresias . ")");
    
    if ($total == 0) {
        return ['estado' => 'disponible', 'clase' => 'success', 'texto' => 'Disponible'];
    } else {
        return ['estado' => 'en_uso', 'clase' => 'info', 'texto' => 'En uso'];
    }
}

?>

<style>
.body-bg {
    background: #f7f8fa;
    min-height: 100vh;
    width: 100vw;
    overflow-x: hidden;
}

.main-content-boxed {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2.5rem 2rem;
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 24px 0 rgba(32,38,84,0.07);
    min-height: 80vh;
    margin-top: 2rem;
    margin-bottom: 2rem;
}

.tipo-membresia-card {
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
    overflow: hidden;
    height: 100%;
}

.tipo-membresia-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-color: #007bff;
}

.tipo-membresia-card.inactivo {
    border-color: #6c757d;
    opacity: 0.7;
}

.tipo-membresia-card.disponible {
    border-color: #28a745;
}

.tipo-membresia-card.en_uso {
    border-color: #17a2b8;
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
    position: relative;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.card-body {
    padding: 1.5rem;
}

.card-footer {
    padding: 1rem 1.5rem;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

.estado-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    background: rgba(255,255,255,0.2);
    color: white;
}

.precio-tag {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-weight: 600;
    font-size: 1.1rem;
    display: inline-block;
    margin-top: 0.5rem;
}

.info-row {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
}

.info-label {
    font-weight: 600;
    color: #6c757d;
    min-width: 120px;
    margin-right: 0.5rem;
}

.info-value {
    color: #495057;
}

.btn-crear {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-crear:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    color: white;
    text-decoration: none;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: #dee2e6;
}

@media (max-width: 991.98px) {
    .main-content-boxed {
        margin: 1rem;
        padding: 1.5rem 1rem;
    }
    
    .tipo-membresia-card {
        margin-bottom: 1rem;
    }
}
</style>
<?php include '../../includes/sidebar.php'; ?>
<div class="body-bg">
    <div class="main-content-boxed">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Tipos de Membresía</h1>
                <p class="text-muted mb-0">Gestiona los tipos de membresía de tu empresa</p>
            </div>
            <div>
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva Membresía
                </a>
                <!--<a href="list.php" class="btn btn-outline-secondary ml-2">
                    <i class="fas fa-list"></i> Vista Lista
                </a>-->
            </div>
        </div>

        <?php if (empty($tipos_membresia)): ?>
            <div class="empty-state">
                <i class="fas fa-id-card"></i>
                <h3>No hay tipos de membresía</h3>
                <p>No se encontraron tipos de membresía para tu empresa. Contacta al administrador.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($tipos_membresia as $tipo): ?>
                    <?php 
                    $estado = getEstadoTipoMembresia($tipo['total_membresias']);
                    ?>
                    
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="tipo-membresia-card <?php echo $estado['estado']; ?>" 
                             onclick="crearMembresiaDesdeTipo(<?php echo htmlspecialchars(json_encode($tipo)); ?>)">
                            
                            <div class="card-header">
                                <h5 class="mb-2"><?php echo htmlspecialchars($tipo['nombre']); ?></h5>
                                <div class="precio-tag">
                                    <?php echo formatPrecio($tipo['precio']); ?>
                                </div>
                                
                                <span class="estado-badge">
                                    <?php echo $estado['texto']; ?>
                                </span>
                            </div>
                            
                            <div class="card-body">
                                <div class="info-row">
                                    <span class="info-label">Descripción:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($tipo['descripcion'] ?: 'Sin descripción'); ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">Duración:</span>
                                    <span class="info-value"><?php echo $tipo['duracion_dias']; ?> días</span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">Membresías:</span>
                                    <span class="info-value"><?php echo $tipo['total_membresias']; ?> activas</span>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <button class="btn btn-crear w-100" 
                                        onclick="event.stopPropagation(); crearMembresiaDesdeTipo(<?php echo htmlspecialchars(json_encode($tipo)); ?>)">
                                    <i class="fas fa-plus"></i> Crear Membresía
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function crearMembresiaDesdeTipo(tipo) {
    // Crear un formulario temporal para enviar los datos
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'add.php';
    
    // Solo enviar el ID del tipo de membresía seleccionado
    const campos = {
        'tipo_membresia_id': tipo.id,
        'crear_desde_tipo': '1' // Flag para indicar que viene desde tipo de membresía
    };
    
    // Crear campos ocultos para cada dato
    Object.keys(campos).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = campos[key];
        form.appendChild(input);
    });
    
    // Agregar el formulario al DOM y enviarlo
    document.body.appendChild(form);
    form.submit();
}

// Agregar efecto de hover con información adicional
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.tipo-membresia-card');
    
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?> 