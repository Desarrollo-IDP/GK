<?php
/**
 * Etiquetas de estado y cierre por inactividad — módulo tickets
 */

function ticketIsClosedByInactivity(array $ticket): bool
{
    return !empty($ticket['closed_by_inactivity']);
}

function ticketGetDisplayStatusName(?string $status_name, $closed_by_inactivity = false): string
{
    if ($closed_by_inactivity) {
        return 'Cerrado por inactividad';
    }

    return $status_name !== null && $status_name !== '' ? $status_name : 'Sin estado';
}

function ticketGetDisplayStatusNameFromRow(array $ticket): string
{
    return ticketGetDisplayStatusName(
        $ticket['status_name'] ?? null,
        ticketIsClosedByInactivity($ticket)
    );
}

function ticketGetStatusColorClass(?string $status_name, bool $closed_by_inactivity = false): string
{
    if ($closed_by_inactivity) {
        return 'warning';
    }

    $status = strtolower(trim((string) $status_name));

    if (strpos($status, 'cerrado') !== false || strpos($status, 'resuelto') !== false) {
        return 'secondary';
    }
    if (strpos($status, 'espera') !== false || strpos($status, 'proceso') !== false || strpos($status, 'atención') !== false) {
        return 'warning';
    }
    if (strpos($status, 'abierto') !== false) {
        return 'success';
    }
    if (strpos($status, 'sin asignar') !== false) {
        return 'danger';
    }

    return 'primary';
}

function ticketHistoryActionLabel(string $action): string
{
    switch ($action) {
        case 'created':
            return 'Ticket Creado';
        case 'updated':
            return 'Ticket Actualizado';
        case 'closed':
            return 'Ticket Cerrado';
        case 'closed_inactivity':
            return 'Ticket Cerrado por Inactividad';
        case 'deleted':
            return 'Ticket Eliminado';
        case 'reopened':
            return 'Ticket Reabierto';
        default:
            return 'Acción en Ticket';
    }
}

function ticketHistoryActionIcon(string $action): string
{
    switch ($action) {
        case 'created':
            return 'fa-plus';
        case 'updated':
            return 'fa-edit';
        case 'closed':
            return 'fa-lock';
        case 'closed_inactivity':
            return 'fa-clock';
        case 'deleted':
            return 'fa-trash';
        case 'reopened':
            return 'fa-unlock';
        default:
            return 'fa-circle';
    }
}

function ticketHistoryActionCssClass(string $action): string
{
    return $action === 'closed_inactivity' ? 'closed-inactivity' : $action;
}
