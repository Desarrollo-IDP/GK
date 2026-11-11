<?php
header('Content-Type: application/json');
require __DIR__ . '/../../../index.php';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
    
        $stmt = $pdo->query("SELECT * FROM productos");
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($productos);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO productos 
            (cliente, nombre_equipo, resp, sistema_operativo, mac, tipo, 
             fabriMarca, modelo, numSerie, ram, discoDuro, procesador, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
        $stmt->execute([
            $data['cliente'],$data['nombreEquipo'],$data['resp'],$data['sistemaOperativo'],$data['mac'],$data['tipo'],$data['fabriMarca'],$data['modelo'],$data['numSerie'],$data['ram'],$data['discoDuro'],$data['procesador'],$data['observaciones']]);
        echo json_encode(['message' => 'Producto creado exitosamente']);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        break;
}
