<?php
include '../../includes/db.php';
$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = $pdo->prepare("DELETE FROM membresias WHERE id = ?");
    $stmt->execute([$id]);
}
header('Location: list.php');
exit; 