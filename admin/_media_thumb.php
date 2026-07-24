<?php
declare(strict_types=1);

/*
 * Artdon HK Website Admin V7.1.8.143
 * Safe media thumbnail/proxy for admin media picker.
 * It reads image files from /uploads directly through PHP so the picker is not affected by
 * relative path issues, Nginx static rules, transparent PNG preview backgrounds, or cached admin JS/CSS.
 * V7.1.8.143 also creates small cached thumbnails when GD is available, so large original images do not make the picker spin.
 */

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

function artdon_thumb_fail(string $text = 'Image unavailable'): void
{
    http_response_code(200);
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: no-store');
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="480" height="360" viewBox="0 0 480 360">'
        . '<rect width="480" height="360" fill="#f3f4f6"/>'
        . '<rect x="16" y="16" width="448" height="328" fill="none" stroke="#d1d5db" stroke-width="2" stroke-dasharray="8 8"/>'
        . '<text x="240" y="178" text-anchor="middle" font-family="Arial,sans-serif" font-size="18" font-weight="700" fill="#9ca3af">'.$safe.'</text>'
        . '</svg>';
    exit;
}

$path = trim((string)($_GET['path'] ?? ''));
if ($path === '') artdon_thumb_fail('No image');
$path = str_replace('\\', '/', rawurldecode($path));
if (preg_match('#^https?://#i', $path)) {
    $u = parse_url($path);
    $path = is_array($u) ? (string)($u['path'] ?? '') : '';
}
$path = preg_replace('#/+#', '/', $path) ?: $path;
$path = ltrim($path, '/');
while (strpos($path, '../') === 0) $path = substr($path, 3);

$root = realpath(dirname(__DIR__));
if (!$root) artdon_thumb_fail('Root error');
$abs = realpath($root . '/' . $path);
if (!$abs || !is_file($abs)) artdon_thumb_fail('File missing');
$rootNorm = str_replace('\\', '/', $root);
$absNorm = str_replace('\\', '/', $abs);
if (strpos($absNorm, $rootNorm . '/uploads/') !== 0) artdon_thumb_fail('Not allowed');


function artdon_thumb_send_file(string $file, string $mime, int $mtime, string $etagPrefix = 'artdon-media-thumb'): void
{
    $etag = 'W/"' . $etagPrefix . '-' . md5($file . ':' . $mtime . ':' . (@filesize($file) ?: 0)) . '"';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($file));
    header('Cache-Control: public, max-age=86400');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    readfile($file);
    exit;
}

function artdon_thumb_create_cached(string $abs, string $absNorm, string $root, string $ext, int $mtime): ?array
{
    if (!function_exists('imagecreatetruecolor') || !function_exists('getimagesize')) return null;
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) return null;

    $info = @getimagesize($abs);
    if (!is_array($info) || empty($info[0]) || empty($info[1])) return null;
    $srcW = (int)$info[0];
    $srcH = (int)$info[1];
    if ($srcW <= 0 || $srcH <= 0) return null;

    $maxW = 520;
    $maxH = 390;
    $ratio = min($maxW / $srcW, $maxH / $srcH, 1);
    $dstW = max(1, (int)round($srcW * $ratio));
    $dstH = max(1, (int)round($srcH * $ratio));

    $cacheDir = $root . '/storage/media_thumbs';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
    if (!is_dir($cacheDir) || !is_writable($cacheDir)) return null;
    $cache = $cacheDir . '/' . md5($absNorm . ':' . $mtime . ':' . (@filesize($abs) ?: 0)) . '.jpg';
    if (is_file($cache) && (@filemtime($cache) ?: 0) >= $mtime) {
        return [$cache, 'image/jpeg', (int)(@filemtime($cache) ?: $mtime)];
    }

    $src = null;
    try {
        if (in_array($ext, ['jpg','jpeg'], true) && function_exists('imagecreatefromjpeg')) $src = @imagecreatefromjpeg($abs);
        elseif ($ext === 'png' && function_exists('imagecreatefrompng')) $src = @imagecreatefrompng($abs);
        elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($abs);
        elseif ($ext === 'gif' && function_exists('imagecreatefromgif')) $src = @imagecreatefromgif($abs);
    } catch (Throwable $e) { $src = null; }
    if (!$src) return null;

    $dst = imagecreatetruecolor($dstW, $dstH);
    if (!$dst) { @imagedestroy($src); return null; }
    $bg = imagecolorallocate($dst, 245, 247, 250);
    imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $bg);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    $ok = function_exists('imagejpeg') ? @imagejpeg($dst, $cache, 82) : false;
    @imagedestroy($src);
    @imagedestroy($dst);
    if (!$ok || !is_file($cache)) return null;
    @touch($cache, time());
    return [$cache, 'image/jpeg', (int)(@filemtime($cache) ?: time())];
}

$ext = strtolower(pathinfo($absNorm, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'avif' => 'image/avif',
    'bmp' => 'image/bmp',
];
if (!isset($mimeMap[$ext])) artdon_thumb_fail('Not image');

$mtime = @filemtime($abs) ?: time();
$cached = artdon_thumb_create_cached($abs, $absNorm, $root, $ext, (int)$mtime);
if (is_array($cached)) {
    artdon_thumb_send_file((string)$cached[0], (string)$cached[1], (int)$cached[2], 'artdon-media-thumb-cache');
}
artdon_thumb_send_file($abs, $mimeMap[$ext], (int)$mtime);
