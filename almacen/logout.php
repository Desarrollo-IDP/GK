<?php
/**
 * Logout - Sistema de Gestión de Almacén
 */
session_start();
session_destroy();
header('Location: ../index.php');
exit();
?>

