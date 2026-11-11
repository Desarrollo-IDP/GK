<?php
include '../../includes/db.php';

$id = $_GET['id'] ?? null;
if ($id) {
    try {
        // Obtener datos del cliente antes de eliminar
        $sql = "SELECT * FROM clientes WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $cliente = $stmt->fetch();

        if ($cliente) {
            // Verificar si el cliente tiene membresías asociadas
            $sql_membresias = "SELECT COUNT(*) FROM membresias WHERE cliente_id = ?";
            $stmt_membresias = $pdo->prepare($sql_membresias);
            $stmt_membresias->execute([$id]);
            $tiene_membresias = $stmt_membresias->fetchColumn() > 0;

            if ($tiene_membresias) {
                // No eliminar si tiene membresías
                header('Location: list.php?error=No se puede eliminar el cliente porque tiene membresías asociadas');
                exit;
            }

            // Eliminar la foto del servidor si existe
            if (!empty($cliente['foto'])) {
                $foto_path = '../../' . $cliente['foto'];
                if (file_exists($foto_path) && is_writable($foto_path)) {
                    @unlink($foto_path);
                }
            }

            // Eliminar el cliente de la base de datos
            $sql_delete = "DELETE FROM clientes WHERE id = ?";
            $stmt_delete = $pdo->prepare($sql_delete);
            $result = $stmt_delete->execute([$id]);

            if ($result) {
                header('Location: list.php?success=Cliente eliminado exitosamente');
            } else {
                header('Location: list.php?error=Error al eliminar el cliente');
            }
        } else {
            header('Location: list.php?error=Cliente no encontrado');
        }
    } catch (PDOException $e) {
        header('Location: list.php?error=Error al eliminar el cliente: ' . urlencode($e->getMessage()));
    }
} else {
    header('Location: list.php?error=ID de cliente no proporcionado');
}
exit;
?> 