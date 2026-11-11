<?php
include_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/../../includes/empresa_helper.php';
checkAuth();

header('Content-Type: application/json');

if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    echo json_encode([]);
    exit;
}

$searchTerm = trim($_GET['q']);
$suggestions = [];
$user_empresa_id = getCurrentUserEmpresaId();

try {
    $searchPattern = '%' . $searchTerm . '%';
    
    // Buscar técnicos (filtrado por empresa)
    $tech_query = "SELECT id, name, email, 'technician' as type FROM technicians WHERE (name LIKE ? OR email LIKE ?)";
    $tech_params = [$searchPattern, $searchPattern];
    $tech_types = 'ss';
    $tech_query = addEmpresaFilter($tech_query, 'id_empresa', $tech_params, $tech_types);
    $tech_query .= " LIMIT 3";
    $stmt = $conn->prepare($tech_query);
    $stmt->bind_param($tech_types, ...$tech_params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($tech = $result->fetch_assoc()) {
        $suggestions[] = [
            'id' => $tech['id'],
            'name' => $tech['name'],
            'email' => $tech['email'],
            'type' => 'Técnico',
            'icon' => 'fas fa-user-cog',
            'url' => appUrl('/tickets/modules/technicians/technicians.php?id=' . $tech['id'])
        ];
    }
    $stmt->close();
    
    // Buscar solicitantes (filtrado por empresa)
    $req_query = "SELECT id, name, email, 'requester' as type FROM requesters WHERE (name LIKE ? OR email LIKE ?)";
    $req_params = [$searchPattern, $searchPattern];
    $req_types = 'ss';
    $req_query = addEmpresaFilter($req_query, 'id_empresa', $req_params, $req_types);
    $req_query .= " LIMIT 3";
    $stmt = $conn->prepare($req_query);
    $stmt->bind_param($req_types, ...$req_params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($req = $result->fetch_assoc()) {
        $suggestions[] = [
            'id' => $req['id'],
            'name' => $req['name'],
            'email' => $req['email'],
            'type' => 'Solicitante',
            'icon' => 'fas fa-user',
            'url' => appUrl('/tickets/modules/requester/requester.php?id=' . $req['id'])
        ];
    }
    $stmt->close();
    
    // Función auxiliar para obtener y limpiar el asunto del ticket
    function getTicketSubject($ticket) {
        // Priorizar subject, luego email_subject, luego description truncado
        $subject = trim($ticket['subject'] ?? '');
        if (empty($subject)) {
            $subject = trim($ticket['email_subject'] ?? '');
        }
        if (empty($subject) && !empty($ticket['description'])) {
            // Si no hay asunto, usar una parte de la descripción
            $description = strip_tags($ticket['description']);
            $description = preg_replace('/\s+/', ' ', $description); // Limpiar espacios múltiples
            $subject = mb_substr(trim($description), 0, 100);
            if (mb_strlen($description) > 100) {
                $subject .= '...';
            }
        }
        
        // Limpiar el asunto: remover prefijos comunes de respuesta
        $subject = preg_replace('/^(Re:|RE:|Respuesta:|RESPUESTA:|Fwd:|FWD:|Fw:|FW:)\s*/i', '', $subject);
        $subject = trim($subject);
        
        // Limitar longitud para mostrar bien en las sugerencias
        if (mb_strlen($subject) > 80) {
            $subject = mb_substr($subject, 0, 77) . '...';
        }
        
        return !empty($subject) ? $subject : 'Sin asunto';
    }
    
    // Buscar tickets por ticket_number (filtrado por empresa)
    if (is_numeric($searchTerm)) {
        $ticket_query = "SELECT t.id, t.ticket_number, t.subject, t.email_subject, t.description, t.status_id FROM tickets t WHERE t.ticket_number = ? AND t.deleted = 0";
        $ticket_params = [$searchTerm];
        $ticket_types = 's';
        $ticket_query = addEmpresaFilter($ticket_query, 't.id_empresa', $ticket_params, $ticket_types);
        $ticket_query .= " LIMIT 1";
        $stmt = $conn->prepare($ticket_query);
        $stmt->bind_param($ticket_types, ...$ticket_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $ticket = $result->fetch_assoc();
        
        if ($ticket) {
            $display_number = $ticket['ticket_number'] ?? $ticket['id'];
            $suggestions[] = [
                'id' => $ticket['id'],
                'name' => 'Ticket #' . $display_number,
                'email' => getTicketSubject($ticket),
                'type' => 'Ticket',
                'icon' => 'fas fa-ticket-alt',
                'url' => appUrl('/tickets/modules/tickets/tickets.php?id=' . $ticket['id'])
            ];
        }
        $stmt->close();
    }
    
    // Buscar tickets por asunto (filtrado por empresa)
    $ticket_query = "SELECT t.id, t.ticket_number, t.subject, t.email_subject, t.description, t.status_id FROM tickets t WHERE (t.subject LIKE ? OR t.email_subject LIKE ?) AND t.deleted = 0";
    $ticket_params = [$searchPattern, $searchPattern];
    $ticket_types = 'ss';
    $ticket_query = addEmpresaFilter($ticket_query, 't.id_empresa', $ticket_params, $ticket_types);
    $ticket_query .= " ORDER BY t.created_at DESC LIMIT 2";
    $stmt = $conn->prepare($ticket_query);
    $stmt->bind_param($ticket_types, ...$ticket_params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($ticket = $result->fetch_assoc()) {
        $display_number = $ticket['ticket_number'] ?? $ticket['id'];
        $suggestions[] = [
            'id' => $ticket['id'],
            'name' => 'Ticket #' . $display_number,
            'email' => getTicketSubject($ticket),
            'type' => 'Ticket',
            'icon' => 'fas fa-ticket-alt',
            'url' => appUrl('/tickets/modules/tickets/tickets.php?id=' . $ticket['id'])
        ];
    }
    $stmt->close();
    
    // Limitar a 5 sugerencias máximo
    $suggestions = array_slice($suggestions, 0, 5);
    
} catch (Exception $e) {
    error_log("Error en sugerencias de búsqueda: " . $e->getMessage());
}

echo json_encode($suggestions);
?>
