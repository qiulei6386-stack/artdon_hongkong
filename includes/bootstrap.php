<?php

declare(strict_types=1);

function web_public_get_without_session(): bool
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) return false;
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    $uri = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));
    if (strpos($script, '/admin/') !== false || strpos($uri, '/admin/') !== false) return false;
    if (preg_match('~/(api|cron)/~i', $script.$uri)) return false;
    if (preg_match('~/(submit_|sync_|bridge_|repair_|artdon_emergency_)~i', basename($script))) return false;
    foreach (['inquiry','preview','nocache','admin','login','logout'] as $k) {
        if (array_key_exists($k, $_GET)) return false;
    }
    return true;
}

function web_ensure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

// Public GET pages do not need PHP sessions. Avoid PHPSESSID + no-store headers on the public website.
if (!web_public_get_without_session()) {
    web_ensure_session();
}

require_once __DIR__ . '/content.php';

function web_favicon_path(): string
{
    $site = web_get_block('site');
    $path = trim((string)($site['favicon'] ?? ''));
    if ($path === '' || str_contains($path, "\0") || str_contains($path, '..')) {
        return 'assets/img/favicon-artdon.png';
    }
    return $path;
}

function web_favicon_mime_type(string $path): string
{
    return match (strtolower((string)pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        default => 'image/png',
    };
}

function web_inject_favicon(string $html): string
{
    if (stripos($html, '</head>') === false || stripos($html, 'rel="icon"') !== false || stripos($html, "rel='icon'") !== false) {
        return $html;
    }
    $path = web_favicon_path();
    $href = web_e($path);
    $type = web_e(web_favicon_mime_type($path));
    $tag = '<link rel="icon" href="' . $href . '" type="' . $type . '">';
    return preg_replace('~</head\s*>~i', $tag . '</head>', $html, 1) ?? $html;
}

if (web_public_get_without_session() && !defined('WEB_FAVICON_OUTPUT_BUFFER')) {
    define('WEB_FAVICON_OUTPUT_BUFFER', true);
    ob_start('web_inject_favicon');
}

function web_e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function web_csrf_token(): string
{
    web_ensure_session();
    if (empty($_SESSION['web_csrf'])) {
        $_SESSION['web_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['web_csrf'];
}

function web_verify_csrf(?string $token): bool
{
    web_ensure_session();
    return is_string($token) && hash_equals(web_csrf_token(), $token);
}
