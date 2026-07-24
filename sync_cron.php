<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/sync.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$error = null;
$pdo = web_db($error);
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Database unavailable.']);
    exit;
}
web_migrate($pdo);
$key = (string)($_GET['key'] ?? '');
$expected = (string)web_setting_get($pdo, 'sync_cron_key', '');
if ($expected === '' || !hash_equals($expected, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid cron key.']);
    exit;
}
if (!web_sync_enabled($pdo)) {
    echo json_encode(['ok' => true, 'message' => 'Sync disabled.', 'processed' => 0]);
    exit;
}
$summary = web_sync_process_queue($pdo, 30);
echo json_encode(['ok' => true] + $summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
