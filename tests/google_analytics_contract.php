<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/google_analytics.php';

function ga_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

foreach ([
    ['/index.php', '/', 'GET', true],
    ['/product.php', '/products/downlights/example', 'HEAD', true],
    ['/contact.php', '/contact.php?inquiry=success', 'GET', true],
    ['/index.php', '/?nocache=1', 'GET', true],
    ['/contact.php', '/contact.php?next=/admin/', 'GET', true],
    ['/admin/inquiries.php', '/admin/inquiries.php', 'GET', false],
    ['/index.php', '/ADMIN/login.php', 'GET', false],
    ['/api/lighting-calculator.php', '/api/lighting-calculator.php', 'GET', false],
    ['/cron/run.php', '/cron/run.php', 'GET', false],
    ['/submit_inquiry.php', '/submit_inquiry.php', 'GET', false],
    ['/sync_status.php', '/sync_status.php', 'GET', false],
    ['/contact.php', '/contact.php', 'POST', false],
] as [$script, $uri, $method, $allowed]) {
    $_SERVER = ['SCRIPT_NAME' => $script, 'REQUEST_URI' => $uri, 'REQUEST_METHOD' => $method];
    ga_check(web_google_analytics_allowed() === $allowed, 'Wrong route eligibility: ' . $method . ' ' . $uri);
}

$html = '<!doctype html><html><HEAD data-test="1"><title>Example</title></HEAD><body>Original</body></html>';
$tagged = web_inject_google_analytics($html);
ga_check(strpos($tagged, 'gtag/js?id=G-C63RTCJ402') < strpos($tagged, '<title>'), 'Tag must be at start of head');
ga_check(substr_count($tagged, "gtag('config', 'G-C63RTCJ402')") === 1, 'Exactly one config call');
ga_check(substr_count($tagged, 'gtag/js?id=G-C63RTCJ402') === 1, 'Exactly one script');
ga_check(str_contains($tagged, '<body>Original</body>'), 'Body must be preserved');
ga_check(web_inject_google_analytics($tagged) === $tagged, 'Filter must be idempotent');
foreach (['{"status":"ok"}', '<?xml version="1.0"?><urlset/>', '%PDF-1.7'] as $content) {
    ga_check(web_inject_google_analytics($content) === $content, 'Non-HTML response must be unchanged');
}

// The cache test uses synthetic HTML in an isolated temporary directory, no DB.
$dir = sys_get_temp_dir() . '/artdon-ga-contract-' . bin2hex(random_bytes(6));
mkdir($dir, 0700);
try {
    $cached = '<!doctype html><html><head><title>Cached</title></head><body>' . str_repeat('Synthetic ', 160) . '</body></html>';
    $cacheFile = $dir . '/page.html';
    file_put_contents($cacheFile, $cached);
    $runner = $dir . '/runner.php';
    $cacheHelper = dirname(__DIR__) . '/includes/public_cache.php';
    file_put_contents($runner, '<?php http_response_code(200); $_SERVER = ["REQUEST_METHOD"=>"GET", "SCRIPT_NAME"=>"/index.php", "REQUEST_URI"=>"/"];'
        . ' function web_public_cache_dir(): string { return ' . var_export($dir, true) . '; }'
        . ' function web_public_cache_key(string $group): string { return "page"; }'
        . ' require ' . var_export($cacheHelper, true) . '; web_public_cache_start();');
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner), $lines, $exitCode);
    $hit = implode("\n", $lines);
    ga_check($exitCode === 0, 'Cache HIT fixture exited with an error');
    ga_check(substr_count($hit, "gtag('config', 'G-C63RTCJ402')") === 1, 'Cache HIT must include tag exactly once');
    ga_check(file_get_contents($cacheFile) === $cached, 'Cache HIT must not rewrite stored HTML');

    unlink($cacheFile);
    file_put_contents($runner, file_get_contents($runner) . ' echo ' . var_export($cached, true) . ';');
    $lines = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner), $lines, $exitCode);
    ga_check($exitCode === 0, 'Cache MISS fixture exited with an error');
    ga_check(substr_count(implode("\n", $lines), "gtag('config', 'G-C63RTCJ402')") === 1, 'Cache MISS must include tag exactly once');
    ga_check(file_get_contents($cacheFile) === $cached, 'Cache must store original HTML before output transform');

    // Simulate bootstrap-first pages: starting twice must still produce one tag.
    $source = file_get_contents($runner);
    $source = str_replace('web_public_cache_start();', 'web_google_analytics_start(); web_public_cache_start();', $source);
    file_put_contents($runner, $source);
    $lines = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner), $lines, $exitCode);
    ga_check($exitCode === 0 && substr_count(implode("\n", $lines), "gtag('config', 'G-C63RTCJ402')") === 1, 'Repeated buffer starts must not duplicate the tag');
} finally {
    foreach (glob($dir . '/*') ?: [] as $file) unlink($file);
    rmdir($dir);
}

echo "GA4 route, head, duplicate and cache contract checks passed.\n";
