<?php
include_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/../../includes/empresa_helper.php';
checkAuth();

function isEmail($string) {
    return filter_var($string, FILTER_VALIDATE_EMAIL) !== false;
}

function isTicketId($string) {
    return is_numeric($string) && (int)$string > 0;
}

function searchInDatabase($searchTerm) {
    global $conn;
    
    $results = [
        'type' => 'unknown',
        'data' => null,
        'redirect_url' => null
    ];
    
    $user_empresa_id = getCurrentUserEmpresaId();
    
    try {
        // 1. Verificar si es un ID de ticket o ticket_number (filtrado por empresa)
        if (isTicketId($searchTerm)) {
            $ticket_id = (int)$searchTerm;
            $ticket_query = "SELECT t.id, t.ticket_number, t.subject, t.status_id FROM tickets t WHERE (t.id = ? OR t.ticket_number = ?) AND t.deleted = 0";
            $ticket_params = [$ticket_id, $searchTerm];
            $ticket_types = 'is';
            $ticket_query = addEmpresaFilter($ticket_query, 't.id_empresa', $ticket_params, $ticket_types);
            $stmt = $conn->prepare($ticket_query);
            $stmt->bind_param($ticket_types, ...$ticket_params);
            $stmt->execute();
            $result = $stmt->get_result();
            $ticket = $result->fetch_assoc();
            $stmt->close();
            
            if ($ticket) {
                $results['type'] = 'ticket';
                $results['data'] = $ticket;
                $results['redirect_url'] = appUrl('/tickets/modules/tickets/tickets.php?id=' . $ticket['id']);
                return $results;
            }
        }
        
        // 2. Verificar si es un email (filtrado por empresa)
        if (isEmail($searchTerm)) {
            // Buscar en técnicos
            $tech_query = "SELECT id, name, email FROM technicians WHERE email = ?";
            $tech_params = [$searchTerm];
            $tech_types = 's';
            $tech_query = addEmpresaFilter($tech_query, 'id_empresa', $tech_params, $tech_types);
            $stmt = $conn->prepare($tech_query);
            $stmt->bind_param($tech_types, ...$tech_params);
            $stmt->execute();
            $result = $stmt->get_result();
            $technician = $result->fetch_assoc();
            $stmt->close();
            
            if ($technician) {
                $results['type'] = 'technician';
                $results['data'] = $technician;
                $results['redirect_url'] = appUrl('/tickets/modules/technicians/technicians.php?id=' . $technician['id']);
                return $results;
            }
            
            // Buscar en solicitantes
            $req_query = "SELECT id, name, email FROM requesters WHERE email = ?";
            $req_params = [$searchTerm];
            $req_types = 's';
            $req_query = addEmpresaFilter($req_query, 'id_empresa', $req_params, $req_types);
            $stmt = $conn->prepare($req_query);
            $stmt->bind_param($req_types, ...$req_params);
            $stmt->execute();
            $result = $stmt->get_result();
            $requester = $result->fetch_assoc();
            $stmt->close();
            
            if ($requester) {
                $results['type'] = 'requester';
                $results['data'] = $requester;
                $results['redirect_url'] = appUrl('/tickets/modules/requester/requester.php?id=' . $requester['id']);
                return $results;
            }
        }
        
        // 3. Buscar por nombre (técnicos) - filtrado por empresa
        $searchPattern = '%' . $searchTerm . '%';
        $tech_query = "SELECT id, name, email FROM technicians WHERE name LIKE ?";
        $tech_params = [$searchPattern];
        $tech_types = 's';
        $tech_query = addEmpresaFilter($tech_query, 'id_empresa', $tech_params, $tech_types);
        $stmt = $conn->prepare($tech_query);
        $stmt->bind_param($tech_types, ...$tech_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $technician = $result->fetch_assoc();
        $stmt->close();
        
        if ($technician) {
            $results['type'] = 'technician';
            $results['data'] = $technician;
            $results['redirect_url'] = appUrl('/tickets/modules/technicians/technicians.php?id=' . $technician['id']);
            return $results;
        }
        
        // 4. Buscar por nombre (solicitantes) - filtrado por empresa
        $req_query = "SELECT id, name, email FROM requesters WHERE name LIKE ?";
        $req_params = [$searchPattern];
        $req_types = 's';
        $req_query = addEmpresaFilter($req_query, 'id_empresa', $req_params, $req_types);
        $stmt = $conn->prepare($req_query);
        $stmt->bind_param($req_types, ...$req_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $requester = $result->fetch_assoc();
        $stmt->close();
        
        if ($requester) {
            $results['type'] = 'requester';
            $results['data'] = $requester;
            $results['redirect_url'] = appUrl('/tickets/modules/requester/requester.php?id=' . $requester['id']);
            return $results;
        }
        
        // 5. Buscar en asunto de tickets (filtrado por empresa)
        $ticket_query = "SELECT t.id, t.ticket_number, t.subject, t.status_id FROM tickets t WHERE t.subject LIKE ? AND t.deleted = 0";
        $ticket_params = [$searchPattern];
        $ticket_types = 's';
        $ticket_query = addEmpresaFilter($ticket_query, 't.id_empresa', $ticket_params, $ticket_types);
        $ticket_query .= " ORDER BY t.created_at DESC LIMIT 1";
        $stmt = $conn->prepare($ticket_query);
        $stmt->bind_param($ticket_types, ...$ticket_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $ticket = $result->fetch_assoc();
        $stmt->close();
        
        if ($ticket) {
            $results['type'] = 'ticket';
            $results['data'] = $ticket;
            $results['redirect_url'] = appUrl('/tickets/modules/tickets/tickets.php?id=' . $ticket['id']);
            return $results;
        }
        
        // 6. Si no encuentra nada específico, redirigir a búsqueda general
        $results['type'] = 'general_search';
        $results['redirect_url'] = appUrl('/tickets/modules/tickets/all_tickets.php?search=' . urlencode($searchTerm));
        
    } catch (Exception $e) {
        error_log("Error en búsqueda inteligente: " . $e->getMessage());
        $results['type'] = 'error';
        $results['redirect_url'] = appUrl('/tickets/modules/tickets/all_tickets.php?search=' . urlencode($searchTerm));
    }
    
    return $results;
}

// Si se llama directamente con parámetros GET
if (isset($_GET['q'])) {
    $searchTerm = trim($_GET['q']);
    $results = searchInDatabase($searchTerm);
    
    header('Location: ' . $results['redirect_url']);
    exit;
}
?>
