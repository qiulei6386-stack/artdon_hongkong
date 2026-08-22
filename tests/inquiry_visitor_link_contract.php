<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$inquiries = file_get_contents($root . '/admin/inquiries.php');
$analytics = file_get_contents($root . '/admin/visitor_analytics.php');
if (!is_string($inquiries) || !is_string($analytics)) {
    fwrite(STDERR, "Unable to read inquiry visitor link sources.\n");
    exit(1);
}

$inquiryContracts = [
    'function inquiry_visitor_analytics_url',
    "\$params['visitor'] = \$visitorId",
    "'from_inquiry' =>",
    "'range' => 'custom'",
    '查看此客户的访问页面和路径',
    'OR ip_address LIKE ?',
];
foreach ($inquiryContracts as $needle) {
    if (!str_contains($inquiries, $needle)) {
        fwrite(STDERR, "Missing inquiry visitor link contract: {$needle}\n");
        exit(1);
    }
}

$analyticsContracts = [
    '$fromInquiryId = max(0, (int)($_GET[\'from_inquiry\'] ?? 0));',
    '返回询盘 #',
    'var initialVisitor=',
    'if(initialVisitor) openDrawer(initialVisitor);',
    "fetch('visitor_analytics.php?partial=visitor&visitor='",
];
foreach ($analyticsContracts as $needle) {
    if (!str_contains($analytics, $needle)) {
        fwrite(STDERR, "Missing visitor analytics deep-link contract: {$needle}\n");
        exit(1);
    }
}

echo "inquiry visitor link contract passed\n";
