<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/sync.php';

$error = null;
$pdo = web_db($error);
if (!$pdo) {
    fwrite(STDERR, "Database connection failed: {$error}\n");
    exit(1);
}
web_migrate($pdo);

if (!web_sync_enabled($pdo)) {
    echo "Sync disabled.\n";
    exit(0);
}

$summary = web_sync_process_queue($pdo, 30);
echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit($summary['failed'] > 0 ? 2 : 0);
