<?php
/**
 * Artdon public page micro-cache V7.1.8.137
 * - Public GET/HEAD pages only.
 * - No cache for admin, API, submit/sync endpoints or inquiry feedback URLs.
 * - Stores HTML in storage/page_cache for a short TTL to reduce homepage TTFB.
 */
declare(strict_types=1);

require_once __DIR__ . '/security_headers.php';

if (!function_exists('web_public_cache_allowed')) {
    function web_public_cache_allowed(): bool
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET', 'HEAD'], true)) return false;
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
        $uri = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));
        if (strpos($script, '/admin/') !== false || strpos($uri, '/admin/') !== false) return false;
        if (preg_match('~/(api|cron)/~i', $script.$uri)) return false;
        if (preg_match('~/(submit_|sync_|bridge_|repair_|artdon_emergency_)~i', basename($script))) return false;
        foreach (['inquiry','preview','nocache','clear_cache','admin','login','logout'] as $k) {
            if (array_key_exists($k, $_GET)) return false;
        }
        return true;
    }
}

if (!function_exists('web_public_cache_dir')) {
    function web_public_cache_dir(): string
    {
        return dirname(__DIR__) . '/storage/page_cache';
    }
}

if (!function_exists('web_public_cache_key')) {
    function web_public_cache_key(string $group): string
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        // V7.1.8.106: include this cache file mtime in the key so category/menu
        // fixes take effect immediately after overwrite instead of waiting for old HTML TTL.
        $version = 'v718137-' . (string)@filemtime(__FILE__);
        return sha1($group . '|' . $version . '|' . $host . '|' . $uri);
    }
}

if (!function_exists('web_public_cache_send_headers')) {
    function web_public_cache_send_headers(int $ttl, string $state): void
    {
        if (headers_sent()) return;
        artdon_security_headers_send();
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: public, max-age=' . max(30, min(600, $ttl)) . ', stale-while-revalidate=600');
        header('X-Artdon-Page-Cache: ' . $state);
    }
}

if (!function_exists('web_public_cache_start')) {
    function web_public_cache_start(string $group = 'page', int $ttl = 300): void
    {
        if (!web_public_cache_allowed()) return;
        $ttl = max(30, min(1800, $ttl));
        $dir = web_public_cache_dir();
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (!is_dir($dir) || !is_writable($dir)) return;
        $file = $dir . '/' . web_public_cache_key($group) . '.html';
        if (is_file($file) && (time() - (int)@filemtime($file) < $ttl)) {
            web_public_cache_send_headers($ttl, 'HIT');
            readfile($file);
            exit;
        }
        $GLOBALS['__ARTDON_PUBLIC_CACHE_FILE'] = $file;
        $GLOBALS['__ARTDON_PUBLIC_CACHE_TTL'] = $ttl;
        ob_start(static function (string $html): string {
            $file = (string)($GLOBALS['__ARTDON_PUBLIC_CACHE_FILE'] ?? '');
            $ttl = (int)($GLOBALS['__ARTDON_PUBLIC_CACHE_TTL'] ?? 300);
            if ($file !== '' && strlen($html) > 1024 && http_response_code() === 200 && stripos($html, '<html') !== false) {
                @file_put_contents($file, $html, LOCK_EX);
            }
            web_public_cache_send_headers($ttl, 'MISS');
            return $html;
        });
    }
}


if (!function_exists('web_public_cache_clear')) {
    function web_public_cache_clear(string $group = ''): int
    {
        $dir = web_public_cache_dir();
        if (!is_dir($dir)) return 0;
        $count = 0;
        foreach (glob($dir . '/*.html') ?: [] as $file) {
            if (is_file($file) && @unlink($file)) $count++;
        }
        return $count;
    }
}
