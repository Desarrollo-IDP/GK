<?php

if (!function_exists('ticketsRelativeBaseUrl')) {
    function ticketsRelativeBaseUrl(): string {
        $scriptPath = parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH);
        $ticketRootPosition = is_string($scriptPath) ? stripos($scriptPath, '/tickets/') : false;
        if ($ticketRootPosition === false) {
            return '';
        }

        $relativeScriptPath = substr($scriptPath, $ticketRootPosition + strlen('/tickets/'));
        $relativeDirectory = dirname($relativeScriptPath);
        if ($relativeDirectory === '.') {
            return '';
        }

        return str_repeat('../', substr_count($relativeDirectory, '/') + 1);
    }
}

$base_url = ticketsRelativeBaseUrl();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

?>
