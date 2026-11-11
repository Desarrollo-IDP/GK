<?php
/**
 * Descripción de tareas con formato (HTML sanitizado)
 */

function taskSanitizeRichDescription(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowed = '<p><br><strong><b><em><i><u><s><strike><ul><ol><li><h1><h2><h3><blockquote><a><span>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/<(a)\s+[^>]*href\s*=\s*["\']?\s*javascript:[^>]*>/i', '<$1>', $html);
    $html = preg_replace('/\s*on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace_callback(
        '/<(a)\s+[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>/i',
        static function (array $m): string {
            $href = trim($m[2]);
            if ($href === '' || preg_match('#^\s*javascript:#i', $href)) {
                return '<a>';
            }
            if (!preg_match('#^https?://#i', $href) && !preg_match('#^mailto:#i', $href) && $href[0] !== '/') {
                $href = 'https://' . $href;
            }
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" rel="noopener noreferrer" target="_blank">';
        },
        $html
    );

    return $html;
}

function taskRichDescriptionIsEmpty(?string $html): bool
{
    $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[\s\x{00A0}]+/u', '', $text);
    return $text === '';
}

function taskPrepareDescriptionForEditor(?string $content): string
{
    $content = trim((string) $content);
    if ($content === '') {
        return '';
    }
    if ($content !== strip_tags($content)) {
        return taskSanitizeRichDescription($content);
    }
    return '<p>' . nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'), false) . '</p>';
}

function taskRenderRichDescription(?string $html): string
{
    $html = taskSanitizeRichDescription((string) $html);
    if ($html === '') {
        return '<span class="text-muted">—</span>';
    }
    return $html;
}
