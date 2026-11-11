<?php
/**
 * Suite de pruebas WhatsApp — CLI y navegador
 *
 * CLI:
 *   php whatsapp/test_webhook.php
 *   php whatsapp/test_webhook.php http://localhost:90/GK
 *   php whatsapp/test_webhook.php http://localhost:90/GK --live-send=5215512345678
 *
 * Navegador:
 *   /whatsapp/test_webhook.php
 *   /whatsapp/test_webhook.php?base_url=http://localhost:90/GK&live_send=1&phone=521...
 */

declare(strict_types=1);

require __DIR__ . '/includes/test_suite.php';

$isCli = PHP_SAPI === 'cli';

$baseUrl = 'http://localhost:90/GK';
$livePhone = null;

if ($isCli) {
    $args = $argv ?? [];
    foreach (array_slice($args, 1) as $arg) {
        if (str_starts_with($arg, '--live-send=')) {
            $livePhone = substr($arg, strlen('--live-send='));
        } elseif (!str_starts_with($arg, '--')) {
            $baseUrl = rtrim($arg, '/');
        }
    }
} else {
    require_once dirname(__DIR__) . '/auth.php';
    checkAuth();
    header('Content-Type: text/html; charset=utf-8');

    $baseUrl = rtrim((string) ($_GET['base_url'] ?? 'http://localhost:90/GK'), '/');
    if (!empty($_GET['phone'])) {
        $livePhone = preg_replace('/\D+/', '', (string) $_GET['phone']);
    }
    if (empty($_GET['live_send']) || $_GET['live_send'] !== '1') {
        $livePhone = null;
    }
}

$webhookUrl = $baseUrl . '/whatsapp/webhook.php';
$liveSend = $livePhone !== null && $livePhone !== '';

$results = whatsappRunTestSuite($baseUrl, $liveSend, $livePhone);
$summary = whatsappTestSuiteSummary($results);

if ($isCli) {
    echo "\n";
    echo "╔══════════════════════════════════════════════════╗\n";
    echo "║       WhatsApp — Suite de pruebas SecuriTI       ║\n";
    echo "╚══════════════════════════════════════════════════╝\n\n";
    echo "URL webhook : {$webhookUrl}\n";
    echo "Chat panel  : {$baseUrl}/whatsapp/chat.php\n";
    if ($liveSend) {
        echo "Envío real  : {$livePhone}\n";
    }
    echo "\n";

    $currentGroup = '';
    foreach ($results as $r) {
        if ($r['group'] !== $currentGroup) {
            $currentGroup = $r['group'];
            echo "── {$currentGroup} " . str_repeat('─', max(0, 44 - strlen($currentGroup))) . "\n";
        }
        $icon = $r['ok'] ? '✓' : '✗';
        $status = $r['ok'] ? 'OK ' : 'FAIL';
        echo "  [{$status}] {$icon} {$r['name']}\n";
        echo "         {$r['message']}\n";
        if (!$r['ok'] && !empty($r['details'])) {
            $detail = is_string($r['details']) ? $r['details'] : json_encode($r['details'], JSON_UNESCAPED_UNICODE);
            echo "         → " . substr($detail, 0, 120) . "\n";
        }
    }

    echo "\n";
    echo "══════════════════════════════════════════════════\n";
    echo " Resultado: {$summary['passed']}/{$summary['total']} pruebas OK";
    if ($summary['failed'] > 0) {
        echo " — {$summary['failed']} fallaron";
    }
    echo "\n";
    echo "══════════════════════════════════════════════════\n\n";

    exit($summary['all_ok'] ? 0 : 1);
}

// --- Salida HTML (navegador) ---
$groups = [];
foreach ($results as $r) {
    $groups[$r['group']][] = $r;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pruebas WhatsApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .test-row.pass { background: #f6fff8; }
        .test-row.fail { background: #fff5f5; }
    </style>
</head>
<body class="py-4">
<div class="container" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1"><i class="fab fa-whatsapp text-success"></i> Pruebas WhatsApp</h1>
            <p class="text-muted mb-0 small">Webhook: <code><?= htmlspecialchars($webhookUrl) ?></code></p>
        </div>
        <a href="chat.php" class="btn btn-success btn-sm">Ir al chat</a>
    </div>

    <form method="get" class="card mb-4">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small">URL base</label>
                <input type="text" name="base_url" class="form-control form-control-sm" value="<?= htmlspecialchars($baseUrl) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Teléfono envío real (opcional)</label>
                <input type="tel" name="phone" class="form-control form-control-sm" value="<?= htmlspecialchars($livePhone ?? '') ?>" placeholder="521...">
            </div>
            <div class="col-md-3">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="live_send" value="1" id="liveSend" <?= $liveSend ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="liveSend">Enviar mensaje real</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100">Ejecutar pruebas</button>
            </div>
        </div>
    </form>

    <div class="alert alert-<?= $summary['all_ok'] ? 'success' : 'warning' ?>">
        <strong><?= (int) $summary['passed'] ?>/<?= (int) $summary['total'] ?></strong> pruebas exitosas
        <?php if ($summary['failed'] > 0): ?>
            — <strong><?= (int) $summary['failed'] ?> fallaron</strong>
        <?php endif; ?>
    </div>

    <?php foreach ($groups as $groupName => $items): ?>
    <div class="card mb-3">
        <div class="card-header py-2 fw-semibold"><?= htmlspecialchars($groupName) ?></div>
        <ul class="list-group list-group-flush">
            <?php foreach ($items as $r): ?>
            <li class="list-group-item test-row <?= $r['ok'] ? 'pass' : 'fail' ?>">
                <div class="d-flex justify-content-between gap-2">
                    <span><?= $r['ok'] ? '✅' : '❌' ?> <?= htmlspecialchars($r['name']) ?></span>
                    <span class="badge bg-<?= $r['ok'] ? 'success' : 'danger' ?>"><?= $r['ok'] ? 'OK' : 'FAIL' ?></span>
                </div>
                <small class="text-muted"><?= htmlspecialchars($r['message']) ?></small>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
</div>
</body>
</html>
