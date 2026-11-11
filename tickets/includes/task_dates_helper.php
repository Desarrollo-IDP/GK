<?php
/**
 * Fechas planificadas de tareas (inicio / término)
 */

function taskTableHasDateColumns(mysqli $conn): bool
{
    $r = $conn->query("SHOW COLUMNS FROM tasks LIKE 'start_date'");
    return $r && $r->num_rows > 0;
}

/**
 * @return string|null|false null si vacío, string Y-m-d si válido, false si inválido
 */
function taskNormalizeDateInput(?string $value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        return false;
    }
    return $value;
}

function taskValidateDateRange(?string $start_date, ?string $end_date): ?string
{
    if ($start_date && $end_date && $end_date < $start_date) {
        return 'La fecha de término no puede ser anterior a la fecha de inicio.';
    }
    return null;
}

function taskFormatDateDisplay(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return htmlspecialchars($date);
    }
    return date('d/m/Y', $ts);
}

function taskFormatDateForInput(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts === false ? '' : date('Y-m-d', $ts);
}
