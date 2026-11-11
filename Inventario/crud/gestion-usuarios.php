<?php
require_once 'includes/db.php';
require_once '../../auth.php';
checkAdmin();

$pageTitle = 'Gesti&oacuten de Usuarios';
require_once 'includes/header.php';


$current_user = $_SESSION['user']['nombre_usuario'] ?? '';
$canViewAdminProfile = canViewAdminProfile();

if ($canViewAdminProfile) {
    $query = "SELECT * FROM usuarios ORDER BY nombre_usuario";
} else {
    $query = "SELECT * FROM usuarios WHERE LOWER(nombre_usuario) != 'admin' ORDER BY nombre_usuario";
}

$result = $conn->query($query);

if (!$result) {
    die("Error en la consulta: " . $conn->error);
}
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Gesti&oacuten de Usuarios</h1>
    
    <div class="mb-4">
        <a href="agregar-usuario.php" class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
            Agregar Usuario
        </a>
    </div>
    
    <div class="bg-white shadow-md rounded my-6">
        <table class="min-w-full bg-white">
            <thead>
                <tr>
                    <th class="py-4 px-6 bg-gray-100 font-bold uppercase text-sm text-gray-600 border-b border-gray-200">
                        Usuario
                    </th>
                    <th class="py-4 px-6 bg-gray-100 font-bold uppercase text-sm text-gray-600 border-b border-gray-200">
                        Correo Electrónico
                    </th>
                    <th class="py-4 px-6 bg-gray-100 font-bold uppercase text-sm text-gray-600 border-b border-gray-200">
                        Rol
                    </th>
                    <th class="py-4 px-6 bg-gray-100 font-bold uppercase text-sm text-gray-600 border-b border-gray-200">
                        autenticado
                    </th>
                    <th class="py-4 px-6 bg-gray-100 font-bold uppercase text-sm text-gray-600 border-b border-gray-200">
                        Acciones
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php while ($usuario = $result->fetch_assoc()): ?>
                <tr>
                    <td class="py-4 px-6 border-b border-gray-200 text-center">
                        <?php echo htmlspecialchars($usuario['nombre_usuario'] ?? ''); ?>
                    </td>
                    <td class="py-4 px-6 border-b border-gray-200 text-center">
                        <?php echo htmlspecialchars($usuario['email'] ?? ''); ?>
                    </td>
                    <td class="py-4 px-6 border-b border-gray-200 text-center">
                        <?php echo htmlspecialchars($usuario['rol'] ?? ''); ?>
                    </td>
                    <td class="py-4 px-6 border-b border-gray-200 text-center">
                        <?php echo htmlspecialchars($usuario['google_auth_secret'] ?? ''); ?>
                    </td>
                    <td class="py-4 px-6 border-b border-gray-200 text-center">
                        <?php 
                        $isAdminUser = strtolower($usuario['nombre_usuario']) === 'admin';
                        if (!$isAdminUser || $canViewAdminProfile): 
                        ?>
                        <a href="editar-usuario.php?id=<?php echo $usuario['id_usuario']; ?>" 
                           class="text-blue-600 hover:text-blue-900 mr-3 ">Editar</a>
                        
                        <?php if (!$isAdminUser): ?>
                        <button onclick="eliminarUsuario(<?php echo $usuario['id_usuario']; ?>)" 
                                class="text-red-600 hover:text-red-900 eliminar-usuario" 
                                data-id="<?php echo $usuario['id_usuario']; ?>"
                                data-nombre="<?php echo htmlspecialchars($usuario['nombre_usuario']); ?>">
                            Eliminar
                        </button>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function eliminarUsuario(id) {
    const row = event.target.closest('tr');
    const nombre = event.target.getAttribute('data-nombre');
    
    if (confirm(`¿Estás seguro de que desea eliminar al usuario "${nombre}"?`)) {
        fetch('api/delete-usuario.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                nombre_usuario: nombre
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                row.remove();
            } else {
                alert(data.error || 'Error al eliminar el usuario');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
 