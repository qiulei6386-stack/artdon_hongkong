<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sync = file_get_contents($root . '/includes/sync.php');
$inquiries = file_get_contents($root . '/admin/inquiries.php');
$contact = file_get_contents($root . '/admin/contact_page.php');
if (!is_string($sync) || !is_string($inquiries) || !is_string($contact)) {
    fwrite(STDERR, "Unable to read status sync sources.\n");
    exit(1);
}

$requiredSync = [
    'function web_sync_inquiry_status',
    "['replied', 'closed']",
    "web_sync_enqueue(\$pdo, 'inquiry.status_changed'",
    'bool $processNow = true',
    "'queued' => true",
    'web_sync_process_item($pdo, $queueId)',
    "'bridge_inquiry_id'",
    "'status_changed_at'",
];
foreach ($requiredSync as $needle) {
    if (!str_contains($sync, $needle)) {
        fwrite(STDERR, "Missing sync contract: {$needle}\n");
        exit(1);
    }
}
if (substr_count($inquiries, 'web_sync_inquiry_status(') < 2) {
    fwrite(STDERR, "Single and batch inquiry status paths must both sync.\n");
    exit(1);
}
if (!str_contains($contact, "web_sync_inquiry_status(\$pdo, \$inquiry, 'replied')")
    || !str_contains($contact, "web_sync_inquiry_status(\$pdo, \$inquiry, 'closed')")) {
    fwrite(STDERR, "Contact record handled/archive paths must sync.\n");
    exit(1);
}

echo "inquiry status sync contract passed\n";
